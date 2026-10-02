<?php
require_once 'includes/config.php';
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add':
                $name           = sanitize($_POST['name'] ?? '');
                $contact_person = sanitize($_POST['contact_person'] ?? '');
                $email          = sanitize($_POST['email'] ?? '');
                $phone          = sanitize($_POST['phone'] ?? '');
                $address        = sanitize($_POST['address'] ?? '');
                $notes          = sanitize($_POST['notes'] ?? '');
                $status         = sanitize($_POST['status'] ?? 'active');

                if ($name === '') {
                    $message = 'Supplier name is required';
                    $messageType = 'error';
                    break;
                }

                $slug = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)), '-');

                $stmt = $pdo->prepare("
                    INSERT INTO suppliers (name, slug, contact_person, email, phone, address, notes, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $slug, $contact_person, $email, $phone, $address, $notes, $status]);

                if (function_exists('logActivity')) {
                    logActivity('add_supplier', 'Added supplier: ' . $name,
                        $_SESSION['user_id'], $_SESSION['user_name']);
                }

                $message = 'Supplier added successfully!';
                $messageType = 'success';
                break;

            case 'edit':
                $id             = intval($_POST['id'] ?? 0);
                $name           = sanitize($_POST['name'] ?? '');
                $contact_person = sanitize($_POST['contact_person'] ?? '');
                $email          = sanitize($_POST['email'] ?? '');
                $phone          = sanitize($_POST['phone'] ?? '');
                $address        = sanitize($_POST['address'] ?? '');
                $notes          = sanitize($_POST['notes'] ?? '');
                $status         = sanitize($_POST['status'] ?? 'active');

                if (!$id || $name === '') {
                    $message = 'Supplier name is required';
                    $messageType = 'error';
                    break;
                }

                $slug = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)), '-');

                $stmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET name = ?, slug = ?, contact_person = ?, email = ?, phone = ?, 
                        address = ?, notes = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $slug, $contact_person, $email, $phone, $address, $notes, $status, $id]);

                if (function_exists('logActivity')) {
                    logActivity('update_supplier', 'Updated supplier #' . $id,
                        $_SESSION['user_id'], $_SESSION['user_name']);
                }

                $message = 'Supplier updated successfully!';
                $messageType = 'success';
                break;

            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) {
                    $message = 'Invalid supplier ID';
                    $messageType = 'error';
                    break;
                }

                // Check if any products use this supplier
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
                $stmt->execute([$id]);
                $count = intval($stmt->fetchColumn());

                if ($count > 0) {
                    $message = "Cannot delete — $count product(s) still assigned to this supplier.";
                    $messageType = 'error';
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);

                if (function_exists('logActivity')) {
                    logActivity('delete_supplier', 'Deleted supplier #' . $id,
                        $_SESSION['user_id'], $_SESSION['user_name']);
                }

                $message = 'Supplier deleted successfully!';
                $messageType = 'success';
                break;
        }
    } catch (PDOException $e) {
        error_log('Supplier action error: ' . $e->getMessage());
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Filters
$search = trim($_GET['q'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(s.name ILIKE ? OR s.contact_person ILIKE ? OR s.email ILIKE ? OR s.phone ILIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s);
}
if ($filter_status !== '') {
    $where[] = "s.status = ?";
    $params[] = $filter_status;
}
$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch suppliers + product count per supplier
$suppliers = [];
try {
    $sql = "
        SELECT s.*, 
               (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) AS product_count
        FROM suppliers s
        $where_sql
        ORDER BY s.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get suppliers error: ' . $e->getMessage());
}

// Stats
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];
try {
    $stats['total']    = intval($pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn());
    $stats['active']   = intval($pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'active'")->fetchColumn());
    $stats['inactive'] = intval($pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'inactive'")->fetchColumn());
} catch (PDOException $e) {}

function jsEscape($str) {
    if ($str === null) return '';
    $str = str_replace("\\", "\\\\", $str);
    $str = str_replace("'", "\\'", $str);
    $str = str_replace('"', '\\"', $str);
    $str = str_replace("\r", "\\r", $str);
    $str = str_replace("\n", "\\n", $str);
    return $str;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat-card {
            background: #fff;
            padding: 14px 18px;
            border-radius: 10px;
            border-left: 4px solid #05573c;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .stat-card .stat-icon {
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #fff; background: #05573c; flex-shrink: 0;
        }
        .stat-card.info .stat-icon { background: #17a2b8; }
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.warning .stat-icon { background: #fd7e14; }
        .stat-card.warning { border-left-color: #fd7e14; }
        .stat-card .stat-label {
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
            color: #888; font-weight: 600; margin-bottom: 2px;
        }
        .stat-card .stat-value {
            font-size: 22px; font-weight: 700; color: #222; line-height: 1;
        }

        .toolbar-card {
            background: #fff; border-radius: 10px; padding: 14px 18px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05); margin-bottom: 16px;
            display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
        }
        .toolbar-search {
            flex: 1 1 260px; position: relative;
        }
        .toolbar-search i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: #999; font-size: 14px;
        }
        .toolbar-search input {
            width: 100%; padding: 9px 14px 9px 38px;
            border: 1px solid #ddd; border-radius: 8px;
            font-size: 14px; background: #fafafa;
        }
        .toolbar-search input:focus {
            outline: none; border-color: #05573c; background: #fff;
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }
        .toolbar-select {
            padding: 9px 12px; border: 1px solid #ddd; border-radius: 8px;
            background-color: #fff; color: #333; font-size: 13px; min-width: 140px;
        }
        .toolbar-btn {
            padding: 9px 16px; border: none; border-radius: 8px;
            background: #05573c; color: #fff; font-weight: 600;
            cursor: pointer; font-size: 13px; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .toolbar-btn:hover { background: #03402c; }

        .table-card {
            background: #fff; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05); overflow: hidden;
        }
        .table-inner { overflow-x: auto; padding: 4px 8px 8px; }

        .supplier-name {
            font-weight: 600; color: #05573c; text-decoration: none;
        }
        .supplier-name:hover { text-decoration: underline; }

        .status-pill {
            padding: 3px 10px; border-radius: 12px; font-size: 12px;
            color: #fff; display: inline-block;
        }
        .status-active { background: #28a745; }
        .status-inactive { background: #dc3545; }

        .btn-edit {
            background-color: #28a745; color: #fff; border: none;
            padding: 5px 10px; border-radius: 4px; cursor: pointer;
        }
        .btn-view {
            background-color: #17a2b8; color: #fff; border: none;
            padding: 5px 10px; border-radius: 4px; cursor: pointer;
        }
        .btn-delete {
            background-color: #dc3545; color: #fff; border: none;
            padding: 5px 10px; border-radius: 4px; cursor: pointer;
        }
        .action-buttons { display: flex; gap: 5px; }

        .product-count-pill {
            display: inline-block; min-width: 34px; text-align: center;
            padding: 2px 10px; border-radius: 10px;
            background: #e8f5f0; color: #05573c; font-weight: 700; font-size: 12px;
        }
        .product-count-pill.zero {
            background: #f0f0f0; color: #999;
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
                    <h1 style="margin:0; font-size:22px;">
                        <i class="fas fa-truck"></i> Suppliers
                    </h1>
                    <p style="margin:4px 0 0; color:#888; font-size:13px;">
                        Manage supplier records, contacts, and product assignments.
                    </p>
                </div>
                <button class="btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Supplier
                </button>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- STATS -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                    <div>
                        <div class="stat-label">Total Suppliers</div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                    </div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-label">Active</div>
                        <div class="stat-value"><?php echo $stats['active']; ?></div>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div>
                        <div class="stat-label">Inactive</div>
                        <div class="stat-value"><?php echo $stats['inactive']; ?></div>
                    </div>
                </div>
            </div>

            <!-- TOOLBAR -->
            <form class="toolbar-card" method="GET" action="suppliers.php">
                <div class="toolbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search by name, contact, email, phone..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status" class="toolbar-select">
                    <option value="">All Status</option>
                    <option value="active"   <?php echo $filter_status === 'active'   ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="toolbar-btn">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if ($search || $filter_status): ?>
                    <a href="suppliers.php" class="toolbar-btn" style="background:#6c757d;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>

            <!-- TABLE -->
            <div class="table-card">
                <?php if (count($suppliers) > 0): ?>
                    <div class="table-inner">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Products</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers as $s): ?>
                                    <tr>
                                        <td>
                                            <a href="#" class="supplier-name"
                                               onclick="viewSupplier(<?php echo $s['id']; ?>); return false;">
                                                <?php echo htmlspecialchars($s['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($s['contact_person'] ?: '—'); ?></td>
                                        <td>
                                            <?php if ($s['email']): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($s['email']); ?>"
                                                   style="color:#05573c;">
                                                    <?php echo htmlspecialchars($s['email']); ?>
                                                </a>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($s['phone']): ?>
                                                <a href="tel:<?php echo htmlspecialchars($s['phone']); ?>"
                                                   style="color:#05573c;">
                                                    <?php echo htmlspecialchars($s['phone']); ?>
                                                </a>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="product-count-pill <?php echo $s['product_count'] == 0 ? 'zero' : ''; ?>">
                                                <?php echo $s['product_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill status-<?php echo htmlspecialchars($s['status']); ?>">
                                                <?php echo htmlspecialchars($s['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-view" onclick="viewSupplier(<?php echo $s['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-edit" onclick="editSupplier(<?php echo $s['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display:inline;"
                                                      onsubmit="return confirm('Delete this supplier?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
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
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:60px 20px; color:#888;">
                        <i class="fas fa-truck" style="font-size:56px; display:block; margin-bottom:16px; opacity:0.3;"></i>
                        <h3 style="margin:0 0 8px; color:#555;">No suppliers yet</h3>
                        <p style="margin:0;">Click "Add Supplier" to create your first one.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- ADD / EDIT MODAL -->
    <div id="supplierModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-truck"></i> Add Supplier</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="supplierForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="supplierId">

                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Supplier Name *</label>
                    <input type="text" name="name" id="sName" required placeholder="e.g., Acme Electronics Ltd">
                </div>

                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Contact Person</label>
                        <input type="text" name="contact_person" id="sContact" placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="text" name="phone" id="sPhone" placeholder="+254 700 000 000">
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="sEmail" placeholder="contact@supplier.com">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                    <textarea name="address" id="sAddress" rows="2" placeholder="Street, City, Country"></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Notes</label>
                    <textarea name="notes" id="sNotes" rows="2" placeholder="Payment terms, delivery info, etc."></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="status" id="sStatus">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Save Supplier
                </button>
            </form>
        </div>
    </div>

    <!-- VIEW MODAL -->
    <div id="viewSupplierModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2><i class="fas fa-truck"></i> Supplier Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div id="viewSupplierContent" style="padding: 8px 4px;">
                <p style="text-align:center; padding:30px; color:#888;">
                    <i class="fas fa-spinner fa-spin"></i> Loading…
                </p>
            </div>
        </div>
    </div>

    <script>
        var suppliersData = <?php
            echo json_encode(array_map(function($s) {
                return [
                    'id' => (int)$s['id'],
                    'name' => $s['name'],
                    'contact_person' => $s['contact_person'],
                    'email' => $s['email'],
                    'phone' => $s['phone'],
                    'address' => $s['address'],
                    'notes' => $s['notes'],
                    'status' => $s['status'],
                    'product_count' => (int)$s['product_count'],
                ];
            }, $suppliers));
        ?>;

        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('supplierModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        function closeViewModal() {
            document.getElementById('viewSupplierModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-truck"></i> Add Supplier';
            document.getElementById('formAction').value = 'add';
            document.getElementById('supplierId').value = '';
            document.getElementById('sName').value = '';
            document.getElementById('sContact').value = '';
            document.getElementById('sPhone').value = '';
            document.getElementById('sEmail').value = '';
            document.getElementById('sAddress').value = '';
            document.getElementById('sNotes').value = '';
            document.getElementById('sStatus').value = 'active';
            openModal('supplierModal');
        }

        function editSupplier(id) {
            var s = suppliersData.find(function(x) { return x.id === id; });
            if (!s) { alert('Supplier not found'); return; }

            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Supplier';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('supplierId').value = s.id;
            document.getElementById('sName').value = s.name || '';
            document.getElementById('sContact').value = s.contact_person || '';
            document.getElementById('sPhone').value = s.phone || '';
            document.getElementById('sEmail').value = s.email || '';
            document.getElementById('sAddress').value = s.address || '';
            document.getElementById('sNotes').value = s.notes || '';
            document.getElementById('sStatus').value = s.status || 'active';
            openModal('supplierModal');
        }

        function viewSupplier(id) {
            var s = suppliersData.find(function(x) { return x.id === id; });
            if (!s) return;

            var html = ''
                + '<h3 style="margin:0 0 6px; color:#05573c;">' + escapeHtml(s.name) + '</h3>'
                + '<div style="display:flex; gap:8px; margin-bottom:14px;">'
                +   '<span class="status-pill status-' + s.status + '">' + escapeHtml(s.status) + '</span>'
                +   '<span class="product-count-pill ' + (s.product_count === 0 ? 'zero' : '') + '">'
                +     s.product_count + ' products'
                +   '</span>'
                + '</div>'
                + '<div style="background:#f8f9fa; border-radius:8px; padding:14px;">'
                +   row('Contact Person', s.contact_person)
                +   row('Email', s.email, s.email ? 'mailto:' + s.email : null)
                +   row('Phone', s.phone, s.phone ? 'tel:' + s.phone : null)
                +   row('Address', s.address)
                +   row('Notes', s.notes)
                + '</div>';

            document.getElementById('viewSupplierContent').innerHTML = html;
            openModal('viewSupplierModal');
        }

        function row(label, value, href) {
            if (!value) return '';
            var v = href
                ? '<a href="' + href + '" style="color:#05573c; font-weight:600;">' + escapeHtml(value) + '</a>'
                : escapeHtml(value);
            return '<div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee;">'
                + '<span style="color:#888;">' + label + '</span>'
                + '<span style="color:#333; text-align:right; max-width:60%;">' + v + '</span>'
                + '</div>';
        }

        function escapeHtml(s) {
            if (s == null) return '';
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(function(m) { m.style.display = 'none'; });
                document.body.style.overflow = 'auto';
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert-persistent').forEach(function(a) {
                a.style.transition = 'opacity 0.4s';
                setTimeout(function() { a.style.opacity = '0'; setTimeout(function(){ a.remove(); }, 400); }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>
