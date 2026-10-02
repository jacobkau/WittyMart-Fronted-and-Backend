<?php

// Disable error display but log them
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Clear any previous output
if (ob_get_level()) {
    ob_clean();
}
ob_start();

// Set JSON header first
header('Content-Type: application/json');

try {
    require_once 'config.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

// Make sure Cloudinary helpers are loaded
$cloudinary_helper_paths = [
    __DIR__ . '/cloudinary_helper.php',
    __DIR__ . '/../includes/cloudinary_helper.php',
    __DIR__ . '/../../includes/cloudinary_helper.php',
];
foreach ($cloudinary_helper_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Get action from request
$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login']);
    exit;
}

// Check if user is admin for admin-only actions
$admin_actions = [
    'get_product', 'add_product', 'update_product', 'delete_product',
    'get_order', 'update_order_status', 'delete_order',
    'get_customer', 'delete_customer',
    'get_category', 'add_category', 'update_category', 'delete_category',
    'get_stats', 'search_orders', 'get_admin',
    'get_product_images', 'delete_product_image',
    'admin_search_products',
];

if (in_array($action, $admin_actions)) {
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    if (!$isAdmin) {
        ob_clean();
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
                    $response = ['success' => true, 'product' => $product];
                } else {
                    $response = ['success' => false, 'message' => 'Product not found'];
                }
            } catch (PDOException $e) {
                error_log('Get product error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
            break;

        // ========================================
        // PRODUCT IMAGES (GALLERY) ACTIONS
        // ========================================
        
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
                
                // Fallback: if no gallery rows, use the product's main image
                if (empty($images)) {
                    $stmt = $pdo->prepare("
                        SELECT image, image_url, image_public_id
                        FROM products WHERE id = ?
                    ");
                    $stmt->execute([$id]);
                    $p = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($p) {
                        $url = null;
                        if (!empty($p['image_url'])) {
                            $url = $p['image_url'];
                        } elseif (!empty($p['image'])) {
                            $url = (defined('UPLOAD_URL') ? UPLOAD_URL : '../uploads/products/') . $p['image'];
                        }
                        
                        if ($url) {
                            $images[] = [
                                'id' => 0,
                                'image_url' => $url,
                                'image_public_id' => $p['image_public_id'] ?? null,
                                'display_order' => 0,
                                'is_primary' => true,
                            ];
                        }
                    }
                }
                
                $response = ['success' => true, 'images' => $images];
            } catch (PDOException $e) {
                error_log('Get product images error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error'];
            }
            break;
        
        case 'delete_product_image':
            // Accept JSON body or form data
            $input = null;
            if (empty($_POST)) {
                $input = json_decode(file_get_contents('php://input'), true) ?: [];
            } else {
                $input = $_POST;
            }
            
            $image_id = intval($input['image_id'] ?? $_GET['image_id'] ?? 0);
            if (!$image_id) {
                $response = ['success' => false, 'message' => 'Invalid image ID'];
                break;
            }
            
            try {
                // Fetch image record
                $stmt = $pdo->prepare("
                    SELECT image_public_id, product_id, is_primary
                    FROM product_images
                    WHERE id = ?
                ");
                $stmt->execute([$image_id]);
                $img = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$img) {
                    $response = ['success' => false, 'message' => 'Image not found'];
                    break;
                }
                
                // Delete from Cloudinary
                if (!empty($img['image_public_id']) && function_exists('deleteFromCloudinary')) {
                    deleteFromCloudinary($img['image_public_id']);
                }
                
                // Delete DB row
                $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
                $stmt->execute([$image_id]);
                
                // If it was primary, promote the next image
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
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
            break;
        
        // ========================================
        // ADMIN LIVE SEARCH (products.php / manage_products.php)
        // ========================================
        
        case 'admin_search_products':
            $search        = trim($_GET['q'] ?? '');
            $filter_cat    = intval($_GET['category'] ?? 0);
            $filter_status = trim($_GET['status'] ?? '');
            $filter_stock  = trim($_GET['stock'] ?? '');
            $sort          = $_GET['sort'] ?? 'newest';
            $page          = max(1, intval($_GET['page'] ?? 1));
            $per_page      = 12;
            $offset        = ($page - 1) * $per_page;

            $where  = [];
            $params = [];

            if ($search !== '') {
                $where[] = "(p.name ILIKE ? OR p.description ILIKE ? OR p.sku ILIKE ? OR p.supplier ILIKE ?)";
                $s = "%$search%";
                array_push($params, $s, $s, $s, $s);
            }
            if ($filter_cat > 0) {
                $where[] = "p.category_id = ?";
                $params[] = $filter_cat;
            }
            if ($filter_status !== '') {
                $where[] = "p.status = ?";
                $params[] = $filter_status;
            }
            if ($filter_stock === 'in') {
                $where[] = "p.stock > 5";
            } elseif ($filter_stock === 'low') {
                $where[] = "p.stock > 0 AND p.stock <= 5";
            } elseif ($filter_stock === 'out') {
                $where[] = "(p.stock IS NULL OR p.stock <= 0)";
            }
            $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $order_sql = 'ORDER BY p.created_at DESC';
            switch ($sort) {
                case 'oldest':     $order_sql = 'ORDER BY p.created_at ASC'; break;
                case 'name':       $order_sql = 'ORDER BY p.name ASC'; break;
                case 'price_low':  $order_sql = 'ORDER BY p.price ASC'; break;
                case 'price_high': $order_sql = 'ORDER BY p.price DESC'; break;
                case 'stock_low':  $order_sql = 'ORDER BY p.stock ASC NULLS LAST'; break;
            }

            try {
                // Total count
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where_sql");
                $stmt->execute($params);
                $total = intval($stmt->fetchColumn());

                $total_pages = max(1, ceil($total / $per_page));
                if ($page > $total_pages) {
                    $page = $total_pages;
                    $offset = ($page - 1) * $per_page;
                }

                // Page of results
                $sql = "
                    SELECT p.*, c.name as category_name 
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    $where_sql
                    $order_sql
                    LIMIT $per_page OFFSET $offset
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Build HTML rows
                $html = '';
                foreach ($rows as $product) {
                    $img_src  = function_exists('getProductImage')
                                  ? getProductImage($product['image'] ?? null, $product['image_url'] ?? null)
                                  : (defined('UPLOAD_URL') ? UPLOAD_URL : '../uploads/products/') . 'no-image.png';
                    $is_cloud = !empty($product['image_url']) && strpos($product['image_url'], 'cloudinary.com') !== false;
                    $stock_val = intval($product['stock'] ?? 0);
                    $status_val = $product['status'] ?? 'active';

                    if ($stock_val <= 0) {
                        $stock_badge = '<span class="status-badge status-inactive">0</span>';
                    } elseif ($stock_val <= 5) {
                        $stock_badge = '<span class="status-badge status-draft">' . $stock_val . '</span>';
                    } else {
                        $stock_badge = '<span class="status-badge status-active">' . $stock_val . '</span>';
                    }

                    $html .= '<tr>'
                        . '<td>'
                        .   '<img src="' . htmlspecialchars($img_src) . '" alt="' . htmlspecialchars($product['name']) . '" class="product-image-thumb" onerror="this.src=\'../uploads/products/no-image.png\'">'
                        .   ($is_cloud ? '<br><span class="cloudinary-badge">Cloud</span>' : '')
                        . '</td>'
                        . '<td><strong>' . htmlspecialchars($product['name']) . '</strong></td>'
                        . '<td><code>' . htmlspecialchars($product['sku'] ?? 'N/A') . '</code></td>'
                        . '<td>Ksh ' . number_format($product['price'], 0) . '</td>'
                        . '<td>' . $stock_badge . '</td>'
                        . '<td>' . htmlspecialchars($product['supplier'] ?? 'N/A') . '</td>'
                        . '<td>' . htmlspecialchars($product['category_name'] ?? 'Uncategorized') . '</td>'
                        . '<td><span class="status-badge status-' . htmlspecialchars($status_val) . '">' . htmlspecialchars($status_val) . '</span></td>'
                        . '<td><div class="action-buttons">'
                        .   '<button class="btn-view" onclick="viewProduct(' . $product['id'] . ')" title="View details"><i class="fas fa-eye"></i></button>'
                        .   '<button class="btn-edit" onclick="editProduct(' . $product['id'] . ')" title="Edit"><i class="fas fa-edit"></i></button>'
                        .   '<form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this product?\')" style="display:inline;">'
                        .     '<input type="hidden" name="action" value="delete">'
                        .     '<input type="hidden" name="id" value="' . $product['id'] . '">'
                        .     '<button type="submit" class="btn-delete" title="Delete"><i class="fas fa-trash"></i></button>'
                        .   '</form>'
                        . '</div></td>'
                        . '</tr>';
                }

                $response = [
                    'success'     => true,
                    'html'        => $html,
                    'total'       => $total,
                    'shown'       => count($rows),
                    'total_pages' => $total_pages,
                    'page'        => $page,
                ];
            } catch (PDOException $e) {
                error_log('Admin search error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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
                    $response = ['success' => true, 'admin' => $admin];
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
                    $response = ['success' => true, 'message' => 'Product updated successfully'];
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
                $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
                $stmt->execute([$id]);
                if (!$stmt->fetch()) {
                    $response = ['success' => false, 'message' => 'Product not found'];
                    break;
                }
                
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $success = $stmt->execute([$id]);
                
                if ($success) {
                    $response = ['success' => true, 'message' => 'Product deleted successfully'];
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
                $stmt = $pdo->prepare("
                    SELECT o.*, u.name as customer_name 
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
                        JOIN products p ON oi.product_id = p.id 
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
                    $response = ['success' => true, 'message' => 'Order status updated successfully'];
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
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
                $stmt->execute([$id]);
                
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $success = $stmt->execute([$id]);
                
                if ($success) {
                    $response = ['success' => true, 'message' => 'Order deleted successfully'];
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
                    $response = ['success' => true, 'customer' => $customer];
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
                    $response = ['success' => true, 'message' => 'Customer deleted successfully'];
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
                    $response = ['success' => true, 'category' => $category];
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
                    $response = ['success' => true, 'message' => 'Category updated successfully'];
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
                    $response = ['success' => true, 'message' => 'Category deleted successfully'];
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
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
                $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
                $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'");
                $stats['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
                $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
                $stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                $response = ['success' => true, 'stats' => $stats];
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
                $response = ['success' => true, 'products' => $products];
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
                $response = ['success' => true, 'orders' => $orders];
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
