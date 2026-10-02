<?php
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_number = trim($_GET['order'] ?? '');
$order = null;
$items = [];
$error = '';

if ($order_number) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM orders 
            WHERE order_number = ? AND user_id = ?
        ");
        $stmt->execute([$order_number, $user_id]);
        $order = $stmt->fetch();

        if ($order) {
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $items = $stmt->fetchAll();
        } else {
            $error = 'Order not found or does not belong to your account.';
        }
    } catch (PDOException $e) { error_log('Track order: '.$e->getMessage()); }
}

// Status flow
$statuses = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$currentStatus = $order['status'] ?? 'pending';
$currentIndex = array_search($currentStatus, array_keys($statuses));
if ($currentIndex === false) $currentIndex = 0;

$page_title = 'Track Order';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Order - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .track-wrap { max-width:760px; margin:30px auto; padding:0 20px; }
        .track-wrap h1 { color:#05573c; margin-bottom:20px; }
        .track-card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:24px; margin-bottom:20px; }
        .track-form { display:flex; gap:10px; margin-bottom:24px; }
        .track-form input { flex:1; padding:12px 16px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; }
        .track-form input:focus { outline:none; border-color:#05573c; }
        .track-form button { padding:12px 26px; background:#05573c; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
        .track-form button:hover { background:#03402c; }

        .timeline { position:relative; margin:30px 0; padding:0 10px; }
        .timeline::before { content:''; position:absolute; top:22px; left:10%; right:10%; height:3px; background:#e0e0e0; z-index:1; }
        .timeline-steps { display:flex; justify-content:space-between; position:relative; z-index:2; }
        .tl-step { text-align:center; flex:1; position:relative; }
        .tl-dot { width:44px; height:44px; border-radius:50%; background:#e0e0e0; color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 8px; font-size:18px; transition:all .3s; }
        .tl-step.active .tl-dot { background:#05573c; box-shadow:0 0 0 4px rgba(5,87,60,.15); }
        .tl-step.current .tl-dot { animation:pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { box-shadow:0 0 0 4px rgba(5,87,60,.15); }
            50% { box-shadow:0 0 0 10px rgba(5,87,60,.08); }
        }
        .tl-label { font-size:12px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.5px; }
        .tl-step.active .tl-label { color:#05573c; }

        .track-info { background:#f8f9fa; padding:16px; border-radius:10px; margin-bottom:20px; }
        .track-info .row { display:flex; justify-content:space-between; padding:6px 0; font-size:14px; }
        .track-info .row .label { color:#888; }
        .track-info .row .value { color:#333; font-weight:600; text-align:right; }

        .items-list { margin-top:20px; }
        .items-list h3 { margin:0 0 12px; font-size:14px; text-transform:uppercase; color:#888; letter-spacing:.5px; }
        .item-row { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f0f0f0; align-items:center; }
        .item-row:last-child { border-bottom:none; }
        .item-row .it-name { flex:1; font-size:14px; color:#333; font-weight:500; }
        .item-row .it-qty { color:#888; font-size:13px; }
        .item-row .it-total { font-weight:700; color:#05573c; }

        .btn-invoice { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:#f0f0f0; color:#333; border-radius:8px; text-decoration:none; font-weight:600; margin-top:14px; }
        .btn-invoice:hover { background:#e0e0e0; }

        .alert-error { padding:15px 20px; border-radius:8px; margin-bottom:20px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <main>
        <div class="track-wrap">
            <h1><i class="fas fa-map-marker-alt"></i> Track Order</h1>

            <div class="track-card">
                <form class="track-form" method="GET">
                    <input type="text" name="order" placeholder="Enter your order number (e.g. ORD-20251002-00123)" value="<?php echo htmlspecialchars($order_number); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Track</button>
                </form>

                <?php if ($error): ?>
                    <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($order): ?>
                    <div class="track-info">
                        <div class="row"><span class="label">Order Number</span><span class="value"><?php echo htmlspecialchars($order['order_number']); ?></span></div>
                        <div class="row"><span class="label">Status</span><span class="value" style="text-transform:capitalize;"><?php echo htmlspecialchars($order['status']); ?></span></div>
                        <div class="row"><span class="label">Placed</span><span class="value"><?php echo date('M d, Y · H:i', strtotime($order['created_at'])); ?></span></div>
                        <div class="row"><span class="label">Payment</span><span class="value" style="text-transform:capitalize;"><?php echo str_replace('_', ' ', htmlspecialchars($order['payment_method'] ?? '')); ?></span></div>
                        <?php if (!empty($order['delivery_county'])): ?>
                            <div class="row"><span class="label">Delivery to</span><span class="value"><?php echo htmlspecialchars($order['delivery_county']); ?></span></div>
                        <?php endif; ?>
                        <div class="row"><span class="label">Total</span><span class="value" style="color:#05573c; font-size:16px;">Ksh <?php echo number_format($order['total'], 0); ?></span></div>
                    </div>

                    <div class="timeline">
                        <div class="timeline-steps">
                            <?php $i = 0; foreach ($statuses as $key => $label): 
                                $isActive = $i <= $currentIndex;
                                $isCurrent = $i === $currentIndex;
                            ?>
                                <div class="tl-step <?php echo $isActive ? 'active' : ''; ?> <?php echo $isCurrent ? 'current' : ''; ?>">
                                    <div class="tl-dot"><i class="fas <?php echo $isActive ? 'fa-check' : 'fa-circle'; ?>"></i></div>
                                    <div class="tl-label"><?php echo $label; ?></div>
                                </div>
                            <?php $i++; endforeach; ?>
                        </div>
                    </div>

                    <?php if (!empty($items)): ?>
                        <div class="items-list">
                            <h3>Items</h3>
                            <?php foreach ($items as $it): ?>
                                <div class="item-row">
                                    <span class="it-name"><?php echo htmlspecialchars($it['product_name']); ?></span>
                                    <span class="it-qty">× <?php echo $it['quantity']; ?></span>
                                    <span class="it-total">Ksh <?php echo number_format($it['total'], 0); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <a href="orders.php?invoice=<?php echo (int)$order['id']; ?>" class="btn-invoice">
                        <i class="fas fa-file-pdf"></i> Download Invoice
                    </a>
                <?php elseif (!$order_number): ?>
                    <p style="text-align:center; color:#888; padding:20px;">
                        <i class="fas fa-info-circle"></i> Enter your order number above to see its status.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>
