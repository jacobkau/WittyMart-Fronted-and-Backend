<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (ob_get_level()) {
    ob_clean();
}
ob_start();

header('Content-Type: application/json');

try {
    require_once 'config.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

// Include Cloudinary helpers so getProductImages() is available
if (file_exists(__DIR__ . '/cloudinary_helper.php')) {
    require_once __DIR__ . '/cloudinary_helper.php';
} elseif (file_exists(__DIR__ . '/../includes/cloudinary_helper.php')) {
    require_once __DIR__ . '/../includes/cloudinary_helper.php';
}

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

// ===== ADMIN ACTIONS =====
$admin_actions = [
    'get_order', 'get_customer', 'update_status',
    'get_product', 'get_stats', 'search_orders',
    'get_product_images', 'delete_product_image',
];

if (in_array($action, $admin_actions)) {
    if (!isAdmin()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
        exit;
    }
}

global $pdo;

try {
    switch ($action) {

        // ============================================
        // SEARCH PRODUCTS (header live search)
        // ============================================
        case 'search_products':
            $query = sanitize($_GET['q'] ?? '');
            if (strlen($query) < 2) {
                $response = ['success' => true, 'products' => []];
                break;
            }
            
            try {
                $search = "%{$query}%";
                $stmt = $pdo->prepare("
                    SELECT id, name, description, price, image, image_url 
                    FROM products 
                    WHERE (name ILIKE ? OR description ILIKE ?) 
                    AND (status = 'active' OR status IS NULL)
                    LIMIT 10
                ");
                $stmt->execute([$search, $search]);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'products' => $products];
            } catch (PDOException $e) {
                error_log('Search products error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // GET PRODUCT (admin edit)
        // ============================================
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
                    $response = ['success' => true, 'product' => $product];
                } else {
                    $response = ['success' => false, 'message' => 'Product not found'];
                }
            } catch (PDOException $e) {
                error_log('Get product error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // GET PRODUCT IMAGES (gallery)
        // ============================================
        case 'get_product_images':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                $response = ['success' => false, 'message' => 'Invalid product ID'];
                break;
            }
            try {
                $stmt = $pdo->prepare("
                    SELECT id, image_url, image_public_id, display_order, is_primary
                    FROM product_images
                    WHERE product_id = ?
                    ORDER BY is_primary DESC, display_order ASC, id ASC
                ");
                $stmt->execute([$id]);
                $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'images' => $images];
            } catch (PDOException $e) {
                error_log('Get product images error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // DELETE PRODUCT IMAGE (admin)
        // ============================================
        case 'delete_product_image':
            $image_id = intval($_POST['image_id'] ?? $_GET['image_id'] ?? 0);
            if (!$image_id) {
                $response = ['success' => false, 'message' => 'Invalid image ID'];
                break;
            }
            try {
                // Fetch image record
                $stmt = $pdo->prepare("
                    SELECT image_public_id, product_id, is_primary 
                    FROM product_images WHERE id = ?
                ");
                $stmt->execute([$image_id]);
                $img = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$img) {
                    $response = ['success' => false, 'message' => 'Image not found'];
                    break;
                }
                
                // Delete from Cloudinary (if it has a public_id)
                if (!empty($img['image_public_id'])) {
                    deleteFromCloudinary($img['image_public_id']);
                }
                
                // Delete DB row
                $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
                $stmt->execute([$image_id]);
                
                // If this was the primary, promote the next image
                if ($img['is_primary']) {
                    $stmt = $pdo->prepare("
                        UPDATE product_images 
                        SET is_primary = TRUE 
                        WHERE id = (
                            SELECT id FROM product_images 
                            WHERE product_id = ? 
                            ORDER BY display_order ASC, id ASC 
                            LIMIT 1
                        )
                    ");
                    $stmt->execute([$img['product_id']]);
                }
                
                $response = ['success' => true, 'message' => 'Image deleted'];
            } catch (PDOException $e) {
                error_log('Delete product image error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // GET ORDER (admin)
        // ============================================
        case 'get_order':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                $response = ['success' => false, 'message' => 'Invalid order ID'];
                break;
            }
            try {
                $stmt = $pdo->prepare("
                    SELECT o.*, u.name as customer_name, u.email as customer_email
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    WHERE o.id = ?
                ");
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($order) {
                    $stmt = $pdo->prepare("
                        SELECT oi.*, p.name as product_name 
                        FROM order_items oi
                        LEFT JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = ?
                    ");
                    $stmt->execute([$id]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $response = ['success' => true, 'order' => $order, 'items' => $items];
                } else {
                    $response = ['success' => false, 'message' => 'Order not found'];
                }
            } catch (PDOException $e) {
                error_log('Get order error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // GET CUSTOMER (admin)
        // ============================================
        case 'get_customer':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                $response = ['success' => false, 'message' => 'Invalid customer ID'];
                break;
            }
            try {
                $stmt = $pdo->prepare("
                    SELECT id, name, email, phone, role, status, created_at 
                    FROM users WHERE id = ? AND role = 'user'
                ");
                $stmt->execute([$id]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($customer) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent 
                        FROM orders WHERE user_id = ?
                    ");
                    $stmt->execute([$id]);
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    $response = [
                        'success'      => true,
                        'customer'     => $customer,
                        'order_count'  => $stats['order_count'] ?? 0,
                        'total_spent'  => $stats['total_spent'] ?? 0,
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Customer not found'];
                }
            } catch (PDOException $e) {
                error_log('Get customer error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // UPDATE ORDER STATUS
        // ============================================
        case 'update_status':
            $input  = json_decode(file_get_contents('php://input'), true);
            $id     = intval($input['id'] ?? 0);
            $status = sanitize($input['status'] ?? '');
            
            if (!$id || !$status) {
                $response = ['success' => false, 'message' => 'Invalid data'];
                break;
            }
            try {
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $success = $stmt->execute([$status, $id]);
                
                if ($success) {
                    logActivity('update_order_status',
                        'Updated order #' . $id . ' status to: ' . $status,
                        $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    $response = ['success' => true, 'message' => 'Order status updated'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to update status'];
                }
            } catch (PDOException $e) {
                error_log('Update status error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // SEARCH ORDERS
        // ============================================
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
                    WHERE o.order_number ILIKE ? OR u.name ILIKE ? OR o.status ILIKE ?
                    LIMIT 10
                ");
                $stmt->execute([$search, $search, $search]);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'orders' => $orders];
            } catch (PDOException $e) {
                error_log('Search orders error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // GET STATS
        // ============================================
        case 'get_stats':
            try {
                $stats = [];
                $stats['products']        = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
                $stats['orders']          = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                $stats['revenue']         = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
                $stats['customers']       = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
                $stats['pending_orders']  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
                
                $response = ['success' => true, 'stats' => $stats];
            } catch (PDOException $e) {
                error_log('Get stats error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // ADD TO CART
        // ============================================
        case 'add_to_cart':
            if (!isLoggedIn()) {
                $response = ['success' => false, 'message' => 'Please login first'];
                break;
            }
            
            // Accept both JSON and form-data
            $input = null;
            if (empty($_POST)) {
                $input = json_decode(file_get_contents('php://input'), true) ?: [];
            } else {
                $input = $_POST;
            }
            
            $product_id = intval($input['product_id'] ?? 0);
            $quantity   = intval($input['quantity'] ?? 1);
            
            if (!$product_id || $quantity <= 0) {
                $response = ['success' => false, 'message' => 'Invalid product data'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    $response = ['success' => false, 'message' => 'Product not found'];
                    break;
                }
                
                if ($product['stock'] < $quantity) {
                    $response = ['success' => false, 'message' => 'Not enough stock available'];
                    break;
                }
                
                $user_id = $_SESSION['user_id'];
                
                $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $new_quantity = $existing['quantity'] + $quantity;
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_quantity, $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $product_id, $quantity]);
                }
                
                $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $cart_count = intval($stmt->fetchColumn() ?? 0);
                
                $response = [
                    'success'    => true,
                    'message'    => 'Product added to cart',
                    'cart_count' => $cart_count,
                ];
            } catch (PDOException $e) {
                error_log('Add to cart error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;

        // ============================================
        // GET CART COUNT
        // ============================================
        case 'get_cart_count':
            if (!isLoggedIn()) {
                $response = ['success' => true, 'count' => 0];
                break;
            }
            try {
                $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $count = intval($stmt->fetchColumn() ?? 0);
                $response = ['success' => true, 'count' => $count];
            } catch (PDOException $e) {
                error_log('Get cart count error: ' . $e->getMessage());
                $response = ['success' => true, 'count' => 0];
            }
            break;

        default:
            $response = ['success' => false, 'message' => 'Action not found'];
    }
} catch (Exception $e) {
    error_log('AJAX Error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

ob_clean();
echo json_encode($response);
exit;
