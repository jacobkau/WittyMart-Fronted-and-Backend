<?php
// ============================================
// WITTYMART RECEIPT 
// ============================================
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_number = trim($_GET['order'] ?? '');

if ($order_number === '') {
    http_response_code(400);
    exit('Missing order number');
}

// Fetch order (only if it belongs to this user)
$order = null;
$items = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM orders
        WHERE order_number = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_number, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Receipt fetch error: ' . $e->getMessage());
}

if (!$order) {
    http_response_code(404);
    exit('Order not found');
}

// Only allow receipts for orders that have a payment reference (i.e. paid)
$isPaid = ($order['payment_status'] === 'paid');

$siteName    = 'WittyMart';
$siteEmail   = 'wittyhighbrowtechnologies@gmail.com';
$sitePhone   = '+254 768 374 497';
$siteAddress = 'WittyMart HQ, Nairobi CBD, Kenya';

// ============================================
// LOGO    
// ============================================
$siteUrl  = 'https://wittymart.onrender.com';
$logoPath = 'images/wittymart-logo.png'; 
$logoUrl  = rtrim($siteUrl, '/') . '/' . ltrim($logoPath, '/');

// Compute totals
$subtotal = 0;
foreach ($items as $it) $subtotal += (float)$it['total'];
$shipping = (float)($order['shipping_fee'] ?? 0);
$coupon   = (float)($order['coupon_discount'] ?? 0);
$grand    = (float)$order['total'];

$receiptNumber = $order['mpesa_receipt'] ?: ($order['payment_reference'] ?: $order['order_number']);
$paidAt        = $order['paid_at'] ?? $order['updated_at'] ?? $order['created_at'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?php echo htmlspecialchars($order['order_number']); ?> — <?php echo htmlspecialchars($siteName); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --brand: #05573c;
        --brand-dark: #03402c;
        --brand-light: #f0faf5;
        --text: #222;
        --muted: #6b7280;
        --border: #e5e7eb;
        --bg: #f3f4f6;
    }

    body {
        font-family: -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.55;
        padding: 30px 16px 60px;
    }

    /* ============================================
       ACTION BAR (hidden on print)
       ============================================ */
    .actions {
        max-width: 780px;
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }
    .actions button,
    .actions a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: none;
        font-family: inherit;
        transition: all 0.15s ease;
    }
    .btn-primary {
        background: var(--brand);
        color: #fff;
    }
    .btn-primary:hover { background: var(--brand-dark); }
    .btn-outline {
        background: #fff;
        color: var(--text);
        border: 1.5px solid var(--border);
    }
    .btn-outline:hover { border-color: var(--brand); color: var(--brand); }

    /* ============================================
       RECEIPT CARD
       ============================================ */
    .receipt {
        max-width: 780px;
        margin: 0 auto;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 6px 30px rgba(0,0,0,0.08);
        overflow: hidden;
        position: relative;
    }

    /* Top brand header */
    .r-header {
        background: linear-gradient(135deg, var(--brand) 0%, #0a7a54 100%);
        color: #fff;
        padding: 28px 34px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }
    .r-header .brand {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    /* ============================================
       LOGO — wittymart-logo.png
       ============================================ */
    .r-header .brand .logo {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid rgba(255,255,255,0.35);
        overflow: hidden;
        padding: 6px;
        flex-shrink: 0;
    }
    .r-header .brand .logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }

    .r-header .brand h1 {
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 0.3px;
        line-height: 1.1;
    }
    .r-header .brand small {
        display: block;
        font-size: 12px;
        opacity: 0.85;
        font-weight: 500;
        margin-top: 3px;
    }
    .r-header .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: rgba(255,255,255,0.18);
        border: 1.5px solid rgba(255,255,255,0.35);
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }
    .r-header .status-badge.paid { background: #ffffff; color: var(--brand); border-color: #fff; }
    .r-header .status-badge.unpaid { background: #fef3c7; color: #92400e; border-color: #fde68a; }

    /* Meta row: receipt no + date */
    .r-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        padding: 24px 34px;
        background: #fafbfc;
        border-bottom: 1px solid var(--border);
    }
    .r-meta .cell label {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--muted);
        font-weight: 700;
        margin-bottom: 6px;
    }
    .r-meta .cell .value {
        font-size: 15px;
        font-weight: 700;
        color: var(--text);
        font-family: 'SF Mono', 'Courier New', monospace;
        word-break: break-all;
    }

    /* Two-column details: billed to / paid with */
    .r-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        padding: 24px 34px;
        border-bottom: 1px solid var(--border);
    }
    .r-details h3 {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--muted);
        font-weight: 700;
        margin-bottom: 10px;
    }
    .r-details .line {
        font-size: 14px;
        color: var(--text);
        line-height: 1.65;
    }
    .r-details .line strong { font-weight: 700; }
    .r-details .line .muted { color: var(--muted); font-size: 13px; }

    /* Items table */
    .r-items {
        padding: 8px 34px 0;
    }
    .r-items table {
        width: 100%;
        border-collapse: collapse;
        margin: 16px 0 8px;
    }
    .r-items thead th {
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--muted);
        font-weight: 700;
        padding: 12px 8px;
        border-bottom: 2px solid var(--border);
    }
    .r-items thead th.qty,
    .r-items thead th.amount { text-align: right; }
    .r-items tbody td {
        padding: 14px 8px;
        font-size: 14px;
        color: var(--text);
        border-bottom: 1px solid var(--border);
    }
    .r-items tbody td.qty,
    .r-items tbody td.amount { text-align: right; font-variant-numeric: tabular-nums; }
    .r-items tbody td.item-name { font-weight: 600; }
    .r-items tbody td.item-name small {
        display: block;
        color: var(--muted);
        font-weight: 400;
        font-size: 12px;
        margin-top: 2px;
    }
    .r-items tbody tr:last-child td { border-bottom: none; }

    /* Totals block */
    .r-totals {
        padding: 12px 34px 30px;
        display: flex;
        justify-content: flex-end;
    }
    .r-totals .totals {
        width: 100%;
        max-width: 340px;
    }
    .r-totals .row {
        display: flex;
        justify-content: space-between;
        padding: 7px 0;
        font-size: 14px;
        color: var(--text);
    }
    .r-totals .row.discount { color: #16a34a; font-weight: 600; }
    .r-totals .row.grand {
        margin-top: 8px;
        padding-top: 14px;
        border-top: 2px solid var(--brand);
        font-size: 18px;
        font-weight: 800;
        color: var(--brand);
    }
    .r-totals .row.grand span:last-child { font-variant-numeric: tabular-nums; }

    /* Confirmation stamp */
    .r-stamp {
        margin: 0 34px 24px;
        padding: 14px 18px;
        border-radius: 10px;
        background: var(--brand-light);
        border-left: 4px solid var(--brand);
        font-size: 13px;
        color: #155724;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .r-stamp i { font-size: 20px; color: var(--brand); }
    .r-stamp strong { display: block; font-size: 14px; margin-bottom: 2px; }

    /* Footer */
    .r-footer {
        background: #fafbfc;
        border-top: 1px solid var(--border);
        padding: 20px 34px 24px;
        text-align: center;
        color: var(--muted);
        font-size: 12.5px;
        line-height: 1.7;
    }
    .r-footer strong { color: var(--text); }
    .r-footer .thanks {
        color: var(--brand);
        font-weight: 700;
        font-size: 14px;
        display: block;
        margin-bottom: 6px;
    }

    /* ============================================
       PRINT STYLES
       ============================================ */
    @media print {
        @page { margin: 12mm; size: A4; }
        body { background: #fff; padding: 0; }
        .actions { display: none !important; }
        .receipt {
            box-shadow: none;
            border-radius: 0;
            max-width: 100%;
        }
        .r-header {
            background: var(--brand) !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .r-stamp, .status-badge {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .r-header .brand .logo {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .r-items tbody tr { page-break-inside: avoid; }
    }

    /* ============================================
       MOBILE
       ============================================ */
    @media (max-width: 640px) {
        .r-header { padding: 22px; }
        .r-header .brand h1 { font-size: 18px; }
        .r-header .brand .logo { width: 46px; height: 46px; padding: 5px; }
        .r-meta,
        .r-details { grid-template-columns: 1fr; gap: 16px; padding: 20px 22px; }
        .r-items { padding: 8px 22px 0; }
        .r-items thead th,
        .r-items tbody td { padding: 10px 6px; font-size: 13px; }
        .r-totals { padding: 12px 22px 24px; }
        .r-stamp { margin: 0 22px 20px; }
        .r-footer { padding: 18px 22px; }
    }
</style>
</head>
<body>

    <div class="actions">
        <button class="btn-primary" onclick="window.print()">
            <i class="fas fa-download"></i> Download PDF
        </button>
        <a class="btn-outline" href="order_confirmation.php?order=<?php echo urlencode($order['order_number']); ?>">
            <i class="fas fa-arrow-left"></i> Back to Order
        </a>
    </div>

    <div class="receipt">

        <!-- Header -->
        <div class="r-header">
            <div class="brand">

                <!-- ============================================
                     LOGO — wittymart-logo.png 
                     ============================================ -->
                <div class="logo">
                    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>"
                         alt="<?php echo htmlspecialchars($siteName); ?> logo"
                         onerror="this.onerror=null; this.parentNode.innerHTML='<span style=&quot;font-weight:800;font-size:20px;color:#05573c;&quot;>W</span>';">
                </div>

                <div>
                    <h1><?php echo htmlspecialchars($siteName); ?></h1>
                    <small>Payment Receipt</small>
                </div>
            </div>
            <div class="status-badge <?php echo $isPaid ? 'paid' : 'unpaid'; ?>">
                <i class="fas <?php echo $isPaid ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                <?php echo $isPaid ? 'Paid' : strtoupper(htmlspecialchars($order['payment_status'])); ?>
            </div>
        </div>

        <!-- Meta: receipt #, date -->
        <div class="r-meta">
            <div class="cell">
                <label>Receipt Number</label>
                <div class="value"><?php echo htmlspecialchars($receiptNumber); ?></div>
            </div>
            <div class="cell">
                <label>Date Paid</label>
                <div class="value">
                    <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($paidAt))); ?>
                </div>
            </div>
        </div>

        <!-- Billed to / Payment method -->
        <div class="r-details">
            <div>
                <h3><i class="fas fa-user"></i> Billed To</h3>
                <div class="line">
                    <strong><?php echo htmlspecialchars($order['delivery_recipient'] ?: ($user['name'] ?? 'Customer')); ?></strong><br>
                    <?php if (!empty($order['delivery_phone'])): ?>
                        <span class="muted"><?php echo htmlspecialchars($order['delivery_phone']); ?></span><br>
                    <?php endif; ?>
                    <?php if (!empty($order['shipping_address'])): ?>
                        <span class="muted"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                <div class="line">
                    <strong>
                        <?php
                            $pm = strtolower($order['payment_method'] ?? '');
                            echo $pm === 'mpesa'    ? 'M-Pesa (STK Push)'
                               : ($pm === 'paybill' ? 'M-Pesa Paybill'
                               : ($pm === 'pay_on_delivery' ? 'Pay on Delivery'
                               : strtoupper($pm ?: 'Unknown')));
                        ?>
                    </strong><br>
                    <?php if (!empty($order['mpesa_phone'])): ?>
                        <span class="muted">Phone: <?php echo htmlspecialchars($order['mpesa_phone']); ?></span><br>
                    <?php endif; ?>
                    <span class="muted">Order #<?php echo htmlspecialchars($order['order_number']); ?></span>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="r-items">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="qty">Qty</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td class="item-name">
                                <?php echo htmlspecialchars($it['product_name']); ?>
                                <?php if (!empty($it['price'])): ?>
                                    <small>Unit: Ksh <?php echo number_format((float)$it['price'], 0); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="qty"><?php echo (int)$it['quantity']; ?></td>
                            <td class="amount">Ksh <?php echo number_format((float)$it['total'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="r-totals">
            <div class="totals">
                <div class="row">
                    <span>Subtotal</span>
                    <span>Ksh <?php echo number_format($subtotal, 0); ?></span>
                </div>
                <?php if ($coupon > 0): ?>
                <div class="row discount">
                    <span><i class="fas fa-tag"></i> Coupon <?php echo htmlspecialchars($order['coupon_code'] ?? ''); ?></span>
                    <span>-Ksh <?php echo number_format($coupon, 0); ?></span>
                </div>
                <?php endif; ?>
                <div class="row">
                    <span>Transport</span>
                    <span>Ksh <?php echo number_format($shipping, 0); ?></span>
                </div>
                <div class="row grand">
                    <span>Total Paid</span>
                    <span>Ksh <?php echo number_format($grand, 0); ?></span>
                </div>
            </div>
        </div>

        <!-- Confirmation stamp -->
        <?php if ($isPaid): ?>
        <div class="r-stamp">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Payment received. Thank you!</strong>
                This is an official receipt from <?php echo htmlspecialchars($siteName); ?>. Keep it for your records.
            </div>
        </div>
        <?php else: ?>
        <div class="r-stamp" style="background:#fef3c7; border-left-color:#f59e0b; color:#92400e;">
            <i class="fas fa-clock" style="color:#f59e0b;"></i>
            <div>
                <strong>Payment not yet confirmed</strong>
                This receipt is provisional until payment is received.
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="r-footer">
            <span class="thanks">Thank you for shopping with <?php echo htmlspecialchars($siteName); ?>!</span>
            <strong><?php echo htmlspecialchars($siteName); ?></strong> &middot;
            <?php echo htmlspecialchars($siteEmail); ?> &middot;
            <?php echo htmlspecialchars($sitePhone); ?><br>
            <?php echo htmlspecialchars($siteAddress); ?>
        </div>

    </div>

    <script>
        // Auto-open the print dialog when opened with ?print=1
        if (new URLSearchParams(location.search).get('print') === '1') {
            window.addEventListener('load', function () { setTimeout(() => window.print(), 300); });
        }
    </script>
</body>
</html>
