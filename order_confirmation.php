<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

// Get order details from session
$order_success = $_SESSION['order_success'] ?? false;
$order_number = $_SESSION['order_number'] ?? '';

// Clear session variables
unset($_SESSION['order_success']);
unset($_SESSION['order_number']);

// If no order confirmation, redirect to home
if (!$order_success || !$order_number) {
    header('Location: index.php');
    exit();
}

// Get order details
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.order_number = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_number, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header('Location: index.php');
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT * FROM order_items WHERE order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Order confirmation error: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

$page_title = 'Order Confirmation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .confirmation-box {
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: left;
        }
        
        .order-number {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            margin: 20px 0;
        }
        
        .order-number h3 {
            margin: 0;
            color: #05573c;
            font-size: 24px;
        }
        
        .order-details {
            margin: 20px 0;
        }
        
        .order-details table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .order-details th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        
        .order-details td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .order-total {
            text-align: right;
            font-size: 18px;
            font-weight: 700;
            color: #05573c;
            padding: 15px 0;
            border-top: 2px solid #05573c;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            display: inline-block;
            padding: 12px 30px;
            background: #05573c;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #03402c;
        }
        
        .btn-secondary {
            display: inline-block;
            padding: 12px 30px;
            background: #f8f9fa;
            color: #333;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        @media (max-width: 768px) {
            .confirmation-box {
                padding: 20px;
            }
            
            .order-details table {
                font-size: 13px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons a {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <main>
        <div class="confirmation-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Order Confirmed!</h1>
            <p style="color: #666; font-size: 18px;">Thank you for your order. We'll notify you once it's processed.</p>
            
            <div class="confirmation-box">
                <div class="order-number">
                    <h3>Order #<?php echo htmlspecialchars($order_number); ?></h3>
                </div>
                
                <div class="order-details">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>Ksh <?php echo number_format($item['price'], 0); ?></td>
                                    <td>Ksh <?php echo number_format($item['total'], 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="order-total">
                        Total: Ksh <?php echo number_format($order['total'], 0); ?>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                    <p><strong>Shipping Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></p>
                    <p><strong>Status:</strong> <span class="badge badge-warning">Pending</span></p>
                </div>
                
                <div class="action-buttons">
                    <a href="index.php" class="btn-primary"><i class="fas fa-home"></i> Continue Shopping</a>
                    <a href="orders.php" class="btn-secondary"><i class="fas fa-list"></i> View My Orders</a>
                </div>
            </div>
        </div>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>
