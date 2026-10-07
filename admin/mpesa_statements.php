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
// AJAX: EXPORT CSV
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $stmt = $pdo->query("
            SELECT
                o.order_number,
                o.payment_method,
                o.mpesa_receipt,
                o.mpesa_phone,
                o.paybill_number,
                o.paybill_account,
                o.total,
                o.shipping_fee,
                o.payment_status,
                o.status,
                o.paid_at,
                o.created_at,
                u.name AS customer_name,
                u.email AS customer_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.payment_method IN ('mpesa','paybill')
            ORDER BY o.created_at DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="mpesa_statements_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Order #', 'Method', 'M-Pesa Receipt', 'Phone', 'Paybill #', 'Paybill Acct', 'Total (Ksh)', 'Shipping', 'Payment Status', 'Order Status', 'Paid At', 'Created At', 'Customer', 'Email']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['order_number'], $r['payment_method'],
                $r['mpesa_receipt'], $r['mpesa_phone'],
                $r['paybill_number'], $r['paybill_account'],
                $r['total'], $r['shipping_fee'], $r['payment_status'], $r['status'],
                $r['paid_at'], $r['created_at'], $r['customer_name'], $r['customer_email']
            ]);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) {
        error_log('M-Pesa CSV export error: ' . $e->getMessage());
    }
}

// ============================================
// HANDLE MANUAL ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'mark_paid':
                $order_id = intval($_POST['id'] ?? 0);
                $receipt  = sanitize($_POST['receipt'] ?? '');
                if ($order_id) {
                    $stmt = $pdo->prepare("
                        UPDATE orders
                        SET payment_status = 'paid',
                            mpesa_receipt = COALESCE(NULLIF(?, ''), mpesa_receipt),
                            payment_reference = COALESCE(NULLIF(?, ''), payment_reference),
                            paid_at = COALESCE(paid_at, NOW()),
                            status = 'processing',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$receipt, $receipt, $order_id]);

                    if (function_exists('logActivity')) {
                        logActivity('mpesa_mark_paid', 'Marked order ID ' . $order_id . ' as paid', $_SESSION['user_id'], $_SESSION['user_name']);
                    }

                    $message = 'Order marked as paid.' . ($receipt ? ' Receipt: ' . htmlspecialchars($receipt) : '');
                    $messageType = 'success';
                }
                break;

            case 'mark_failed':
                $order_id = intval($_POST['id'] ?? 0);
                if ($order_id) {
                    $pdo->prepare("
                        UPDATE orders
                        SET payment_status = 'failed',
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$order_id]);

                    if (function_exists('logActivity')) {
                        logActivity('mpesa_mark_failed', 'Marked order ID ' . $order_id . ' as failed', $_SESSION['user_id'], $_SESSION['user_name']);
                    }

                    $message = 'Order marked as failed.';
                    $messageType = 'success';
                }
                break;

            case 'retry_stk':
                $order_id = intval($_POST['id'] ?? 0);
                if ($order_id) {
                    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                    $stmt->execute([$order_id]);
                    $order = $stmt->fetch();

                    if (!$order) {
                        $message = 'Order not found.';
                        $messageType = 'error';
                        break;
                    }

                    // Guard: only M-Pesa orders can be retried via STK
                    if (($order['payment_method'] ?? '') !== 'mpesa') {
                        $message = 'This order is not an M-Pesa (STK push) order. Retry is only available for M-Pesa payments.';
                        $messageType = 'error';
                        break;
                    }

                    if (empty($order['mpesa_phone'])) {
                        $message = 'No M-Pesa phone on this order.';
                        $messageType = 'error';
                        break;
                    }

                    require_once '../includes/mpesa_service.php';
                    $mpesa = new MpesaService();
                    $normalized = MpesaService::normalizePhone($order['mpesa_phone']);

                    $push = $mpesa->stkPush(
                        $normalized,
                        (int) ceil((float)$order['total']),
                        'ORD' . $order['id'],
                        'WittyMart Retry'
                    );

                    if ($push && !empty($push['CheckoutRequestID'])) {
                        $pdo->prepare("
                            UPDATE orders
                            SET mpesa_checkout_id = ?,
                                payment_reference = ?,
                                payment_status = 'awaiting_payment',
                                updated_at = NOW()
                            WHERE id = ?
                        ")->execute([$push['CheckoutRequestID'], $push['CheckoutRequestID'], $order_id]);

                        $message = 'STK Push re-sent to ' . htmlspecialchars($order['mpesa_phone']);
                        $messageType = 'success';
                    } else {
                        $message = 'STK Push failed. Check logs.';
                        $messageType = 'error';
                    }
                }
                break;
        }
    } catch (PDOException $e) {
        error_log('M-Pesa action error: ' . $e->getMessage());
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    } catch (Throwable $e) {
        error_log('M-Pesa action exception: ' . $e->getMessage());
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ============================================
// FETCH ALL M-PESA / PAYBILL ORDERS
// ============================================
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            o.*,
            u.name AS customer_name,
            u.email AS customer_email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.payment_method IN ('mpesa','paybill')
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('M-Pesa orders fetch error: ' . $e->getMessage());
    $orders = [];
}

// ============================================
// STATS
// ============================================
$total_orders      = count($orders);
$paid_orders       = 0;
$awaiting_orders   = 0;
$failed_orders     = 0;
$paid_total        = 0.0;
$awaiting_total    = 0.0;
$failed_total      = 0.0;
$today_total       = 0.0;
$today_count       = 0;
$today             = date('Y-m-d');

foreach ($orders as $o) {
    $ps = $o['payment_status'] ?? 'pending';
    $amt = (float)($o['total'] ?? 0);

    if ($ps === 'paid') {
        $paid_orders++;
        $paid_total += $amt;
        if (!empty($o['paid_at']) && substr($o['paid_at'], 0, 10) === $today) {
            $today_total += $amt;
            $today_count++;
        }
    } elseif ($ps === 'failed') {
        $failed_orders++;
        $failed_total += $amt;
    } else {
        $awaiting_orders++;
        $awaiting_total += $amt;
    }
}

$status_counts = [
    'all'      => $total_orders,
    'paid'     => $paid_orders,
    'awaiting' => $awaiting_orders,
    'failed'   => $failed_orders,
];

$page_title = 'M-Pesa Statements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Statements - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
        }
        .status-paid       { background-color: #28a745; }
        .status-awaiting   { background-color: #17a2b8; }
        .status-pending    { background-color: #6c757d; }
        .status-failed     { background-color: #dc3545; }
        .status-processing { background-color: #0d6efd; }

        .method-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-top: 4px;
        }
        .method-mpesa   { background: #d1f2eb; color: #0e6251; }
        .method-paybill { background: #fff3cd; color: #856404; }

        .receipt-code {
            font-family: 'SF Mono', 'Courier New', monospace;
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #333;
            letter-spacing: 0.5px;
        }
        .receipt-code.empty {
            background: #fafafa;
            color: #aaa;
            font-style: italic;
            font-family: inherit;
            letter-spacing: normal;
        }

        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 14px;
            background: #fafafa;
            border-bottom: 1px solid #eee;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 240px;
            max-width: 420px;
        }
        .search-box:focus-within {
            border-color: #05573c;
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }
        .search-box i { color: #888; font-size: 14px; }
        .search-box input {
            border: none;
            background: transparent;
            padding: 8px 0;
            outline: none;
            color: #333;
            width: 100%;
            font-size: 14px;
        }
        .search-box input::placeholder { color: #aaa; }
        .clear-search-btn {
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .clear-search-btn:hover { background: rgba(0,0,0,0.05); color: #333; }

        .filter-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-controls select,
        .filter-controls input[type="date"] {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: #fff;
            color: #333;
            font-size: 13px;
            cursor: pointer;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }
        .filter-controls select:focus,
        .filter-controls input[type="date"]:focus {
            outline: none;
            border-color: #05573c;
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }

        .category-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 14px;
            background: #fff;
            border-bottom: 1px solid #eee;
        }
        .category-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            background: #f0f0f0;
            color: #333;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s ease;
            user-select: none;
        }
        .category-chip:hover { background: #e8f5f0; color: #05573c; }
        .category-chip.active { background: #05573c; color: #fff; border-color: #05573c; }
        .category-chip .chip-count {
            background: rgba(0,0,0,0.1);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            min-width: 22px;
            text-align: center;
        }
        .category-chip.active .chip-count {
            background: rgba(255,255,255,0.25);
            color: #fff;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            padding: 14px;
            background: #fff;
            border-bottom: 1px solid #eee;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #05573c;
        }
        .stat-card .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 4px;
        }
        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
        }
        .stat-card .stat-sub {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
        }
        .stat-card.warning { border-left-color: #fd7e14; }
        .stat-card.danger  { border-left-color: #dc3545; }
        .stat-card.info    { border-left-color: #17a2b8; }
        .stat-card.success { border-left-color: #28a745; }

        .results-info {
            padding: 10px 14px;
            background: #fafafa;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .results-info strong { color: #05573c; }

        .admin-table th { font-size: 12px; }
        .admin-table td { font-size: 13px; vertical-align: middle; }
        .amount-cell { font-weight: 700; color: #05573c; white-space: nowrap; }
        .phone-cell { font-family: 'SF Mono', 'Courier New', monospace; font-size: 12px; }
        .date-cell { font-size: 12px; color: #666; white-space: nowrap; }

        .order-link {
            color: #05573c;
            font-weight: 600;
            text-decoration: none;
        }
        .order-link:hover { text-decoration: underline; }

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
        .btn-sm.danger:hover  { background: #c82333; }
        .btn-sm.info    { background: #17a2b8; color: #fff; }
        .btn-sm.info:hover    { background: #138496; }

        .no-results-message {
            display: none;
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        .no-results-message i {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        .no-results-message h3 { margin: 0 0 8px; color: #555; }

        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #05573c;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .export-btn:hover { background: #03402c; }

        @media (max-width: 768px) {
            .table-toolbar { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            .filter-controls { width: 100%; }
            .filter-controls select,
            .filter-controls input[type="date"] { flex: 1; min-width: 0; }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>

        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <span class="badge badge-info" style="padding: 8px 16px; background: #e8f5f0; color: #05573c; border-radius: 20px; font-weight: 600;">
                    <i class="fas fa-mobile-alt"></i> <?php echo $total_orders; ?> M-Pesa & Paybill transactions
                </span>
                <a href="?export=csv" class="export-btn">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-card" style="padding:0; overflow:hidden;">

                <!-- TOOLBAR -->
                <div class="table-toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchOrders"
                               placeholder="Search by order #, receipt, phone, paybill acct, customer..."
                               oninput="applyFilters()">
                        <button class="clear-search-btn" id="clearSearchBtn" onclick="clearSearch()" style="display:none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="filter-controls">
                        <input type="date" id="dateFrom" onchange="applyFilters()" title="From date">
                        <input type="date" id="dateTo" onchange="applyFilters()" title="To date">
                        <select id="methodFilter" onchange="applyFilters()">
                            <option value="">All Methods</option>
                            <option value="mpesa">M-Pesa (STK)</option>
                            <option value="paybill">Paybill</option>
                        </select>
                        <select id="paymentStatusFilter" onchange="applyFilters()">
                            <option value="">All Payment Statuses</option>
                            <option value="paid">Paid</option>
                            <option value="awaiting_payment">Awaiting Payment</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                <!-- STATUS CHIPS -->
                <div class="category-chips">
                    <div class="category-chip active" data-status="" onclick="setStatusFilter(this, '')">
                        <i class="fas fa-th"></i> All
                        <span class="chip-count"><?php echo $status_counts['all']; ?></span>
                    </div>
                    <div class="category-chip" data-status="paid" onclick="setStatusFilter(this, 'paid')">
                        <i class="fas fa-check-circle"></i> Paid
                        <span class="chip-count"><?php echo $status_counts['paid']; ?></span>
                    </div>
                    <div class="category-chip" data-status="awaiting_payment" onclick="setStatusFilter(this, 'awaiting_payment')">
                        <i class="fas fa-clock"></i> Awaiting
                        <span class="chip-count"><?php echo $status_counts['awaiting']; ?></span>
                    </div>
                    <div class="category-chip" data-status="failed" onclick="setStatusFilter(this, 'failed')">
                        <i class="fas fa-times-circle"></i> Failed
                        <span class="chip-count"><?php echo $status_counts['failed']; ?></span>
                    </div>
                </div>

                <!-- STATS -->
                <div class="stats-row">
                    <div class="stat-card success">
                        <div class="stat-label">Total Collected</div>
                        <div class="stat-value">Ksh <?php echo number_format($paid_total, 0); ?></div>
                        <div class="stat-sub"><?php echo $paid_orders; ?> paid orders</div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-label">Awaiting Payment</div>
                        <div class="stat-value">Ksh <?php echo number_format($awaiting_total, 0); ?></div>
                        <div class="stat-sub"><?php echo $awaiting_orders; ?> orders</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-label">Failed</div>
                        <div class="stat-value">Ksh <?php echo number_format($failed_total, 0); ?></div>
                        <div class="stat-sub"><?php echo $failed_orders; ?> orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Today</div>
                        <div class="stat-value">Ksh <?php echo number_format($today_total, 0); ?></div>
                        <div class="stat-sub"><?php echo $today_count; ?> paid today</div>
                    </div>
                </div>

                <!-- RESULTS INFO -->
                <div class="results-info">
                    <span id="resultsCount">
                        Showing <strong><?php echo $total_orders; ?></strong> of <strong><?php echo $total_orders; ?></strong> transactions
                    </span>
                    <span id="activeFilterLabel" style="color:#05573c; font-weight:600;"></span>
                </div>

                <!-- TABLE -->
                <div class="card-body" style="padding:0;">
                    <?php if (count($orders) > 0): ?>
                        <div style="overflow-x:auto;">
                            <table class="admin-table" id="ordersTable">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Phone / Acct</th>
                                        <th>Receipt / Ref</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Order Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $o): ?>
                                        <?php
                                            $pm        = $o['payment_method'] ?? '';
                                            $ps        = $o['payment_status'] ?? 'pending';
                                            $statusCls = 'status-' . str_replace('_', '-', strtolower($ps));
                                            if ($ps === 'awaiting_payment') $statusCls = 'status-awaiting';

                                            $receipt   = $o['mpesa_receipt'] ?: ($o['payment_reference'] ?: '');
                                            $hasRec    = !empty($receipt);

                                            $dateForFilter = $o['created_at'] ?? '';
                                            $searchText = strtolower(
                                                ($o['order_number'] ?? '') . ' ' .
                                                ($o['mpesa_phone'] ?? '') . ' ' .
                                                ($o['paybill_number'] ?? '') . ' ' .
                                                ($o['paybill_account'] ?? '') . ' ' .
                                                ($receipt) . ' ' .
                                                ($o['customer_name'] ?? '') . ' ' .
                                                ($o['customer_email'] ?? '')
                                            );
                                        ?>
                                        <tr data-status="<?php echo htmlspecialchars($ps); ?>"
                                            data-method="<?php echo htmlspecialchars($pm); ?>"
                                            data-date="<?php echo htmlspecialchars(substr($dateForFilter, 0, 10)); ?>"
                                            data-search="<?php echo htmlspecialchars($searchText); ?>">
                                            <td>
                                                <a href="orders.php?view=<?php echo (int)$o['id']; ?>"
                                                   target="_blank"
                                                   rel="noopener"
                                                   class="order-link">
                                                    <?php echo htmlspecialchars($o['order_number']); ?>
                                                </a>
                                                <?php if ($pm === 'paybill'): ?>
                                                    <br><span class="method-badge method-paybill">Paybill</span>
                                                <?php else: ?>
                                                    <br><span class="method-badge method-mpesa">M-Pesa</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($o['customer_name'] ?? 'Guest'); ?>
                                                <?php if (!empty($o['customer_email'])): ?>
                                                    <br><small style="color:#888;"><?php echo htmlspecialchars($o['customer_email']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="phone-cell">
                                                <?php if ($pm === 'paybill'): ?>
                                                    <?php if (!empty($o['paybill_account'])): ?>
                                                        Acct: <?php echo htmlspecialchars($o['paybill_account']); ?>
                                                    <?php else: ?>
                                                        <span style="color:#aaa;">—</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($o['mpesa_phone'] ?? '—'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($hasRec): ?>
                                                    <span class="receipt-code"><?php echo htmlspecialchars($receipt); ?></span>
                                                <?php else: ?>
                                                    <span class="receipt-code empty">No receipt yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="amount-cell">
                                                Ksh <?php echo number_format((float)$o['total'], 0); ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $statusCls; ?>">
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', $ps)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars(strtolower($o['status'] ?? 'pending')); ?>">
                                                    <?php echo htmlspecialchars($o['status'] ?? 'pending'); ?>
                                                </span>
                                            </td>
                                            <td class="date-cell">
                                                <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($o['created_at']))); ?>
                                                <?php if (!empty($o['paid_at'])): ?>
                                                    <br><small style="color:#28a745;">Paid: <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($o['paid_at']))); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($ps === 'paid'): ?>
                                                        <button class="btn-sm info"
                                                                title="View order in admin"
                                                                onclick="window.open('orders.php?view=<?php echo (int)$o['id']; ?>','_blank')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    <?php elseif ($ps === 'failed' && $pm === 'mpesa'): ?>
                                                        <!-- Retry STK only for M-Pesa -->
                                                        <form method="POST" onsubmit="return confirm('Re-send M-Pesa STK push to this customer?')">
                                                            <input type="hidden" name="action" value="retry_stk">
                                                            <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">
                                                            <button type="submit" class="btn-sm info" title="Retry STK">
                                                                <i class="fas fa-redo"></i> Retry
                                                            </button>
                                                        </form>
                                                        <button class="btn-sm success"
                                                                onclick="promptMarkPaid(<?php echo (int)$o['id']; ?>, '<?php echo htmlspecialchars(addslashes($o['order_number'])); ?>')"
                                                                title="Mark as paid">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php elseif ($ps === 'failed' && $pm === 'paybill'): ?>
                                                        <!-- Paybill failed — only manual mark paid -->
                                                        <button class="btn-sm success"
                                                                onclick="promptMarkPaid(<?php echo (int)$o['id']; ?>, '<?php echo htmlspecialchars(addslashes($o['order_number'])); ?>')"
                                                                title="Mark as paid">
                                                            <i class="fas fa-check"></i> Paid
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Awaiting payment: mark paid or mark failed -->
                                                        <button class="btn-sm success"
                                                                onclick="promptMarkPaid(<?php echo (int)$o['id']; ?>, '<?php echo htmlspecialchars(addslashes($o['order_number'])); ?>')"
                                                                title="Mark as paid">
                                                            <i class="fas fa-check"></i> Paid
                                                        </button>
                                                        <form method="POST" onsubmit="return confirm('Mark this order as failed?')" style="display:inline;">
                                                            <input type="hidden" name="action" value="mark_failed">
                                                            <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">
                                                            <button type="submit" class="btn-sm danger" title="Mark as failed">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="no-results-message" id="noResultsMsg">
                            <i class="fas fa-search"></i>
                            <h3>No transactions found</h3>
                            <p>Try adjusting your search or filters.</p>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center" style="padding: 60px 20px;">
                            <i class="fas fa-mobile-alt" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                            No M-Pesa / Paybill transactions yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- MARK AS PAID MODAL -->
    <div id="markPaidModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
        <div class="modal-content" style="max-width:440px; background:#fff; border-radius:12px; padding:26px; position:relative;">
            <span onclick="closeMarkPaidModal()" style="position:absolute; top:14px; right:16px; font-size:22px; cursor:pointer; color:#888;">&times;</span>
            <h2 style="margin:0 0 8px; font-size:20px; color:#222;"><i class="fas fa-check-circle" style="color:#28a745;"></i> Mark as Paid</h2>
            <p style="color:#666; font-size:14px; margin-bottom:16px;">Enter the M-Pesa receipt number to record this payment manually.</p>
            <form method="POST" id="markPaidForm">
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="id" id="markPaidOrderId" value="">

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block; font-weight:600; font-size:13px; color:#555; margin-bottom:6px;">M-Pesa Receipt Number</label>
                    <input type="text" name="receipt" id="markPaidReceipt"
                           placeholder="e.g., SLK7X8Y9Z0"
                           style="width:100%; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px;"
                           required>
                </div>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" onclick="closeMarkPaidModal()"
                            style="padding:10px 20px; background:#f0f0f0; border:none; border-radius:6px; cursor:pointer; font-weight:600;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="padding:10px 22px; background:#28a745; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">
                        <i class="fas fa-check"></i> Mark Paid
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var activeStatus = '';

        function setStatusFilter(el, status) {
            document.querySelectorAll('.category-chip').forEach(function (chip) {
                chip.classList.remove('active');
            });
            el.classList.add('active');
            activeStatus = status;
            applyFilters();
        }

        function applyFilters() {
            var table = document.getElementById('ordersTable');
            if (!table) return;

            var rows         = table.querySelectorAll('tbody tr');
            var searchVal    = (document.getElementById('searchOrders').value || '').toLowerCase().trim();
            var statusVal    = document.getElementById('paymentStatusFilter').value;
            var methodVal    = document.getElementById('methodFilter').value;
            var dateFrom     = document.getElementById('dateFrom').value;
            var dateTo       = document.getElementById('dateTo').value;

            var visibleCount = 0;

            rows.forEach(function (row) {
                var rowStatus = (row.dataset.status || '').toLowerCase();
                var rowMethod = (row.dataset.method || '').toLowerCase();
                var rowDate   = row.dataset.date || '';
                var rowSearch = row.dataset.search || '';

                var show = true;

                if (activeStatus && rowStatus !== activeStatus.toLowerCase()) show = false;
                if (show && statusVal && rowStatus !== statusVal.toLowerCase()) show = false;
                if (show && methodVal && rowMethod !== methodVal.toLowerCase()) show = false;
                if (show && dateFrom && rowDate && rowDate < dateFrom) show = false;
                if (show && dateTo   && rowDate && rowDate > dateTo)   show = false;
                if (show && searchVal && rowSearch.indexOf(searchVal) === -1) show = false;

                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            document.getElementById('resultsCount').innerHTML =
                'Showing <strong>' + visibleCount + '</strong> of <strong>' + rows.length + '</strong> transactions';

            var labels = [];
            if (activeStatus) labels.push('Status: ' + activeStatus);
            if (statusVal)    labels.push('Payment: ' + statusVal);
            if (methodVal)    labels.push('Method: ' + methodVal);
            if (dateFrom)     labels.push('From: ' + dateFrom);
            if (dateTo)       labels.push('To: ' + dateTo);
            if (searchVal)    labels.push('Search: "' + searchVal + '"');
            document.getElementById('activeFilterLabel').textContent = labels.length ? '(' + labels.join(' • ') + ')' : '';

            document.getElementById('clearSearchBtn').style.display = searchVal ? 'block' : 'none';

            var noMsg = document.getElementById('noResultsMsg');
            if (noMsg) {
                noMsg.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
            }
        }

        function clearSearch() {
            document.getElementById('searchOrders').value = '';
            applyFilters();
            document.getElementById('searchOrders').focus();
        }

        function promptMarkPaid(orderId, orderNumber) {
            document.getElementById('markPaidOrderId').value = orderId;
            document.getElementById('markPaidReceipt').value = '';
            document.getElementById('markPaidModal').style.display = 'flex';
            setTimeout(function () {
                document.getElementById('markPaidReceipt').focus();
            }, 100);
        }

        function closeMarkPaidModal() {
            document.getElementById('markPaidModal').style.display = 'none';
        }

        document.getElementById('markPaidModal').addEventListener('click', function (e) {
            if (e.target === this) closeMarkPaidModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMarkPaidModal();
        });

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
