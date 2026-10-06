<?php
// ============================================
// ADMIN — M-PESA STATEMENTS GENERATOR
// ============================================
require_once 'includes/config.php';

// ---------- ADMIN AUTH GUARD ----------
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: index.php');
    exit();
}

// ---------- FILTERS ----------
$from       = $_GET['from']    ?? date('Y-m-01');   
$to         = $_GET['to']      ?? date('Y-m-d');  
$status     = $_GET['status']  ?? 'all';           
$phone      = trim($_GET['phone'] ?? '');
$order_no   = trim($_GET['order'] ?? '');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

// Ensure $to is end-of-day so "today" includes all hours
$fromTs = $from . ' 00:00:00';
$toTs   = $to   . ' 23:59:59';

// ---------- BUILD QUERY ----------
$sql = "
    SELECT
        id,
        order_number,
        total,
        payment_method,
        payment_status,
        payment_reference,
        mpesa_checkout_id,
        mpesa_receipt,
        mpesa_phone,
        delivery_recipient,
        delivery_phone,
        created_at,
        paid_at,
        payment_failure_reason
    FROM orders
    WHERE payment_method IN ('mpesa','paybill')
      AND created_at BETWEEN ? AND ?
";
$params = [$fromTs, $toTs];

if ($status !== 'all') {
    $sql .= " AND payment_status = ? ";
    $params[] = $status;
}

if ($phone !== '') {
    $sql .= " AND (mpesa_phone ILIKE ? OR delivery_phone ILIKE ?) ";
    $params[] = '%' . $phone . '%';
    $params[] = '%' . $phone . '%';
}

if ($order_no !== '') {
    $sql .= " AND (order_number ILIKE ? OR mpesa_receipt ILIKE ? OR payment_reference ILIKE ?) ";
    $params[] = '%' . $order_no . '%';
    $params[] = '%' . $order_no . '%';
    $params[] = '%' . $order_no . '%';
}

$sql .= " ORDER BY created_at DESC ";

$transactions = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Mpesa statements fetch: ' . $e->getMessage());
}

// ---------- TOTALS ----------
$totalPaid     = 0;
$totalPending  = 0;
$totalFailed   = 0;
$countPaid     = 0;
$countPending  = 0;
$countFailed   = 0;

foreach ($transactions as $t) {
    $amt = (float)$t['total'];
    switch ($t['payment_status']) {
        case 'paid':
            $totalPaid    += $amt;
            $countPaid++;
            break;
        case 'awaiting_payment':
        case 'pending':
            $totalPending += $amt;
            $countPending++;
            break;
        case 'failed':
            $totalFailed  += $amt;
            $countFailed++;
            break;
    }
}

$netRevenue = $totalPaid; // change if you want to subtract failed/pending

// ---------- CSV EXPORT ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mpesa_statement_' . $from . '_to_' . $to . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // BOM for Excel UTF-8
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header rows
    fputcsv($out, ['WittyMart — M-Pesa Statement']);
    fputcsv($out, ['Period', $from . ' to ' . $to]);
    fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['Summary']);
    fputcsv($out, ['Paid',     'Ksh ' . number_format($totalPaid, 2),    $countPaid . ' transactions']);
    fputcsv($out, ['Pending',  'Ksh ' . number_format($totalPending, 2), $countPending . ' transactions']);
    fputcsv($out, ['Failed',   'Ksh ' . number_format($totalFailed, 2),  $countFailed . ' transactions']);
    fputcsv($out, []);
    fputcsv($out, ['Transactions']);
    fputcsv($out, [
        'Date',
        'Order #',
        'Customer',
        'Phone',
        'Amount (Ksh)',
        'Status',
        'M-Pesa Receipt',
        'Checkout Request ID',
        'Failure Reason'
    ]);

    foreach ($transactions as $t) {
        fputcsv($out, [
            date('Y-m-d H:i:s', strtotime($t['paid_at'] ?: $t['created_at'])),
            $t['order_number'],
            $t['delivery_recipient'] ?: '',
            $t['mpesa_phone'] ?: $t['delivery_phone'] ?: '',
            number_format((float)$t['total'], 2),
            $t['payment_status'],
            $t['mpesa_receipt'] ?: $t['payment_reference'] ?: '',
            $t['mpesa_checkout_id'] ?: '',
            $t['payment_failure_reason'] ?: '',
        ]);
    }

    fclose($out);
    exit();
}

$page_title = 'M-Pesa Statements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>M-Pesa Statements — WittyMart Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: -apple-system, 'Segoe UI', Roboto, sans-serif;
        background: #f3f4f6;
        color: #222;
        margin: 0;
        padding: 24px;
        line-height: 1.5;
    }
    .wrap { max-width: 1200px; margin: 0 auto; }

    h1 { margin: 0 0 6px; font-size: 22px; color: #05573c; }
    .sub { color: #6b7280; font-size: 13px; margin-bottom: 20px; }

    /* -------- Filter bar -------- */
    .filters {
        background: #fff;
        border-radius: 12px;
        padding: 16px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        align-items: end;
        margin-bottom: 20px;
    }
    .field label {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6b7280;
        font-weight: 700;
        margin-bottom: 5px;
    }
    .field input,
    .field select {
        width: 100%;
        padding: 9px 12px;
        border: 1.5px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        background: #fff;
    }
    .field input:focus,
    .field select:focus {
        outline: none;
        border-color: #05573c;
        box-shadow: 0 0 0 3px rgba(5,87,60,0.1);
    }
    .filter-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: none;
        font-family: inherit;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .btn-primary { background: #05573c; color: #fff; }
    .btn-primary:hover { background: #03402c; }
    .btn-outline {
        background: #fff;
        color: #374151;
        border: 1.5px solid #e5e7eb;
    }
    .btn-outline:hover { border-color: #05573c; color: #05573c; }
    .btn-ghost {
        background: transparent;
        color: #6b7280;
        padding: 10px 12px;
    }
    .btn-ghost:hover { color: #dc3545; }

    /* -------- Stat cards -------- */
    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .stat {
        background: #fff;
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border-left: 4px solid #05573c;
    }
    .stat.pending { border-left-color: #f59e0b; }
    .stat.failed  { border-left-color: #dc3545; }
    .stat.net     { border-left-color: #0a7a54; background: linear-gradient(135deg,#f0faf5 0%,#fff 100%); }
    .stat label {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: #6b7280;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .stat .value {
        font-size: 22px;
        font-weight: 800;
        color: #05573c;
        line-height: 1.2;
    }
    .stat.pending .value { color: #b45309; }
    .stat.failed  .value { color: #b91c1c; }
    .stat .count {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 4px;
    }

    /* -------- Table -------- */
    .table-wrap {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    thead th {
        background: #f9fafb;
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: #6b7280;
        font-weight: 700;
        padding: 12px 14px;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }
    tbody td {
        padding: 12px 14px;
        font-size: 13.5px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #fafbfc; }

    .mono { font-family: 'SF Mono', 'Courier New', monospace; font-size: 12.5px; }
    .amount { font-weight: 700; color: #05573c; white-space: nowrap; text-align: right; }
    .order-link { color: #05573c; font-weight: 600; text-decoration: none; }
    .order-link:hover { text-decoration: underline; }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .badge.paid              { background: #d4edda; color: #155724; }
    .badge.awaiting_payment,
    .badge.pending           { background: #fef3c7; color: #92400e; }
    .badge.failed            { background: #f8d7da; color: #721c24; }

    .empty {
        padding: 60px 20px;
        text-align: center;
        color: #6b7280;
    }
    .empty i {
        display: block;
        font-size: 48px;
        color: #d1d5db;
        margin-bottom: 12px;
    }
    .empty h3 { margin: 0 0 6px; color: #374151; }

    /* -------- Print styles -------- */
    @media print {
        body { background: #fff; padding: 0; }
        .filters, .filter-actions, .no-print { display: none !important; }
        .stat { box-shadow: none; border: 1px solid #e5e7eb; }
        .table-wrap { box-shadow: none; }
        thead th { background: #fff; }
        a { color: inherit; text-decoration: none; }
        .stat.net { background: #f0faf5 !important; }
    }
</style>
</head>
<body>
<div class="wrap">

    <h1><i class="fas fa-mobile-alt" style="color:#25A349;"></i> M-Pesa Statements</h1>
    <p class="sub">Filter, view, and export M-Pesa transactions for any date range.</p>

    <!-- =================== FILTERS =================== -->
    <form class="filters" method="GET" id="filterForm">
        <div class="field">
            <label>From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div class="field">
            <label>To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="all"              <?php echo $status==='all'?'selected':''; ?>>All</option>
                <option value="paid"             <?php echo $status==='paid'?'selected':''; ?>>Paid</option>
                <option value="awaiting_payment" <?php echo $status==='awaiting_payment'?'selected':''; ?>>Awaiting Payment</option>
                <option value="pending"          <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
                <option value="failed"           <?php echo $status==='failed'?'selected':''; ?>>Failed</option>
            </select>
        </div>
        <div class="field">
            <label>Phone</label>
            <input type="text" name="phone" placeholder="e.g. 2547..." value="<?php echo htmlspecialchars($phone); ?>">
        </div>
        <div class="field">
            <label>Order / Receipt</label>
            <input type="text" name="order" placeholder="Search..." value="<?php echo htmlspecialchars($order_no); ?>">
        </div>
        <div class="filter-actions" style="grid-column: 1 / -1; justify-content: flex-end;">
            <a href="mpesa_statements.php" class="btn btn-ghost" title="Reset filters">
                <i class="fas fa-times"></i> Reset
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply
            </button>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>"
               class="btn btn-outline">
                <i class="fas fa-file-csv"></i> CSV
            </a>
            <button type="button" class="btn btn-outline" onclick="window.print()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
        </div>
    </form>

    <!-- =================== STATS =================== -->
    <div class="stats">
        <div class="stat">
            <label>Total Received</label>
            <div class="value">Ksh <?php echo number_format($totalPaid, 0); ?></div>
            <div class="count"><?php echo $countPaid; ?> paid transaction<?php echo $countPaid==1?'':'s'; ?></div>
        </div>
        <div class="stat pending">
            <label>Pending</label>
            <div class="value">Ksh <?php echo number_format($totalPending, 0); ?></div>
            <div class="count"><?php echo $countPending; ?> awaiting payment</div>
        </div>
        <div class="stat failed">
            <label>Failed / Cancelled</label>
            <div class="value">Ksh <?php echo number_format($totalFailed, 0); ?></div>
            <div class="count"><?php echo $countFailed; ?> failed transaction<?php echo $countFailed==1?'':'s'; ?></div>
        </div>
        <div class="stat net">
            <label>Net Revenue</label>
            <div class="value">Ksh <?php echo number_format($netRevenue, 0); ?></div>
            <div class="count">Confirmed only</div>
        </div>
    </div>

    <!-- =================== TABLE =================== -->
    <div class="table-wrap">
        <?php if (empty($transactions)): ?>
            <div class="empty">
                <i class="fas fa-inbox"></i>
                <h3>No transactions found</h3>
                <p>Try widening the date range or clearing filters.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>M-Pesa Receipt</th>
                        <th>Status</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <?php
                            $st = $t['payment_status'] ?: 'pending';
                            $statusLabel = $st === 'awaiting_payment' ? 'Awaiting' : ucfirst($st);
                        ?>
                        <tr>
                            <td class="mono">
                                <?php echo htmlspecialchars(date('d M Y H:i', strtotime($t['paid_at'] ?: $t['created_at']))); ?>
                            </td>
                            <td>
                                <a class="order-link"
                                   href="../order_confirmation.php?order=<?php echo urlencode($t['order_number']); ?>"
                                   target="_blank">
                                    <?php echo htmlspecialchars($t['order_number']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($t['delivery_recipient'] ?: '—'); ?></td>
                            <td class="mono"><?php echo htmlspecialchars($t['mpesa_phone'] ?: $t['delivery_phone'] ?: '—'); ?></td>
                            <td class="mono"><?php echo htmlspecialchars($t['mpesa_receipt'] ?: $t['payment_reference'] ?: '—'); ?></td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($st); ?>">
                                    <i class="fas <?php
                                        echo $st === 'paid' ? 'fa-check-circle'
                                           : ($st === 'failed' ? 'fa-times-circle' : 'fa-clock');
                                    ?>"></i>
                                    <?php echo htmlspecialchars($statusLabel); ?>
                                </span>
                            </td>
                            <td class="amount">Ksh <?php echo number_format((float)$t['total'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p style="text-align:center; color:#9ca3af; font-size:12px; margin-top:20px;">
        Statement generated on <?php echo htmlspecialchars(date('d M Y H:i')); ?>
        &nbsp;·&nbsp; Period: <strong><?php echo htmlspecialchars($from); ?></strong> to <strong><?php echo htmlspecialchars($to); ?></strong>
    </p>
</div>

<script>
    // Auto-submit on date change (feels snappier)
    document.querySelectorAll('#filterForm input[type="date"], #filterForm select').forEach(el => {
        el.addEventListener('change', () => document.getElementById('filterForm').submit());
    });
</script>
</body>
</html>
