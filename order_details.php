<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

// Check if we have an order ID parameter
$order_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id) {
    // Get specific order details
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = ? AND o.user_id = ?
        ");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Get order items
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll();
        } else {
            header('Location: orders.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log('Order details error: ' . $e->getMessage());
        header('Location: orders.php');
        exit();
    }
} else {
    // Check session for order confirmation
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
        $stmt->execute([$order_number, $user_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            header('Location: index.php');
            exit();
        }
        
        // Get order items
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log('Order confirmation error: ' . $e->getMessage());
        header('Location: index.php');
        exit();
    }
}

$page_title = 'Order Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - WittyMart</title>
    <!-- ... rest of your HTML ... -->
</body>
</html>
