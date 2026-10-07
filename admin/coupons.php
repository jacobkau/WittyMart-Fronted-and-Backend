<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            // ============================================
            // CREATE COUPON
            // ============================================
            case 'create':
                $code            = strtoupper(sanitize(trim($_POST['code'] ?? '')));
                $description     = sanitize($_POST['description'] ?? '');
                $discount_type   = sanitize($_POST['discount_type'] ?? 'percentage');
                $discount_value  = floatval($_POST['discount_value'] ?? 0);
                $min_order       = floatval($_POST['min_order_amount'] ?? 0);
                $max_discount    = floatval($_POST['max_discount'] ?? 0);
                $usage_limit     = intval($_POST['usage_limit'] ?? 0);
                $per_user_limit  = intval($_POST['per_user_limit'] ?? 1);
                $starts_at       = trim($_POST['starts_at'] ?? '');
                $expires_at      = trim($_POST['expires_at'] ?? '');
                $status          = sanitize($_POST['status'] ?? 'active');

                if ($code === '' || $discount_value <= 0) {
                    $message = 'Coupon code and a positive discount value are required.';
                    $messageType = 'error';
                    break;
                }

                if (!in_array($discount_type, ['percentage', 'fixed'], true)) {
                    $message = 'Invalid discount type.';
                    $messageType = 'error';
                    break;
                }

                // Check for duplicate code
                $stmt = $pdo->prepare("SELECT id FROM coupons WHERE UPPER(code) = UPPER(?) LIMIT 1");
                $stmt->execute([$code]);
                if ($stmt->fetchColumn()) {
                    $message = 'A coupon with that code already exists.';
                    $messageType = 'error';
                    break;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO coupons
                    (code, description, discount_type, discount_value,
                     min_order_amount, max_discount,
                     usage_limit, per_user_limit,
                     starts_at, expires_at, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $code,
                    $description ?: null,
                    $discount_type,
                    $discount_value,
                    $min_order > 0 ? $min_order : null,
                    $max_discount > 0 ? $max_discount : null,
                    $usage_limit > 0 ? $usage_limit : null,
                    $per_user_limit > 0 ? $per_user_limit : null,
                    $starts_at ?: null,
                    $expires_at ?: null,
                    $status,
                ]);

                if (function_exists('logActivity')) {
                    logActivity('add_coupon', "Created coupon {$code}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                }

                $message = "Coupon '{$code}' created successfully.";
                $messageType = 'success';
                break;

            // ============================================
            // UPDATE COUPON
            // ============================================
            case 'update':
                $id              = intval($_POST['id'] ?? 0);
                $code            = strtoupper(sanitize(trim($_POST['code'] ?? '')));
                $description     = sanitize($_POST['description'] ?? '');
                $discount_type   = sanitize($_POST['discount_type'] ?? 'percentage');
                $discount_value  = floatval($_POST['discount_value'] ?? 0);
                $min_order       = floatval($_POST['min_order_amount'] ?? 0);
                $max_discount    = floatval($_POST['max_discount'] ?? 0);
                $usage_limit     = intval($_POST['usage_limit'] ?? 0);
                $per_user_limit  = intval($_POST['per_user_limit'] ?? 1);
                $starts_at       = trim($_POST['starts_at'] ?? '');
                $expires_at      = trim($_POST['expires_at'] ?? '');
                $status          = sanitize($_POST['status'] ?? 'active');

                if (!$id || $code === '' || $discount_value <= 0) {
                    $message = 'Missing required fields.';
                    $messageType = 'error';
                    break;
                }

                // Check duplicate code (excluding self)
                $stmt = $pdo->prepare("SELECT id FROM coupons WHERE UPPER(code) = UPPER(?) AND id <> ? LIMIT 1");
                $stmt->execute([$code, $id]);
                if ($stmt->fetchColumn()) {
                    $message = 'Another coupon already uses that code.';
                    $messageType = 'error';
                    break;
                }

                $stmt = $pdo->prepare("
                    UPDATE coupons
                    SET code = ?,
                        description = ?,
                        discount_type = ?,
                        discount_value = ?,
                        min_order_amount = ?,
                        max_discount = ?,
                        usage_limit = ?,
                        per_user_limit = ?,
                        starts_at = ?,
                        expires_at = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $code,
                    $description ?: null,
                    $discount_type,
                    $discount_value,
                    $min_order > 0 ? $min_order : null,
                    $max_discount > 0 ? $max_discount : null,
                    $usage_limit > 0 ? $usage_limit : null,
                    $per_user_limit > 0 ? $per_user_limit : null,
                    $starts_at ?: null,
                    $expires_at ?: null,
                    $status,
                    $id,
                ]);

                if (function_exists('logActivity')) {
                    logActivity('update_coupon', "Updated coupon {$code} (ID {$id})", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                }

                $message = "Coupon '{$code}' updated successfully.";
                $messageType = 'success';
                break;

            // ============================================
            // TOGGLE STATUS
            // ============================================
            case 'toggle_status':
                $id = intval($_POST['id'] ?? 0);
                if ($id) {
                    $pdo->prepare("
                        UPDATE coupons
                        SET status = CASE
                            WHEN status = 'active' THEN 'inactive'
                            ELSE 'active'
                        END
                        WHERE id = ?
                    ")->execute([$id]);

                    if (function_exists('logActivity')) {
                        logActivity('toggle_coupon', "Toggled coupon ID {$id} status", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }

                    $message = 'Coupon status toggled.';
                    $messageType = 'success';
                }
                break;

            // ============================================
            // DELETE COUPON
            // ============================================
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if ($id) {
                    // Check if it's been used
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ?");
                    $stmt->execute([$id]);
                    $usages = (int)$stmt->fetchColumn();

                    if ($usages > 0) {
                        $message = "Cannot delete — this coupon has been used {$usages} time(s). Deactivate it instead.";
                        $messageType = 'error';
                        break;
                    }

                    $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);

                    if (function_exists('logActivity')) {
                        logActivity('delete_coupon', "Deleted coupon ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }

                    $message = 'Coupon deleted.';
                    $messageType = 'success';
                }
                break;
        }
    } catch (PDOException $e) {
        error_log('Coupon action error: ' . $e->getMessage());
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ============================================
// FETCH COUPONS + USAGE STATS
// ============================================
$coupons = [];
try {
    $stmt = $pdo->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM coupon_usages cu WHERE cu.coupon_id = c.id) AS usage_count,
               (SELECT COALESCE(SUM(cu.discount_amount), 0) FROM coupon_usages cu WHERE cu.coupon_id = c.id) AS total_discount
        FROM coupons c
        ORDER BY c.created_at DESC
    ");
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch coupons error: ' . $e->getMessage());
    $coupons = [];
}

// ============================================
// STATS
// ============================================
$total_coupons    = count($coupons);
$active_coupons   = 0;
$inactive_coupons = 0;
$total_redemptions = 0;
$total_discount_given = 0.0;

foreach ($coupons as $c) {
    if (($c['status'] ?? '') === 'active') $active_coupons++;
    else                                    $inactive_coupons++;

    $total_redemptions    += (int)($c['usage_count'] ?? 0);
    $total_discount_given += (float)($c['total_discount'] ?? 0);
}

$page_title = 'Coupons';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            display: inline-block;
            text-transform: capitalize;
        }
        .status-active   { background: #28a745; }
        .status-inactive { background: #6c757d; }
        .status-expired  { background: #dc3545; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat-card {
            background: #fff;
            padding: 14px 18px;
            border-radius: 10px;
            border-left: 4px solid #05573c;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .stat-card .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #222;
            line-height: 1.1;
        }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.info    { border-left-color: #17a2b8; }
        .stat-card.warning { border-left-color: #fd7e14; }
        .stat-card.danger  { border-left-color: #dc3545; }

        .toolbar-card {
            background: #fff;
            border-radius: 10px;
            padding: 14px 18px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .toolbar-search {
            flex: 1;
            min-width: 220px;
            max-width: 380px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fafafa;
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .toolbar-search input {
            border: none;
            background: transparent;
            padding: 8px 0;
            outline: none;
            width: 100%;
            font-size: 14px;
        }

        .coupon-code {
            font-family: 'SF Mono', 'Courier New', monospace;
            background: #f0faf5;
            color: #05573c;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            border: 1px dashed #05573c;
            display: inline-block;
        }

        .value-cell {
            font-weight: 700;
            color: #05573c;
            font-size: 14px;
        }

        .date-cell {
            font-size: 12px;
            color: #666;
            white-space: nowrap;
        }

        .table-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .action-buttons form { display: inline; }
        .btn-sm {
            background: #f0f0f0;
            color: #333;
            border: none;
            padding: 5px 9px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn-sm:hover { background: #e0e0e0; }
        .btn-sm.success { background: #28a745; color: #fff; }
        .btn-sm.success:hover { background: #218838; }
        .btn-sm.danger  { background: #dc3545; color: #fff; }
        .btn-sm.danger:hover { background: #c82333; }
        .btn-sm.info    { background: #17a2b8; color: #fff; }
        .btn-sm.info:hover { background: #138496; }
        .btn-sm.warning { background: #fd7e14; color: #fff; }
        .btn-sm.warning:hover { background: #e06900; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #555;
            margin-bottom: 6px;
        }
        .form-group label .required { color: #dc3545; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #05573c;
            box-shadow: 0 0 0 3px rgba(5,87,60,0.1);
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            color: #888;
            font-size: 11px;
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
                        <i class="fas fa-tag"></i> Coupons
                    </h1>
                    <p style="margin:4px 0 0; color:#888; font-size:13px;">
                        Create and manage discount codes.
                    </p>
                </div>
                <button class="btn-primary" onclick="openModal('addCouponModal')">
                    <i class="fas fa-plus"></i> New Coupon
                </button>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent" style="padding:12px 18px; border-radius:8px; margin-bottom:16px; background:<?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color:<?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>;">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- STATS -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-label">Total Coupons</div>
                    <div class="stat-value"><?php echo $total_coupons; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Active</div>
                    <div class="stat-value"><?php echo $active_coupons; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">Redemptions</div>
                    <div class="stat-value"><?php echo number_format($total_redemptions); ?></div>
                </div>
                <div class="stat-card info">
                    <div class="stat-label">Total Discount Given</div>
                    <div class="stat-value">Ksh <?php echo number_format($total_discount_given, 0); ?></div>
                </div>
            </div>

            <!-- TOOLBAR -->
            <div class="toolbar-card">
                <div class="toolbar-search">
                    <i class="fas fa-search" style="color:#888;"></i>
                    <input type="text" id="searchCoupons" placeholder="Search by code or description..." oninput="applyFilters()">
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <select id="statusFilter" onchange="applyFilters()" style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; font-size:13px;">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- TABLE -->
            <div class="table-card">
                <?php if (count($coupons) > 0): ?>
                    <div style="overflow-x:auto;">
                        <table class="admin-table" id="couponsTable">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Discount</th>
                                    <th>Min Order</th>
                                    <th>Usage</th>
                                    <th>Validity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coupons as $c): ?>
                                    <?php
                                        $isExpired = !empty($c['expires_at']) && strtotime($c['expires_at']) < time();
                                        $statusVal = $isExpired ? 'expired' : ($c['status'] ?? 'inactive');
                                        $usageCount = (int)($c['usage_count'] ?? 0);
                                        $usageLimit = $c['usage_limit'] ? (int)$c['usage_limit'] : null;

                                        $discountStr = $c['discount_type'] === 'percentage'
                                            ? $c['discount_value'] . '%'
                                            : 'Ksh ' . number_format((float)$c['discount_value'], 0);

                                        if ($c['discount_type'] === 'percentage' && !empty($c['max_discount'])) {
                                            $discountStr .= ' (max Ksh ' . number_format((float)$c['max_discount'], 0) . ')';
                                        }
                                    ?>
                                    <tr data-status="<?php echo htmlspecialchars($statusVal); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower(($c['code'] ?? '') . ' ' . ($c['description'] ?? ''))); ?>">
                                        <td>
                                            <span class="coupon-code"><?php echo htmlspecialchars($c['code']); ?></span>
                                            <?php if (!empty($c['description'])): ?>
                                                <div style="font-size:11px; color:#888; margin-top:4px;">
                                                    <?php echo htmlspecialchars($c['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="value-cell"><?php echo htmlspecialchars($discountStr); ?></td>
                                        <td>
                                            <?php echo !empty($c['min_order_amount'])
                                                ? 'Ksh ' . number_format((float)$c['min_order_amount'], 0)
                                                : '—'; ?>
                                        </td>
                                        <td>
                                            <?php if ($usageLimit): ?>
                                                <strong><?php echo $usageCount; ?></strong> / <?php echo $usageLimit; ?>
                                            <?php else: ?>
                                                <strong><?php echo $usageCount; ?></strong> <span style="color:#888;">/ ∞</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="date-cell">
                                            <?php if (!empty($c['expires_at'])): ?>
                                                Expires<br><?php echo date('d M Y', strtotime($c['expires_at'])); ?>
                                            <?php else: ?>
                                                Never expires
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($statusVal); ?>">
                                                <?php echo htmlspecialchars($statusVal); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-sm info"
                                                        onclick='openEditCoupon(<?php echo json_encode([
                                                            "id" => (int)$c["id"],
                                                            "code" => $c["code"],
                                                            "description" => $c["description"] ?? "",
                                                            "discount_type" => $c["discount_type"],
                                                            "discount_value" => $c["discount_value"],
                                                            "min_order_amount" => $c["min_order_amount"] ?? "",
                                                            "max_discount" => $c["max_discount"] ?? "",
                                                            "usage_limit" => $c["usage_limit"] ?? "",
                                                            "per_user_limit" => $c["per_user_limit"] ?? "",
                                                            "starts_at" => $c["starts_at"] ?? "",
                                                            "expires_at" => $c["expires_at"] ?? "",
                                                            "status" => $c["status"] ?? "active",
                                                        ]); ?>)'
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                                    <button type="submit" class="btn-sm warning" title="Toggle status">
                                                        <i class="fas fa-toggle-on"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this coupon? This cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                                    <button type="submit" class="btn-sm danger" title="Delete">
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
                    <p style="text-align:center; padding:60px 20px; color:#888;">
                        <i class="fas fa-tag" style="font-size:48px; display:block; margin-bottom:10px; opacity:0.3;"></i>
                        No coupons yet. Click "New Coupon" to create one.
                    </p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- ============================================
         ADD COUPON MODAL
         ============================================ -->
    <div id="addCouponModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> New Coupon</h2>
                <span class="close" onclick="closeModal('addCouponModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="form-row">
                    <div class="form-group">
                        <label>Code <span class="required">*</span></label>
                        <input type="text" name="code" required placeholder="SAVE10" maxlength="50" style="text-transform:uppercase;">
                        <small>Customer enters this at checkout (case-insensitive).</small>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="e.g. 10% off your first order" maxlength="255">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Discount Type <span class="required">*</span></label>
                        <select name="discount_type" required>
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount (Ksh)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Discount Value <span class="required">*</span></label>
                        <input type="number" name="discount_value" required step="0.01" min="0.01" placeholder="10">
                        <small>Percentage (e.g. 10) or fixed amount (e.g. 200).</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Order (Ksh)</label>
                        <input type="number" name="min_order_amount" step="0.01" min="0" placeholder="500">
                        <small>Leave empty for no minimum.</small>
                    </div>
                    <div class="form-group">
                        <label>Max Discount (Ksh)</label>
                        <input type="number" name="max_discount" step="0.01" min="0" placeholder="1000">
                        <small>For % discounts — cap the amount.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Total Usage Limit</label>
                        <input type="number" name="usage_limit" min="0" placeholder="100">
                        <small>Leave empty for unlimited.</small>
                    </div>
                    <div class="form-group">
                        <label>Per-User Limit</label>
                        <input type="number" name="per_user_limit" min="1" value="1">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Starts At</label>
                        <input type="datetime-local" name="starts_at">
                        <small>Leave empty to start immediately.</small>
                    </div>
                    <div class="form-group">
                        <label>Expires At</label>
                        <input type="datetime-local" name="expires_at">
                        <small>Leave empty for no expiry.</small>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Create Coupon
                </button>
            </form>
        </div>
    </div>

    <!-- ============================================
         EDIT COUPON MODAL
         ============================================ -->
    <div id="editCouponModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Coupon</h2>
                <span class="close" onclick="closeModal('editCouponModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editCouponId">

                <div class="form-row">
                    <div class="form-group">
                        <label>Code <span class="required">*</span></label>
                        <input type="text" name="code" id="editCouponCode" required maxlength="50" style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="editCouponStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="editCouponDescription" maxlength="255">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Discount Type <span class="required">*</span></label>
                        <select name="discount_type" id="editCouponType" required>
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount (Ksh)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Discount Value <span class="required">*</span></label>
                        <input type="number" name="discount_value" id="editCouponValue" required step="0.01" min="0.01">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Order (Ksh)</label>
                        <input type="number" name="min_order_amount" id="editCouponMin" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Max Discount (Ksh)</label>
                        <input type="number" name="max_discount" id="editCouponMaxDiscount" step="0.01" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Total Usage Limit</label>
                        <input type="number" name="usage_limit" id="editCouponUsageLimit" min="0">
                    </div>
                    <div class="form-group">
                        <label>Per-User Limit</label>
                        <input type="number" name="per_user_limit" id="editCouponPerUser" min="1">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Starts At</label>
                        <input type="datetime-local" name="starts_at" id="editCouponStarts">
                    </div>
                    <div class="form-group">
                        <label>Expires At</label>
                        <input type="datetime-local" name="expires_at" id="editCouponExpires">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Update Coupon
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

        function toDatetimeLocal(str) {
            if (!str) return '';
            // Convert "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DDTHH:MM"
            return String(str).replace(' ', 'T').slice(0, 16);
        }

        function openEditCoupon(data) {
            document.getElementById('editCouponId').value             = data.id;
            document.getElementById('editCouponCode').value           = data.code || '';
            document.getElementById('editCouponDescription').value    = data.description || '';
            document.getElementById('editCouponType').value           = data.discount_type || 'percentage';
            document.getElementById('editCouponValue').value          = data.discount_value || 0;
            document.getElementById('editCouponMin').value            = data.min_order_amount || '';
            document.getElementById('editCouponMaxDiscount').value    = data.max_discount || '';
            document.getElementById('editCouponUsageLimit').value     = data.usage_limit || '';
            document.getElementById('editCouponPerUser').value        = data.per_user_limit || 1;
            document.getElementById('editCouponStarts').value         = toDatetimeLocal(data.starts_at);
            document.getElementById('editCouponExpires').value        = toDatetimeLocal(data.expires_at);
            document.getElementById('editCouponStatus').value         = data.status || 'active';
            openModal('editCouponModal');
        }

        function applyFilters() {
            const search = (document.getElementById('searchCoupons').value || '').toLowerCase().trim();
            const status = (document.getElementById('statusFilter').value || '').toLowerCase();
            const rows   = document.querySelectorAll('#couponsTable tbody tr');

            rows.forEach(row => {
                const rowSearch = row.dataset.search || '';
                const rowStatus = (row.dataset.status || '').toLowerCase();
                let show = true;
                if (search && rowSearch.indexOf(search) === -1) show = false;
                if (status && rowStatus !== status) show = false;
                row.style.display = show ? '' : 'none';
            });
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        };

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(function(m) { m.style.display = 'none'; });
                document.body.style.overflow = 'auto';
            }
        });

        // Auto-hide alerts
        setTimeout(function () {
            document.querySelectorAll('.alert-persistent').forEach(function (alert) {
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function () {
                    alert.style.opacity = '0';
                    setTimeout(function () { alert.remove(); }, 500);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>
