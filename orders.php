<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's orders
try {
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get orders error: ' . $e->getMessage());
    $orders = [];
}

$page_title = 'My Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin: 20px 0;
        }
        
        .order-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .order-number {
            font-size: 18px;
            font-weight: 700;
            color: #05573c;
        }
        
        .order-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-shipped {
            background: #d4edda;
            color: #155724;
        }
        
        .status-delivered {
            background: #28a745;
            color: #fff;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-date {
            color: #888;
            font-size: 14px;
        }
        
        .order-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-items {
            flex: 1;
        }
        
        .order-items .item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
            color: #555;
        }
        
        .order-total {
            font-size: 20px;
            font-weight: 700;
            color: #05573c;
            text-align: right;
        }
        
        .order-actions {
            margin-top: 15px;
            text-align: right;
        }
        
        .btn-view {
            padding: 8px 20px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-view:hover {
            background: #03402c;
        }
        
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-orders i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
            display: block;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .order-details {
                flex-direction: column;
                text-align: center;
            }
            
            .order-total {
                text-align: center;
            }
            
            .order-actions {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <main>
        <section>
            <h1>My <span>Orders</span></h1>
            
            <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <i class="fas fa-box-open"></i>
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders yet. Start shopping now!</p>
                    <a href="shop.php" class="btn-primary" style="display:inline-block;margin-top:15px;padding:10px 30px;background:#05573c;color:#fff;border-radius:6px;text-decoration:none;">
                        Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <span class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></span>
                                    <span class="order-date"> - <?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                                <span class="order-status status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            
                            <div class="order-details">
                                <div class="order-items">
                                    <?php
                                    // Get order items count and total items
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(quantity) as total_items FROM order_items WHERE order_id = ?");
                                    $stmt->execute([$order['id']]);
                                    $items_info = $stmt->fetch();
                                    ?>
                                    <div class="item">
                                        <span><?php echo $items_info['total_items'] ?? 0; ?> item(s)</span>
                                        <span>Ksh <?php echo number_format($order['total'], 0); ?></span>
                                    </div>
                                    <div style="font-size:12px;color:#888;margin-top:5px;">
                                        Payment: <?php echo htmlspecialchars(ucfirst($order['payment_method'] ?? 'N/A')); ?>
                                    </div>
                                </div>
                                <div class="order-total">
                                    Ksh <?php echo number_format($order['total'], 0); ?>
                                </div>
                            </div>
                            
                            <div class="order-actions">
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>
