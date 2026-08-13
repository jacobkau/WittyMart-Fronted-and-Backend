<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    header('Location: orders.php');
    exit();
}

// Debug: Log the order ID
error_log('Order Details - Order ID: ' . $order_id . ' User ID: ' . $user_id);

// Get order details
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    // Debug: Log if order found
    error_log('Order found: ' . ($order ? 'Yes' : 'No'));
    
    if (!$order) {
        // Order not found or doesn't belong to this user
        header('Location: orders.php');
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT * FROM order_items WHERE order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    // Debug: Log number of items
    error_log('Order items found: ' . count($items));
    
} catch (PDOException $e) {
    error_log('Order details error: ' . $e->getMessage());
    $error = 'Could not load order details. Please try again.';
}

$page_title = 'Order Details #' . ($order ? $order['order_number'] : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .order-details-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px 0;
        }
        
        .order-header {
            background: #fff;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-header .order-number {
            font-size: 24px;
            font-weight: 700;
            color: #05573c;
        }
        
        .order-header .order-date {
            color: #888;
            font-size: 14px;
        }
        
        .order-status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #28a745; color: #fff; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .order-info-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .order-info-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 14px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .order-info-card p {
            margin: 5px 0;
            color: #333;
        }
        
        .order-info-card .label {
            color: #888;
            font-size: 13px;
        }
        
        .order-items-table {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .order-items-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .order-items-table th {
            background: #f8f9fa;
            padding: 15px 20px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }
        
        .order-items-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-items-table tr:last-child td {
            border-bottom: none;
        }
        
        .order-items-table .item-product {
            font-weight: 600;
            color: #333;
        }
        
        .order-items-table .item-price {
            color: #05573c;
            font-weight: 600;
        }
        
        .order-summary {
            background: #fff;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            max-width: 400px;
            margin-left: auto;
        }
        
        .order-summary .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
            color: #555;
        }
        
        .order-summary .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #05573c;
            border-top: 2px solid #05573c;
            padding-top: 15px;
            margin-top: 5px;
        }
        
        .back-btn {
            display: inline-block;
            padding: 10px 25px;
            background: #05573c;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .back-btn:hover {
            background: #03402c;
        }
        
        .btn-print {
            padding: 10px 25px;
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-print:hover {
            background: #5a6268;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                text-align: center;
            }
            
            .order-info-grid {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                max-width: 100%;
            }
            
            .order-items-table {
                overflow-x: auto;
            }
            
            .order-items-table table {
                font-size: 13px;
            }
            
            .order-items-table th,
            .order-items-table td {
                padding: 10px 12px;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <main>
        <div class="order-details-container">
            <?php if (isset($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($order) && $order): ?>
                <!-- Order Header -->
                <div class="order-header">
                    <div>
                        <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                        <div class="order-date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F d, Y \a\t h:i A', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                    <div>
                        <span class="order-status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                        <button class="btn-print" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
                
                <!-- Order Info Grid -->
                <div class="order-info-grid">
                    <div class="order-info-card">
                        <h3><i class="fas fa-user"></i> Customer Information</h3>
                        <p><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                        <p><span class="label">Email:</span> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                        <p><span class="label">Phone:</span> <?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="order-info-card">
                        <h3><i class="fas fa-truck"></i> Shipping Address</h3>
                        <p><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'N/A')); ?></p>
                        <p><span class="label">City:</span> <?php echo htmlspecialchars($order['shipping_city'] ?? 'N/A'); ?></p>
                        <?php if (!empty($order['delivery_instructions'])): ?>
                            <p><span class="label">Instructions:</span> <?php echo htmlspecialchars($order['delivery_instructions']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-info-card">
                        <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                        <p><?php echo htmlspecialchars(ucfirst($order['payment_method'] ?? 'N/A')); ?></p>
                        <p><span class="label">Order Status:</span> <?php echo ucfirst($order['status']); ?></p>
                        <?php if (!empty($order['shipping_fee'])): ?>
                            <p><span class="label">Shipping Fee:</span> Ksh <?php echo number_format($order['shipping_fee'], 0); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="order-items-table">
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
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="item-product"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td class="item-price">Ksh <?php echo number_format($item['price'], 0); ?></td>
                                        <td class="item-price">Ksh <?php echo number_format($item['total'], 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; color:#888; padding:20px;">
                                        <i class="fas fa-box-open"></i> No items found for this order
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>Ksh <?php echo number_format($order['total'] - ($order['shipping_fee'] ?? 0), 0); ?></span>
                    </div>
                    <?php if (!empty($order['shipping_fee'])): ?>
                        <div class="summary-row">
                            <span>Shipping Fee</span>
                            <span>Ksh <?php echo number_format($order['shipping_fee'], 0); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>Ksh <?php echo number_format($order['total'], 0); ?></span>
                    </div>
                </div>
                
                <a href="orders.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            <?php else: ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Order not found. Please check your order number.
                </div>
                <a href="orders.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            <?php endif; ?>
        </div>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>
