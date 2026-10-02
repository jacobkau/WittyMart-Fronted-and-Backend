<?php
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Invoice download
if (isset($_GET['invoice'])) {
    $order_id = intval($_GET['invoice']);
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if ($order) {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();

        $pdf = generateInvoicePDF($order, $items, getCurrentUser());
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice-' . $order['order_number'] . '.pdf"');
        echo $pdf;
        exit();
    }
}

// List orders
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) { error_log('Orders load: '.$e->getMessage()); }

$page_title = 'My Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .orders-wrap { max-width:1000px; margin:30px auto; padding:0 20px; }
        .orders-wrap h1 { color:#05573c; margin-bottom:20px; }
        .order-card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:20px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:16px; align-items:center; }
        .order-card .info { flex:1; min-width:240px; }
        .order-card .num { font-weight:700; font-size:16px; color:#222; }
        .order-card .meta { color:#888; font-size:13px; margin-top:4px; }
        .order-card .total { font-size:18px; font-weight:700; color:#05573c; }
        .status-pill { display:inline-block; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:700; text-transform:uppercase; }
        .st-pending { background:#fff3cd; color:#856404; }
        .st-processing { background:#cce5ff; color:#004085; }
        .st-shipped { background:#d1ecf1; color:#0c5460; }
        .st-delivered { background:#d4edda; color:#155724; }
        .st-cancelled { background:#f8d7da; color:#721c24; }
        .status-badge { padding:4px 12px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; }
        .pay-awaiting { background:#fff3cd; color:#856404; }
        .pay-paid { background:#d4edda; color:#155724; }
        .pay-pending { background:#f8d7da; color:#721c24; }
        .order-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .order-actions a { padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:600; font-size:13px; }
        .btn-track { background:#05573c; color:#fff; }
        .btn-invoice { background:#f0f0f0; color:#333; }
        .btn-invoice:hover { background:#e0e0e0; }
        .empty-orders { text-align:center; padding:80px 20px; }
        .empty-orders i { font-size:64px; color:#ddd; margin-bottom:16px; display:block; }
        .empty-orders h3 { color:#555; margin:0 0 8px; }
        .empty-orders a { display:inline-block; margin-top:16px; padding:12px 28px; background:#05573c; color:#fff; border-radius:6px; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <main>
        <div class="orders-wrap">
            <h1><i class="fas fa-box"></i> My Orders</h1>

            <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <i class="fas fa-box-open"></i>
                    <h3>No orders yet</h3>
                    <p style="color:#888;">Start shopping to see your orders here.</p>
                    <a href="shop.php"><i class="fas fa-store"></i> Start Shopping</a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                    <div class="order-card">
                        <div class="info">
                            <div class="num">#<?php echo htmlspecialchars($o['order_number']); ?></div>
                            <div class="meta">
                                Placed <?php echo date('M d, Y · H:i', strtotime($o['created_at'])); ?>
                                · <?php echo (int)$o['item_count']; ?> item<?php echo $o['item_count'] == 1 ? '' : 's'; ?>
                            </div>
                            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                                <span class="status-pill st-<?php echo htmlspecialchars($o['status']); ?>">
                                    <?php echo htmlspecialchars($o['status']); ?>
                                </span>
                                <?php if (!empty($o['payment_status'])): ?>
                                    <span class="status-badge pay-<?php echo $o['payment_status'] === 'paid' ? 'paid' : ($o['payment_status'] === 'awaiting_payment' ? 'awaiting' : 'pending'); ?>">
                                        <?php echo str_replace('_', ' ', htmlspecialchars($o['payment_status'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="total">Ksh <?php echo number_format($o['total'], 0); ?></div>
                        <div class="order-actions">
                            <a href="track_order.php?order=<?php echo urlencode($o['order_number']); ?>" class="btn-track">
                                <i class="fas fa-map-marker-alt"></i> Track
                            </a>
                            <a href="orders.php?invoice=<?php echo (int)$o['id']; ?>" class="btn-invoice">
                                <i class="fas fa-file-pdf"></i> Invoice
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>
