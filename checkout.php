<?php
// Include config first to start session and get database connection
require_once 'includes/config.php';
require_once 'includes/cloudinary_helper.php';   

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header('Location: home.php');
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';

// Init state vars
$cartItems = [];
$total = 0;
$error = '';
$order_error = '';
$order_success = false;

// Get cart items
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
                $pdo->beginTransaction();
                
                $order_total = $total + ($total * 0.1);
                $shipping_fee = $total * 0.1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO orders (user_id, order_number, total, shipping_fee, status, 
                                       payment_method, shipping_address, shipping_city, 
                                       delivery_instructions, created_at)
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())
                ");
                
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
                    
                    $update_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    $update_stock->execute([$item['quantity'], $item['product_id']]);
                }
                
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $pdo->commit();
                
                logActivity(
                    'order_placed',
                    'Placed order #' . $order_number . ' with ' . count($cartItems) . ' items',
                    $user_id,
                    $user_name
                );
                
                $_SESSION['order_success'] = true;
                $_SESSION['order_number'] = $order_number;
                header('Location: order_confirmation.php');
                exit();
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Checkout error: ' . $e->getMessage());
        $order_error = 'An error occurred while processing your order. Please try again.';
    }
}

$page_title = 'Checkout';
?>
