<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'auth.php';

// Set JSON header
header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

global $pdo;

switch ($action) {
    // ========================================
    // PRODUCT ACTIONS
    // ========================================
    
    case 'get_product':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid product ID'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $response = [
                    'success' => true,
                    'product' => $product
                ];
            } else {
                $response = ['success' => false, 'message' => 'Product not found'];
            }
        } catch (PDOException $e) {
            error_log('Get product error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'add_product':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, image, category_id, stock) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $success = $stmt->execute([
                sanitize($input['name'] ?? ''),
                sanitize($input['description'] ?? ''),
                floatval($input['price'] ?? 0),
                sanitize($input['image'] ?? ''),
                intval($input['category_id'] ?? 0),
                intval($input['stock'] ?? 0)
            ]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Product added successfully',
                    'id' => $pdo->lastInsertId()
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to add product'];
            }
        } catch (PDOException $e) {
            error_log('Add product error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'update_product':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid product ID'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, image = ?, category_id = ?, stock = ? 
                WHERE id = ?
            ");
            
            $success = $stmt->execute([
                sanitize($input['name'] ?? ''),
                sanitize($input['description'] ?? ''),
                floatval($input['price'] ?? 0),
                sanitize($input['image'] ?? ''),
                intval($input['category_id'] ?? 0),
                intval($input['stock'] ?? 0),
                $id
            ]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Product updated successfully'
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update product'];
            }
        } catch (PDOException $e) {
            error_log('Update product error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'delete_product':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid product ID'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Product deleted successfully'
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to delete product'];
            }
        } catch (PDOException $e) {
            error_log('Delete product error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    // ========================================
    // ORDER ACTIONS
    // ========================================
    
    case 'get_order':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid order ID'];
            break;
        }
        
        try {
            // Get order details
            $stmt = $pdo->prepare("
                SELECT o.*, u.name as customer_name 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if ($order) {
                // Get order items
                $stmt = $pdo->prepare("
                    SELECT oi.*, p.name as product_name 
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = ?
                ");
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
        } catch (PDOException $e) {
            error_log('Get order error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'update_order_status':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $status = sanitize($input['status'] ?? '');
        
        if (!$id || !$status) {
            $response = ['success' => false, 'message' => 'Invalid data'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $success = $stmt->execute([$status, $id]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Order status updated successfully'
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update order status'];
            }
        } catch (PDOException $e) {
            error_log('Update order status error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'delete_order':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid order ID'];
            break;
        }
        
        try {
            // Delete order items first
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$id]);
            
            // Then delete order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Order deleted successfully'
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to delete order'];
            }
        } catch (PDOException $e) {
            error_log('Delete order error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    // ========================================
    // CUSTOMER ACTIONS
    // ========================================
    
    case 'get_customer':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid customer ID'];
            break;
        }
        
        try {
            // Get customer details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            
            if ($customer) {
                // Get order stats
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as order_count, 
                        COALESCE(SUM(total), 0) as total_spent 
                    FROM orders 
                    WHERE user_id = ?
                ");
                $stmt->execute([$id]);
                $stats = $stmt->fetch();
                
                // Get recent orders
                $stmt = $pdo->prepare("
                    SELECT * FROM orders 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                $stmt->execute([$id]);
                $recent_orders = $stmt->fetchAll();
                
                $response = [
                    'success' => true,
                    'customer' => $customer,
                    'order_count' => $stats['order_count'] ?? 0,
                    'total_spent' => $stats['total_spent'] ?? 0,
                    'recent_orders' => $recent_orders
                ];
            } else {
                $response = ['success' => false, 'message' => 'Customer not found'];
            }
        } catch (PDOException $e) {
            error_log('Get customer error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'delete_customer':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid customer ID'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
            $success = $stmt->execute([$id]);
            
            if ($success && $stmt->rowCount() > 0) {
                $response = [
                    'success' => true,
                    'message' => 'Customer deleted successfully'
                ];
            } else {
                $response = ['success' => false, 'message' => 'Customer not found or cannot be deleted'];
            }
        } catch (PDOException $e) {
            error_log('Delete customer error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    // ========================================
    // CATEGORY ACTIONS
    // ========================================
    
    case 'get_category':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid category ID'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch();
            
            if ($category) {
                $response = [
                    'success' => true,
                    'category' => $category
                ];
            } else {
                $response = ['success' => false, 'message' => 'Category not found'];
            }
        } catch (PDOException $e) {
            error_log('Get category error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'add_category':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $name = sanitize($input['name'] ?? '');
        $slug = generateSlug($name);
        
        if (!$name) {
            $response = ['success' => false, 'message' => 'Category name is required'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $success = $stmt->execute([$name, $slug]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Category added successfully',
                    'id' => $pdo->lastInsertId()
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to add category'];
            }
        } catch (PDOException $e) {
            error_log('Add category error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'update_category':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        $slug = generateSlug($name);
        
        if (!$id || !$name) {
            $response = ['success' => false, 'message' => 'Invalid data'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
            $success = $stmt->execute([$name, $slug, $id]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Category updated successfully'
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update category'];
            }
        } catch (PDOException $e) {
            error_log('Update category error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'delete_category':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid category ID'];
            break;
        }
        
        try {
            // Check if category has products
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch()['count'];
            
            if ($count > 0) {
                $response = [
                    'success' => false, 
                    'message' => 'Cannot delete category with products. Move products first.'
                ];
                break;
            }
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Category deleted successfully'
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to delete category'];
            }
        } catch (PDOException $e) {
            error_log('Delete category error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    // ========================================
    // DASHBOARD ACTIONS
    // ========================================
    
    case 'get_stats':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        try {
            $stats = [];
            
            // Total products
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
            $stats['products'] = $stmt->fetch()['count'] ?? 0;
            
            // Total orders
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
            $stats['orders'] = $stmt->fetch()['count'] ?? 0;
            
            // Total revenue
            $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'");
            $stats['revenue'] = $stmt->fetch()['total'] ?? 0;
            
            // Total customers
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
            $stats['customers'] = $stmt->fetch()['count'] ?? 0;
            
            // Pending orders
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
            $stats['pending_orders'] = $stmt->fetch()['count'] ?? 0;
            
            $response = [
                'success' => true,
                'stats' => $stats
            ];
        } catch (PDOException $e) {
            error_log('Get stats error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    // ========================================
    // SEARCH ACTIONS
    // ========================================
    
    case 'search_products':
        $query = sanitize($_GET['q'] ?? '');
        
        if (strlen($query) < 2) {
            $response = ['success' => true, 'products' => []];
            break;
        }
        
        try {
            $search = "%{$query}%";
            $stmt = $pdo->prepare("
                SELECT * FROM products 
                WHERE name LIKE ? OR description LIKE ? 
                LIMIT 10
            ");
            $stmt->execute([$search, $search]);
            $products = $stmt->fetchAll();
            
            $response = [
                'success' => true,
                'products' => $products
            ];
        } catch (PDOException $e) {
            error_log('Search products error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    case 'search_orders':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Admin access required'];
            break;
        }
        
        $query = sanitize($_GET['q'] ?? '');
        
        if (strlen($query) < 2) {
            $response = ['success' => true, 'orders' => []];
            break;
        }
        
        try {
            $search = "%{$query}%";
            $stmt = $pdo->prepare("
                SELECT o.*, u.name as customer_name 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE o.id::text LIKE ? OR u.name LIKE ? OR o.status LIKE ?
                LIMIT 10
            ");
            $stmt->execute([$search, $search, $search]);
            $orders = $stmt->fetchAll();
            
            $response = [
                'success' => true,
                'orders' => $orders
            ];
        } catch (PDOException $e) {
            error_log('Search orders error: ' . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error'];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Action not found'];
}

// Return JSON response
echo json_encode($response);
