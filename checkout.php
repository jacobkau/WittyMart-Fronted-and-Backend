<?php
// Include config first to start session and get database connection
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header('Location: home.php');
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';

// Get cart items
$cartItems = [];
$total = 0;
$error = '';

try {
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, c.product_id, c.quantity, 
               p.name, p.price, p.image, p.image_url, p.stock
        FROM cart c
        INNER JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $cartItems = $stmt->fetchAll();
    
    // Calculate total
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }
} catch (PDOException $e) {
    error_log('Cart error: ' . $e->getMessage());
    $cartItems = [];
    $error = 'Could not load cart items. Please try again.';
}

// Check if cart is empty
if (empty($cartItems)) {
    header('Location: cart.php');
    exit();
}

// Get user's saved addresses
$user = getCurrentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $order_error = '';
    $order_success = false;
    
    try {
        // Validate form data
        $full_name = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $payment_method = sanitize($_POST['payment_method'] ?? '');
        $delivery_instructions = sanitize($_POST['delivery_instructions'] ?? '');
        
        // Validate required fields
        if (empty($full_name) || empty($phone) || empty($address) || empty($city)) {
            $order_error = 'Please fill in all required fields.';
        } elseif (empty($payment_method)) {
            $order_error = 'Please select a payment method.';
        } else {
            // Check stock before processing
            $stock_error = false;
            foreach ($cartItems as $item) {
                if ($item['stock'] < $item['quantity']) {
                    $order_error = "Product '{$item['name']}' has insufficient stock. Available: {$item['stock']}";
                    $stock_error = true;
                    break;
                }
            }
            
            if (!$stock_error) {
                // Start transaction
                $pdo->beginTransaction();
                
                // Calculate order total
                $order_total = $total + ($total * 0.1); // 10% shipping fee
                $shipping_fee = $total * 0.1;
                
                // Create order
                $stmt = $pdo->prepare("
                    INSERT INTO orders (user_id, order_number, total, shipping_fee, status, 
                                       payment_method, shipping_address, shipping_city, 
                                       delivery_instructions, created_at)
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())
                ");
                
                // Generate unique order number
                $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                
                $shipping_address = $address . ', ' . $city;
                
                $stmt->execute([
                    $user_id,
                    $order_number,
                    $order_total,
                    $shipping_fee,
                    $payment_method,
                    $shipping_address,
                    $city,
                    $delivery_instructions
                ]);
                
                $order_id = $pdo->lastInsertId();
                
                // Add order items
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($cartItems as $item) {
                    $item_total = $item['price'] * $item['quantity'];
                    $stmt->execute([
                        $order_id,
                        $item['product_id'],
                        $item['name'],
                        $item['quantity'],
                        $item['price'],
                        $item_total
                    ]);
                    
                    // Update product stock
                    $update_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    $update_stock->execute([$item['quantity'], $item['product_id']]);
                }
                
                // Clear the cart
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Commit transaction
                $pdo->commit();
                
                // Log activity
                logActivity(
                    'order_placed',
                    'Placed order #' . $order_number . ' with ' . count($cartItems) . ' items',
                    $user_id,
                    $user_name
                );
                
                // Redirect to order confirmation
                $_SESSION['order_success'] = true;
                $_SESSION['order_number'] = $order_number;
                header('Location: order_confirmation.php');
                exit();
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Checkout error: ' . $e->getMessage());
        $order_error = 'An error occurred while processing your order. Please try again.';
    }
}

$page_title = 'Checkout';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .checkout-container {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 30px;
            margin: 20px 0;
        }
        
        .checkout-form {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .checkout-form h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group label .required {
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #05573c;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .order-summary {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
            align-self: start;
        }
        
        .order-summary h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-details h4 {
            margin: 0 0 3px 0;
            font-size: 14px;
            color: #333;
        }
        
        .order-item-details .item-price {
            font-size: 13px;
            color: #05573c;
            font-weight: 600;
        }
        
        .order-item-details .item-quantity {
            font-size: 12px;
            color: #888;
        }
        
        .order-totals {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }
        
        .order-totals .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
            color: #555;
        }
        
        .order-totals .total-row.grand-total {
            font-size: 20px;
            font-weight: 700;
            color: #05573c;
            border-top: 2px solid #05573c;
            padding-top: 15px;
            margin-top: 5px;
        }
        
        .payment-methods {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .payment-methods label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 400;
        }
        
        .payment-methods label:hover {
            border-color: #05573c;
        }
        
        .payment-methods input[type="radio"] {
            width: auto;
            margin: 0;
        }
        
        .payment-methods label.selected {
            border-color: #05573c;
            background: #f0faf5;
        }
        
        .btn-place-order {
            width: 100%;
            padding: 14px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-place-order:hover:not(:disabled) {
            background: #03402c;
        }
        
        .btn-place-order:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .checkout-form {
                padding: 20px;
            }
            
            .order-summary {
                padding: 20px;
            }
            
            .payment-methods {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <main>
        <section class="checkout">
            <h1>Checkout</h1>
            
            <?php if (!empty($order_error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($order_error); ?>
                </div>
            <?php endif; ?>
            
            <div class="checkout-container">
                <!-- Checkout Form -->
                <div class="checkout-form">
                    <h2><i class="fas fa-user"></i> Shipping Information</h2>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" required 
                                   value="<?php echo htmlspecialchars($user['name'] ?? $user_name ?? ''); ?>"
                                   placeholder="Enter your full name">
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" required 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                   placeholder="Enter your phone number">
                        </div>
                        
                        <div class="form-group">
                            <label>Address <span class="required">*</span></label>
                            <input type="text" name="address" required 
                                   placeholder="Street address, building, apartment">
                        </div>
                        
                        <div class="form-group">
                            <label>City <span class="required">*</span></label>
                            <input type="text" name="city" required 
                                   placeholder="City / Town">
                        </div>
                        
                        <div class="form-group">
                            <label>Delivery Instructions</label>
                            <textarea name="delivery_instructions" 
                                      placeholder="Any special delivery instructions"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Payment Method <span class="required">*</span></label>
                            <div class="payment-methods">
                                <label class="selected">
                                    <input type="radio" name="payment_method" value="mpesa" checked>
                                    <i class="fas fa-mobile-alt" style="color:#25A349;"></i> M-Pesa
                                </label>
                                <label>
                                    <input type="radio" name="payment_method" value="cash">
                                    <i class="fas fa-money-bill-wave" style="color:#28a745;"></i> Cash on Delivery
                                </label>
                                <label>
                                    <input type="radio" name="payment_method" value="card">
                                    <i class="fas fa-credit-card" style="color:#0056b3;"></i> Card Payment
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="place_order" class="btn-place-order">
                            <i class="fas fa-check-circle"></i> Place Order
                        </button>
                    </form>
                </div>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <h2><i class="fas fa-receipt"></i> Order Summary</h2>
                    
                    <?php foreach ($cartItems as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo htmlspecialchars(getCartProductImage($item)); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 onerror="this.src='uploads/products/no-image.png'">
                            <div class="order-item-details">
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <div class="item-price">Ksh <?php echo number_format($item['price'], 0); ?></div>
                                <div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                            </div>
                            <div style="font-weight:700; color:#05573c;">
                                Ksh <?php echo number_format($item['price'] * $item['quantity'], 0); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span>Ksh <?php echo number_format($total, 0); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Shipping Fee (10%)</span>
                            <span>Ksh <?php echo number_format($total * 0.1, 0); ?></span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total</span>
                            <span>Ksh <?php echo number_format($total + ($total * 0.1), 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include "footer.php"; ?>

    <script>
        // Payment method selection styling
        document.querySelectorAll('.payment-methods label').forEach(label => {
            label.addEventListener('click', function() {
                document.querySelectorAll('.payment-methods label').forEach(l => l.classList.remove('selected'));
                this.classList.add('selected');
            });
            
            const radio = label.querySelector('input[type="radio"]');
            if (radio) {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.payment-methods label').forEach(l => l.classList.remove('selected'));
                    this.closest('label').classList.add('selected');
                });
            }
        });
    </script>
</body>
</html>
