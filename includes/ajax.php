<?php
require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

$db = Database::getInstance();

switch ($action) {
    // ===== ADMIN ACTIONS (Requires Admin Login) =====
    case 'get_order':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Unauthorized'];
            break;
        }
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid order ID'];
            break;
        }
        
        $order = $db->getOrder($id);
        if ($order) {
            $stmt = $db->getPDO()->prepare(
                "SELECT oi.*, p.name as product_name 
                 FROM order_items oi 
                 JOIN products p ON oi.product_id = p.id 
                 WHERE oi.order_id = ?"
            );
            $stmt->execute([$id]);
            $items = $stmt->fetchAll();
            
            $response = [
                'success' => true,
                'order' => $order,
                'items' => $items
            ];
        } else {
            $response = ['success' => false, 'message' => 'Order not found'];
        }
        break;
        
    case 'get_customer':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Unauthorized'];
            break;
        }
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid customer ID'];
            break;
        }
        
        $stmt = $db->getPDO()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            $stmt = $db->getPDO()->prepare(
                "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total 
                 FROM orders WHERE user_id = ?"
            );
            $stmt->execute([$id]);
            $stats = $stmt->fetch();
            
            $response = [
                'success' => true,
                'customer' => $customer,
                'order_count' => $stats['count'] ?? 0,
                'total_spent' => $stats['total'] ?? 0
            ];
        } else {
            $response = ['success' => false, 'message' => 'Customer not found'];
        }
        break;
        
    case 'update_status':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Unauthorized'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $status = sanitize($input['status'] ?? '');
        
        if ($id && $status) {
            if ($db->updateOrderStatus($id, $status)) {
                $response = ['success' => true, 'message' => 'Status updated'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update status'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Invalid data'];
        }
        break;
    
    // ===== USER ACTIONS (Requires Login) =====
    case 'add_to_cart':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Please login first'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = intval($input['product_id'] ?? 0);
        $quantity = intval($input['quantity'] ?? 1);
        
        if ($product_id && $quantity > 0) {
            // Add to cart logic here
            // You can use session or database for cart
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = $quantity;
            }
            
            $response = [
                'success' => true,
                'message' => 'Product added to cart',
                'cart_count' => array_sum($_SESSION['cart'])
            ];
        } else {
            $response = ['success' => false, 'message' => 'Invalid product data'];
        }
        break;
        
    case 'get_cart_count':
        if (!isLoggedIn()) {
            $response = ['success' => false, 'cart_count' => 0];
            break;
        }
        
        $count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
        $response = ['success' => true, 'cart_count' => $count];
        break;
        
    case 'search_products':
        $query = sanitize($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            $response = ['success' => true, 'products' => []];
            break;
        }
        
        $stmt = $db->getPDO()->prepare(
            "SELECT * FROM products 
             WHERE name LIKE ? OR description LIKE ? 
             LIMIT 10"
        );
        $search = "%{$query}%";
        $stmt->execute([$search, $search]);
        $products = $stmt->fetchAll();
        
        $response = ['success' => true, 'products' => $products];
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Action not found'];
}

echo json_encode($response);
