<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Clear any previous output
ob_clean();

// Set JSON header first
header('Content-Type: application/json');

try {
    require_once 'config.php';
    require_once 'auth.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login']);
    exit;
}

// Check if user is admin for admin-only actions
$admin_actions = ['get_product', 'add_product', 'update_product', 'delete_product', 'get_order', 'update_order_status', 'delete_order', 'get_customer', 'delete_customer', 'get_category', 'add_category', 'update_category', 'delete_category', 'get_stats', 'search_orders', 'get_admin'];
if (in_array($action, $admin_actions)) {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
}

global $pdo;

try {
    switch ($action) {
        // ========================================
        // PRODUCT ACTIONS
        // ========================================
        
        case 'get_product':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                $response = ['success' => false, 'message' => 'Invalid product ID'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
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
            
        case 'get_admin':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                $response = ['success' => false, 'message' => 'Invalid admin ID'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($admin) {
                    $response = [
                        'success' => true,
                        'admin' => $admin
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Admin not found'];
                }
            } catch (PDOException $e) {
                error_log('Get admin error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;
            
        case 'add_product':
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
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
            break;
            
        case 'update_product':
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
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            
            if (!$id) {
                $response = ['success' => false, 'message' => 'Invalid product ID'];
                break;
            }
            
            try {
                // Check if product exists
                $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
                $stmt->execute([$id]);
                if (!$stmt->fetch()) {
                    $response = ['success' => false, 'message' => 'Product not found'];
                    break;
                }
                
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
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($order) {
                    // Get order items
                    $stmt = $pdo->prepare("
                        SELECT oi.*, p.name as product_name 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        WHERE oi.order_id = ?
                    ");
                    $stmt->execute([$id]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
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
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                $response = ['success' => false, 'message' => 'Invalid customer ID'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
                $stmt->execute([$id]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($customer) {
                    $response = [
                        'success' => true,
                        'customer' => $customer
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
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                $response = ['success' => false, 'message' => 'Invalid category ID'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $category = $stmt->fetch(PDO::FETCH_ASSOC);
                
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
            $input = json_decode(file_get_contents('php://input'), true);
            $name = sanitize($input['name'] ?? '');
            
            if (!$name) {
                $response = ['success' => false, 'message' => 'Category name is required'];
                break;
            }
            
            try {
                $slug = generateSlug($name);
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
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $name = sanitize($input['name'] ?? '');
            
            if (!$id || !$name) {
                $response = ['success' => false, 'message' => 'Invalid data'];
                break;
            }
            
            try {
                $slug = generateSlug($name);
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
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
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
        // DASHBOARD STATS
        // ========================================
        
        case 'get_stats':
            try {
                $stats = [];
                
                // Total products
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
                $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                // Total orders
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
                $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                // Total revenue
                $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'");
                $stats['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Total customers
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
                $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                // Pending orders
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
                $stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
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
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
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
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
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
} catch (Exception $e) {
    error_log('AJAX Error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

// Clean output buffer and return JSON
ob_clean();
echo json_encode($response);
exit;
