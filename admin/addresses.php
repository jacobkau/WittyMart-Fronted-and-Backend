<?php
require_once 'includes/config.php';
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add':
            case 'edit':
                $id             = intval($_POST['id'] ?? 0);
                $user_id        = intval($_POST['user_id'] ?? 0);
                $label          = sanitize($_POST['label'] ?? 'Home');
                $recipient_name = sanitize($_POST['recipient_name'] ?? '');
                $phone          = sanitize($_POST['phone'] ?? '');
                $county         = sanitize($_POST['county'] ?? '');
                $city           = sanitize($_POST['city'] ?? '');
                $address_line   = sanitize($_POST['address_line'] ?? '');
                $instructions   = sanitize($_POST['delivery_instructions'] ?? '');
                $is_default     = !empty($_POST['is_default']);

                if (!$user_id || $recipient_name === '' || $phone === '' || $county === '' || $address_line === '') {
                    $message = 'All required fields must be filled.';
                    $messageType = 'error';
                    break;
                }

                if ($is_default) {
                    $pdo->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?")->execute([$user_id]);
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE user_addresses 
                        SET label=?, recipient_name=?, phone=?, county=?, city=?, address_line=?, 
                            delivery_instructions=?, is_default=?, updated_at=NOW()
                        WHERE id=?
                    ");
                    $stmt->execute([$label, $recipient_name, $phone, $county, $city,
                                    $address_line, $instructions, $is_default ? 1 : 0, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_addresses
                        (user_id, label, recipient_name, phone, county, city, address_line, delivery_instructions, is_default)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $label, $recipient_name, $phone, $county, $city,
                                    $address_line, $instructions, $is_default ? 1 : 0]);
                }
                $message = 'Address saved successfully!';
                $messageType = 'success';
                break;

            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Address deleted.';
                $messageType = 'success';
                break;

            case 'set_default':
                $id      = intval($_POST['id'] ?? 0);
                $user_id = intval($_POST['user_id'] ?? 0);
                $pdo->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?")->execute([$user_id]);
                $pdo->prepare("UPDATE user_addresses SET is_default = TRUE WHERE id = ?")->execute([$id]);
                $message = 'Default address updated.';
                $messageType = 'success';
                break;
        }
    } catch (PDOException $e) {
        error_log('Admin address error: ' . $e->getMessage());
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Filters
$search = trim($_GET['q'] ?? '');
$filter_county = trim($_GET['county'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(a.recipient_name ILIKE ? OR a.phone ILIKE ? OR a.address_line ILIKE ? OR u.name ILIKE ? OR u.email ILIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s);
}
if ($filter_county !== '') {
    $where[] = "a.county = ?";
    $params[] = $filter_county;
}
$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$addresses = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name AS user_name, u.email AS user_email
        FROM user_addresses a
        INNER JOIN users u ON a.user_id = u.id
        $where_sql
        ORDER BY a.is_default DESC, a.created_at DESC
    ");
    $stmt->execute($params);
    $addresses = $stmt->fetchAll();
} catch (PDOException $e) { error_log('Load addresses: ' . $e->getMessage()); }

// Users for the add-address modal
$allUsers = [];
try {
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'user' ORDER BY name");
    $allUsers = $stmt->fetchAll();
} catch (PDOException $e) {}

$kenya_counties = [
    'Baringo','Bomet','Bungoma','Busia','Elgeyo-Marakwet','Embu','Garissa',
    'Homa Bay','Isiolo','Kajiado','Kakamega','Kericho','Kiambu','Kilifi',
    'Kirinyaga','Kisii','Kisumu','Kitui','Kwale','Laikipia','Lamu','Machakos',
    'Makueni','Mandera','Marsabit','Meru','Migori','Mombasa',"Murang'a",
    'Nairobi','Nakuru','Nandi','Narok','Nyamira','Nyandarua','Nyeri','Samburu',
    'Siaya','Taita-Taveta','Tana River','Tharaka-Nithi','Trans Nzoia','Turkana',
    'Uasin Gishu','Vihiga','Wajir','West Pokot'
];

function jsEscape($str) {
    if ($str === null) return '';
    return str_replace(["\\", "'", '"', "\r", "\n", "\t"], ["\\\\", "\\'", '\\"', "\\r", "\\n", "\\t"], $str);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Addresses - Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .addr-default-badge {
            display:inline-block; padding:2px 8px; border-radius:10px;
            background:#ffc107; color:#333; font-size:10px; font-weight:700;
            margin-left:6px; text-transform:uppercase;
        }
        .btn-edit { background:#28a745; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; }
        .btn-delete { background:#dc3545; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; }
        .btn-default { background:#ffc107; color:#333; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; }
        .action-buttons { display:flex; gap:5px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .toolbar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
        .toolbar input, .toolbar select {
            padding:9px 14px; border:1px solid #ddd; border-radius:8px;
            font-size:14px; background:#fff;
        }
        .toolbar input { flex:1; min-width:220px; }
        .toolbar button {
            padding:9px 18px; background:#05573c; color:#fff; border:none;
            border-radius:8px; cursor:pointer; font-weight:600;
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>

        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <h1 style="margin:0; font-size:22px;"><i class="fas fa-map-marked-alt"></i> Manage Addresses</h1>
                    <p style="margin:4px 0 0; color:#888; font-size:13px;">View, edit and delete customer delivery addresses.</p>
                </div>
                <button class="btn-primary" onclick="openAddressModal(0)">
                    <i class="fas fa-plus"></i> Add Address
                </button>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form class="toolbar" method="GET">
                <input type="text" name="q" placeholder="Search by recipient, phone, user, address…" value="<?php echo htmlspecialchars($search); ?>">
                <select name="county">
                    <option value="">All Counties</option>
                    <?php foreach ($kenya_counties as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filter_county === $c ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($search || $filter_county): ?>
                    <a href="addresses.php" style="padding:9px 18px; background:#e0e0e0; color:#333; border-radius:8px; text-decoration:none; font-weight:600;">Reset</a>
                <?php endif; ?>
            </form>

            <div class="admin-card" style="padding:14px;">
                <?php if (count($addresses) > 0): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Recipient</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>County</th>
                                <th>Label</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($addresses as $a): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($a['user_name']); ?></strong><br>
                                        <small style="color:#888;"><?php echo htmlspecialchars($a['user_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['recipient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($a['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($a['address_line']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($a['county']); ?>
                                        <?php if ($a['is_default']): ?>
                                            <span class="addr-default-badge">Default</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['label']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-edit" onclick='openAddressModal(<?php echo $a['id']; ?>, <?php echo json_encode($a, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)' title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!$a['is_default']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="set_default">
                                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $a['user_id']; ?>">
                                                    <button type="submit" class="btn-default" title="Set as default">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this address?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                <button type="submit" class="btn-delete" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align:center; padding:40px; color:#888;">No addresses found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Address Modal -->
    <div id="addressModal" class="modal">
        <div class="modal-content" style="max-width: 620px;">
            <div class="modal-header">
                <h2 id="addrModalTitle"><i class="fas fa-map-marker-alt"></i> Add Address</h2>
                <span class="close" onclick="closeModal('addressModal')">&times;</span>
            </div>
            <form method="POST" id="addrForm">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="id" id="addrId" value="0">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Customer *</label>
                    <select name="user_id" id="addrUser" required>
                        <option value="">— Select User —</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] . ' (' . $u['email'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Label</label>
                        <select name="label" id="addrLabel">
                            <option value="Home">Home</option>
                            <option value="Work">Work</option>
                            <option value="Office">Office</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recipient Name *</label>
                        <input type="text" name="recipient_name" id="addrRecipient" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="text" name="phone" id="addrPhone" required>
                    </div>
                    <div class="form-group">
                        <label>County *</label>
                        <select name="county" id="addrCounty" required>
                            <option value="">— Select County —</option>
                            <?php foreach ($kenya_counties as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>City / Town</label>
                    <input type="text" name="city" id="addrCity">
                </div>

                <div class="form-group">
                    <label>Address Line *</label>
                    <textarea name="address_line" id="addrLine" required rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label>Delivery Instructions</label>
                    <textarea name="delivery_instructions" id="addrInstructions" rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_default" id="addrDefault" value="1">
                        Set as default for this user
                    </label>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Save Address
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openAddressModal(id, data) {
            const form = document.getElementById('addrForm');
            form.reset();
            document.getElementById('formAction').value = id > 0 ? 'edit' : 'add';
            document.getElementById('addrId').value = id;
            document.getElementById('addrModalTitle').innerHTML = id > 0
                ? '<i class="fas fa-edit"></i> Edit Address'
                : '<i class="fas fa-map-marker-alt"></i> Add Address';

            if (id > 0 && data) {
                document.getElementById('addrUser').value         = data.user_id;
                document.getElementById('addrLabel').value        = data.label;
                document.getElementById('addrRecipient').value    = data.recipient_name;
                document.getElementById('addrPhone').value        = data.phone;
                document.getElementById('addrCounty').value       = data.county;
                document.getElementById('addrCity').value         = data.city || '';
                document.getElementById('addrLine').value         = data.address_line;
                document.getElementById('addrInstructions').value = data.delivery_instructions || '';
                document.getElementById('addrDefault').checked    = data.is_default == 1 || data.is_default === true;
            }

            openModal('addressModal');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
    </script>
</body>
</html>
