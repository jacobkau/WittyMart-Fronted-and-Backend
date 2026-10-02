<?php
require_once 'includes/config.php';
requireAdmin();

$order_number = $_GET['order'] ?? '';
$order = null;
$items = [];

if ($order_number) {
    $stmt = $pdo->prepare("
        SELECT o.*, u.name AS customer_name, u.email AS customer_email,
               s.name AS supplier_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN suppliers s ON o.address_id = s.id
        WHERE o.order_number = ?
    ");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();
    
    if ($order) {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Track Order - Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .track-container { max-width: 700px; margin: 40px auto; padding: 24px; background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .status-timeline { display: flex; justify-content: space-between; margin: 30px 0; position: relative; }
        .status-timeline::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #e0e0e0; z-index: 1; }
        .status-step { text-align: center; position: relative; z-index: 2; flex: 1; }
        .status-step .dot { width: 40px; height: 40px; border-radius: 50%; background: #e0e0e0; color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-size: 16px; }
        .status-step.active .dot { background: #05573c; }
        .status-step .label { font-size: 12px; color: #666; font-weight: 600; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>
        <main class="admin-main">
            <div class="track-container">
                <h2><i class="fas fa-search"></i> Track Order</h2>
                <form method="GET" style="display:flex; gap:8px; margin-bottom:24px;">
                    <input type="text" name="order" placeholder="Enter order number" value="<?php echo htmlspecialchars($order_number); ?>" style="flex:1; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                    <button type="submit" style="padding:10px 24px; background:#05573c; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600;">Track</button>
                </form>
                
                <?php if ($order): ?>
                    <div style="background:#f8f9fa; padding:16px; border-radius:10px; margin-bottom:20px;">
                        <strong>Order #<?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                        Customer: <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?><br>
                        Placed: <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                    </div>
                    
                    <div class="status-timeline">
                        <?php 
                        $statuses = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
                        $currentIndex = array_search($order['status'], array_keys($statuses));
                        $i = 0;
                        foreach ($statuses as $key => $label): 
                        ?>
                            <div class="status-step <?php echo $i <= $currentIndex ? 'active' : ''; ?>">
                                <div class="dot"><i class="fas <?php echo $i <= $currentIndex ? 'fa-check' : 'fa-circle'; ?>"></i></div>
                                <div class="label"><?php echo $label; ?></div>
                            </div>
                        <?php $i++; endforeach; ?>
                    </div>
                <?php elseif ($order_number): ?>
                    <div class="alert alert-error">Order not found.</div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
