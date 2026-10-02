<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/cloudinary_helper.php'; 
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

/**
 * Upload multiple files to Cloudinary.
 */
function uploadMultipleProductImages($files) {
    $uploaded = [];
    if (empty($files['name'][0])) return $uploaded;
    
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > 5 * 1024 * 1024) continue;
        
        $tmp = $files['tmp_name'][$i];
        $type = mime_content_type($tmp);
        if (!in_array($type, ['image/jpeg','image/png','image/gif','image/webp'])) continue;
        
        $result = uploadToCloudinary($tmp, 'products');
        if (!empty($result['success'])) {
            $uploaded[] = [
                'url'       => $result['url'],
                'public_id' => $result['public_id'],
            ];
        }
    }
    return $uploaded;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            // ============================================
            // ADD PRODUCT
            // ============================================
            case 'add':
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                $category_id = intval($_POST['category_id'] ?? 0);
                $status = sanitize($_POST['status'] ?? 'active');
                $stock = intval($_POST['stock'] ?? 0);
                $supplier = sanitize($_POST['supplier'] ?? '');
                $sku = sanitize(trim($_POST['sku'] ?? ''));
                
                if ($sku === '') {
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $category = $stmt->fetch();
                    $prefix = 'PRD';
                    if ($category) {
                        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category['name']), 0, 3));
                        $prefix = str_pad($prefix, 3, 'X');
                    }
                    do {
                        $sku = $prefix . '-' . random_int(100000, 999999);
                        $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
                        $check->execute([$sku]);
                    } while ($check->fetchColumn() > 0);
                }
                
                $image_url = null;
                $image_public_id = null;
                $image_name = null;
                $upload_message = '';
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadToCloudinary($_FILES['image']['tmp_name'], 'products');
                    
                    if ($upload_result['success']) {
                        $image_url = $upload_result['url'];
                        $image_public_id = $upload_result['public_id'];
                        $upload_message = 'Main image uploaded. ';
                        
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                        $image_name = time() . '_' . basename($_FILES['image']['name']);
                        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
                    }
                }
                
                $gallery_uploads = [];
                if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                    $gallery_uploads = uploadMultipleProductImages($_FILES['product_images']);
                    if (!empty($gallery_uploads)) {
                        $upload_message .= count($gallery_uploads) . ' gallery image(s) uploaded.';
                    }
                }
                
                if ($name && $price > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO products 
                        (name, description, price, category_id, image, image_url, image_public_id, status, stock, supplier, sku) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if ($stmt->execute([
                        $name, $description, $price, $category_id,
                        $image_name, $image_url, $image_public_id, $status,
                        $stock, $supplier, $sku
                    ])) {
                        $new_product_id = $pdo->lastInsertId();
                        
                        if ($image_url) {
                            try {
                                $stmtImg = $pdo->prepare("
                                    INSERT INTO product_images 
                                    (product_id, image_url, image_public_id, display_order, is_primary)
                                    VALUES (?, ?, ?, 0, TRUE)
                                ");
                                $stmtImg->execute([$new_product_id, $image_url, $image_public_id]);
                            } catch (PDOException $e) {
                                error_log('Insert primary image row error: ' . $e->getMessage());
                            }
                        }
                        
                        $order = 1;
                        foreach ($gallery_uploads as $img) {
                            try {
                                $stmtImg = $pdo->prepare("
                                    INSERT INTO product_images 
                                    (product_id, image_url, image_public_id, display_order, is_primary)
                                    VALUES (?, ?, ?, ?, FALSE)
                                ");
                                $stmtImg->execute([$new_product_id, $img['url'], $img['public_id'], $order++]);
                            } catch (PDOException $e) {
                                error_log('Insert gallery image error: ' . $e->getMessage());
                            }
                        }
                        
                        if (function_exists('logActivity')) {
                            logActivity('add_product', 'Added product: ' . $name . ' (SKU: ' . $sku . ')',
                                $_SESSION['user_id'], $_SESSION['user_name']);
                        }
                        $message = 'Product added successfully! ' . $upload_message;
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to add product.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Product name and price are required.';
                    $messageType = 'error';
                }
                break;
                
            // ============================================
            // EDIT PRODUCT
            // ============================================
            case 'edit':
                $id = intval($_POST['id'] ?? 0);
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                $category_id = intval($_POST['category_id'] ?? 0);
                $status = sanitize($_POST['status'] ?? 'active');
                $stock = intval($_POST['stock'] ?? 0);
                $supplier = sanitize($_POST['supplier'] ?? '');
                $sku = sanitize(trim($_POST['sku'] ?? ''));
                
                if ($sku === '') {
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $category = $stmt->fetch();
                    $prefix = 'PRD';
                    if ($category) {
                        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category['name']), 0, 3));
                        $prefix = str_pad($prefix, 3, 'X');
                    }
                    do {
                        $sku = $prefix . '-' . random_int(100000, 999999);
                        $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ? AND id != ?");
                        $check->execute([$sku, $id]);
                    } while ($check->fetchColumn() > 0);
                }
                
                $stmt = $pdo->prepare("SELECT image_public_id, image, image_url FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $existing_product = $stmt->fetch();
                
                $image_url = null;
                $image_public_id = null;
                $image_name = null;
                $upload_message = '';
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    if (!empty($existing_product['image_public_id'])) {
                        deleteFromCloudinary($existing_product['image_public_id']);
                    }
                    
                    $upload_result = uploadToCloudinary($_FILES['image']['tmp_name'], 'products');
                    
                    if ($upload_result['success']) {
                        $image_url = $upload_result['url'];
                        $image_public_id = $upload_result['public_id'];
                        $upload_message = 'New main image uploaded. ';
                        
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                        $image_name = time() . '_' . basename($_FILES['image']['name']);
                        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
                    }
                }
                
                if ($name && $price > 0 && $id) {
                    $sql = "UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, status = ?, stock = ?, supplier = ?, sku = ?";
                    $params = [$name, $description, $price, $category_id, $status, $stock, $supplier, $sku];
                    
                    if ($image_url !== null) {
                        $sql .= ", image = ?, image_url = ?, image_public_id = ?";
                        $params[] = $image_name;
                        $params[] = $image_url;
                        $params[] = $image_public_id;
                    }
                    
                    $sql .= " WHERE id = ?";
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        if ($image_url !== null) {
                            try {
                                $stmtUpd = $pdo->prepare("
                                    UPDATE product_images 
                                    SET image_url = ?, image_public_id = ?
                                    WHERE product_id = ? AND is_primary = TRUE
                                ");
                                $stmtUpd->execute([$image_url, $image_public_id, $id]);
                                
                                if ($stmtUpd->rowCount() === 0) {
                                    $stmtIns = $pdo->prepare("
                                        INSERT INTO product_images 
                                        (product_id, image_url, image_public_id, display_order, is_primary)
                                        VALUES (?, ?, ?, 0, TRUE)
                                    ");
                                    $stmtIns->execute([$id, $image_url, $image_public_id]);
                                }
                            } catch (PDOException $e) {
                                error_log('Sync primary image error: ' . $e->getMessage());
                            }
                        }
                        
                        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                            $new_gallery = uploadMultipleProductImages($_FILES['product_images']);
                            
                            if (!empty($new_gallery)) {
                                $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) FROM product_images WHERE product_id = ?");
                                $stmtMax->execute([$id]);
                                $next_order = intval($stmtMax->fetchColumn()) + 1;
                                
                                foreach ($new_gallery as $img) {
                                    try {
                                        $stmtIns = $pdo->prepare("
                                            INSERT INTO product_images 
                                            (product_id, image_url, image_public_id, display_order, is_primary)
                                            VALUES (?, ?, ?, ?, FALSE)
                                        ");
                                        $stmtIns->execute([$id, $img['url'], $img['public_id'], $next_order++]);
                                    } catch (PDOException $e) {
                                        error_log('Insert edit gallery image error: ' . $e->getMessage());
                                    }
                                }
                                $upload_message .= count($new_gallery) . ' gallery image(s) added.';
                            }
                        }
                        
                        if (function_exists('logActivity')) {
                            logActivity('update_product', 'Updated product: ' . $name . ' (ID: ' . $id . ')',
                                $_SESSION['user_id'], $_SESSION['user_name']);
                        }
                        $message = 'Product updated successfully! ' . $upload_message;
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update product.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Product name and price are required.';
                    $messageType = 'error';
                }
                break;
                
            // ============================================
            // DELETE PRODUCT
            // ============================================
            case 'delete':
                $id = intval($_POST['id']);
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE product_id = ?");
                $stmt->execute([$id]);
                $cart_count = $stmt->fetch()['count'];
                
                if ($cart_count > 0) {
                    $message = 'Cannot delete product as it is in a cart.';
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT image_public_id FROM product_images WHERE product_id = ?");
                        $stmt->execute([$id]);
                        foreach ($stmt->fetchAll() as $row) {
                            if (!empty($row['image_public_id'])) {
                                deleteFromCloudinary($row['image_public_id']);
                            }
                        }
                    } catch (PDOException $e) {
                        error_log('Delete gallery images error: ' . $e->getMessage());
                    }
                    
                    $stmt = $pdo->prepare("SELECT image_public_id FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $product = $stmt->fetch();
                    if (!empty($product['image_public_id'])) {
                        deleteFromCloudinary($product['image_public_id']);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        if (function_exists('logActivity')) {
                            logActivity('delete_product', 'Deleted product ID: ' . $id,
                                $_SESSION['user_id'], $_SESSION['user_name']);
                        }
                        $message = 'Product deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete product.';
                        $messageType = 'error';
                    }
                }
                break;
                
            // ============================================
            // DELETE A SINGLE GALLERY IMAGE
            // ============================================
            case 'delete_image':
                $img_id = intval($_POST['image_id'] ?? 0);
                
                if (!$img_id) {
                    $message = 'Invalid image ID';
                    $messageType = 'error';
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("SELECT image_public_id, product_id, is_primary FROM product_images WHERE id = ?");
                    $stmt->execute([$img_id]);
                    $img = $stmt->fetch();
                    
                    if (!$img) {
                        $message = 'Image not found';
                        $messageType = 'error';
                        break;
                    }
                    
                    if (!empty($img['image_public_id'])) {
                        deleteFromCloudinary($img['image_public_id']);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
                    $stmt->execute([$img_id]);
                    
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
                    
                    $message = 'Image deleted successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    error_log('Delete image error: ' . $e->getMessage());
                    $message = 'Database error: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    } catch (PDOException $e) {
        error_log('Product action error: ' . $e->getMessage());
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ============================================
// FILTERS + SEARCH + PAGINATION (server-side)
// ============================================
$search        = trim($_GET['q'] ?? '');
$filter_cat    = intval($_GET['category'] ?? 0);
$filter_status = trim($_GET['status'] ?? '');
$filter_stock  = trim($_GET['stock'] ?? '');
$sort          = $_GET['sort'] ?? 'newest';

$page      = max(1, intval($_GET['page'] ?? 1));
$per_page  = 12;
$offset    = ($page - 1) * $per_page;

$where = [];
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
    case 'newest':
    default:           $order_sql = 'ORDER BY p.created_at DESC'; break;
}

$total_products = 0;
try {
    $count_sql = "SELECT COUNT(*) FROM products p $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_products = intval($stmt->fetchColumn());
} catch (PDOException $e) {
    error_log('Count products error: ' . $e->getMessage());
}

$total_pages = max(1, ceil($total_products / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$products = [];
try {
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
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get products error: ' . $e->getMessage());
    $products = [];
}

// Stats
$stats = ['total' => 0, 'active' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
try {
    $stats['total'] = intval($pdo->query("SELECT COUNT(*) FROM products")->fetchColumn());
    $stats['active'] = intval($pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' OR status IS NULL")->fetchColumn());
    $stats['low_stock'] = intval($pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 5")->fetchColumn());
    $stats['out_of_stock'] = intval($pdo->query("SELECT COUNT(*) FROM products WHERE stock IS NULL OR stock <= 0")->fetchColumn());
} catch (PDOException $e) {
    error_log('Stats error: ' . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$edit_product = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_product = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get product for edit error: ' . $e->getMessage());
    }
}

function jsEscape($str) {
    if ($str === null) return '';
    $str = str_replace("\\", "\\\\", $str);
    $str = str_replace("'", "\\'", $str);
    $str = str_replace('"', '\\"', $str);
    $str = str_replace("\r", "\\r", $str);
    $str = str_replace("\n", "\\n", $str);
    $str = str_replace("\t", "\\t", $str);
    return $str;
}

function buildQueryString($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
        else $params[$k] = $v;
    }
    return http_build_query($params);
}

$page_title = 'Products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .product-image-thumb {
            width: 50px; height: 50px;
            object-fit: cover; border-radius: 4px;
        }
        .status-badge {
            padding: 3px 10px; border-radius: 12px;
            font-size: 12px; color: white;
            display: inline-block;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
        .status-draft { background-color: #ffc107; color: #333; }
        
        /* ============================================
           STATS ROW
           ============================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .stat-card {
            background: #fff;
            padding: 14px 18px;
            border-radius: 10px;
            border-left: 4px solid #05573c;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .stat-card .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
            background: #05573c;
            flex-shrink: 0;
        }
        
        .stat-card.warning .stat-icon { background: #fd7e14; }
        .stat-card.warning { border-left-color: #fd7e14; }
        .stat-card.danger .stat-icon { background: #dc3545; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.info .stat-icon { background: #17a2b8; }
        .stat-card.info { border-left-color: #17a2b8; }
        
        .stat-card .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #222;
            line-height: 1;
        }
        
        /* ============================================
           TOOLBAR
           ============================================ */
        .toolbar-card {
            background: #fff;
            border-radius: 10px;
            padding: 14px 18px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .toolbar-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .toolbar-search {
            flex: 1 1 260px;
            position: relative;
            min-width: 200px;
        }
        
        .toolbar-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 14px;
            pointer-events: none;
        }
        
        .toolbar-search input {
            width: 100%;
            padding: 9px 36px 9px 38px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background: #fafafa;
            transition: all 0.2s ease;
        }
        
        .toolbar-search input:focus {
            outline: none;
            border-color: #05573c;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }
        
        .toolbar-search .clear-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: #e0e0e0;
            border: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            cursor: pointer;
            color: #666;
            font-size: 11px;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .toolbar-search .clear-btn.visible { display: flex; }
        
        .toolbar-select {
            padding: 9px 32px 9px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fff;
            color: #333 !important;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20292.4%20292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 10px;
            min-width: 130px;
            opacity: 1;
        }
        
        .toolbar-select option {
            color: #333;
            background: #fff;
        }
        
        .toolbar-select.is-placeholder {
            color: #999 !important;
            font-weight: 400;
        }
        
        .toolbar-select:focus {
            outline: none;
            border-color: #05573c;
            background-color: #fff;
            color: #333 !important;
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }
        
        .toolbar-btn {
            padding: 9px 16px;
            border: none;
            border-radius: 8px;
            background: #05573c;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .toolbar-btn:hover { background: #03402c; }
        
        .toolbar-btn.secondary {
            background: #f0f0f0;
            color: #555;
        }
        
        .toolbar-btn.secondary:hover {
            background: #e0e0e0;
            color: #333;
        }
        
        .toolbar-results {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 4px;
            border-top: 1px solid #f0f0f0;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .toolbar-results strong {
            color: #05573c;
        }
        
        /* Loading state */
        .toolbar-search.loading input {
            background-image: linear-gradient(90deg, #fafafa 0%, #f0f0f0 50%, #fafafa 100%);
            background-size: 200% 100%;
            animation: shimmer 1.2s infinite;
        }
        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* ============================================
           TABLE CARD
           ============================================ */
        .table-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .table-card .table-inner {
            overflow-x: auto;
            padding: 4px 8px 8px;
            transition: opacity 0.2s ease;
        }
        
        /* ============================================
           PAGINATION
           ============================================ */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            border-top: 1px solid #f0f0f0;
            background: #fafafa;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .pagination-info {
            font-size: 13px;
            color: #666;
        }
        
        .pagination-info strong {
            color: #05573c;
        }
        
        .pagination-links {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 8px;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e0e0e0;
            color: #555;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.15s ease;
            cursor: pointer;
        }
        
        .page-link:hover {
            background: #e8f5f0;
            border-color: #05573c;
            color: #05573c;
        }
        
        .page-link.active {
            background: #05573c;
            border-color: #05573c;
            color: #fff;
            cursor: default;
        }
        
        .page-link.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .page-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            color: #999;
            font-weight: 600;
        }
        
        /* ============================================
           OTHER
           ============================================ */
        .form-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 15px;
        }
        .file-input-wrapper {
            position: relative; overflow: hidden;
            display: inline-block; width: 100%;
        }
        .file-input-wrapper input[type=file] {
            position: absolute; left: 0; top: 0;
            opacity: 0; width: 100%; height: 100%; cursor: pointer;
        }
        .btn-edit {
            background-color: #28a745; color: white;
            border: none; padding: 5px 10px;
            border-radius: 4px; cursor: pointer;
        }
        .btn-view {
            background-color: #17a2b8; color: white;
            border: none; padding: 5px 10px;
            border-radius: 4px; cursor: pointer;
        }
        .btn-delete {
            background-color: #dc3545; color: white;
            border: none; padding: 5px 10px;
            border-radius: 4px; cursor: pointer;
        }
        .action-buttons {
            display: flex; gap: 5px;
        }
        .action-buttons form { display: inline; }
        .btn-secondary {
            background-color: #6c757d; color: white;
            border: none; padding: 8px 16px;
            border-radius: 4px; cursor: pointer;
        }
        .btn-secondary:hover { background-color: #5a6268; }
        .cloudinary-badge {
            background-color: #3448C5; color: white;
            font-size: 10px; padding: 2px 6px;
            border-radius: 10px; margin-left: 5px;
        }
        
        /* Image preview grid */
        .image-preview-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            min-height: 0;
        }
        .preview-thumb, .existing-thumb {
            position: relative;
            width: 70px;
            height: 70px;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            background: #f5f5f5;
        }
        .preview-thumb img, .existing-thumb img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        .preview-thumb .thumb-label, .existing-thumb .thumb-label {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: rgba(5, 87, 60, 0.9);
            color: #fff;
            font-size: 9px;
            text-align: center;
            font-weight: 700;
            padding: 1px 0;
            letter-spacing: 0.3px;
        }
        .existing-thumb.primary { border-color: #05573c; }
        .existing-thumb .thumb-delete {
            position: absolute;
            top: 2px; right: 2px;
            background: rgba(220, 53, 69, 0.95);
            color: #fff;
            border: none;
            width: 20px; height: 20px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 11px;
            display: flex; align-items: center; justify-content: center;
            padding: 0;
            z-index: 2;
        }
        .existing-thumb .thumb-delete:hover { background: #dc3545; }
        .main-image-preview {
            margin-bottom: 8px;
        }
        .main-image-preview img {
            max-height: 100px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
        }
        
        /* ===== VIEW PRODUCT MODAL ===== */
        #viewProductModal .modal-content {
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .view-product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .view-gallery {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .view-main-image {
            position: relative;
            width: 100%;
            height: 340px;
            border-radius: 10px;
            overflow: hidden;
            background: #f5f5f5;
            cursor: zoom-in;
        }
        .view-main-image img {
            width: 100%; height: 100%;
            object-fit: contain;
            display: block;
            transition: opacity 0.25s ease;
        }
        .view-image-counter {
            position: absolute; top: 10px; left: 10px;
            background: rgba(0,0,0,0.7); color: #fff;
            font-size: 11px; padding: 4px 10px;
            border-radius: 12px; font-weight: 600;
            display: flex; align-items: center; gap: 5px;
        }
        .view-gallery-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.9); color: #333;
            border: none; width: 36px; height: 36px;
            border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .view-gallery-nav:hover { background: #05573c; color: #fff; }
        .view-gallery-nav.prev { left: 10px; }
        .view-gallery-nav.next { right: 10px; }
        .view-thumbs {
            display: flex; gap: 8px;
            overflow-x: auto; padding: 4px 2px;
        }
        .view-thumb {
            flex: 0 0 64px; width: 64px; height: 64px;
            border-radius: 6px; overflow: hidden;
            cursor: pointer; border: 2px solid transparent;
            transition: all 0.2s ease; background: #f5f5f5;
        }
        .view-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .view-thumb.active { border-color: #05573c; }
        .view-thumb:hover { transform: translateY(-2px); }
        .view-details h2 {
            margin: 0 0 8px 0; color: #222; font-size: 22px;
        }
        .view-details .view-price {
            font-size: 26px; font-weight: 700;
            color: #05573c; margin: 6px 0 14px;
        }
        .view-meta {
            display: flex; flex-wrap: wrap;
            gap: 8px; margin-bottom: 14px;
        }
        .view-meta-badge {
            background: #f0f0f0; padding: 4px 12px;
            border-radius: 12px; font-size: 12px;
            color: #555; display: inline-flex;
            align-items: center; gap: 5px;
        }
        .view-meta-badge.cloud { background: #e7eaff; color: #3448C5; font-weight: 600; }
        .view-section-title {
            font-size: 12px; text-transform: uppercase;
            letter-spacing: 0.5px; color: #888;
            margin: 14px 0 6px; font-weight: 700;
        }
        .view-description {
            color: #444; line-height: 1.7;
            background: #f8f9fa; padding: 12px;
            border-radius: 8px; font-size: 14px;
            white-space: pre-wrap;
        }
        .view-data-row {
            display: flex; justify-content: space-between;
            padding: 6px 0; border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .view-data-row:last-child { border-bottom: none; }
        .view-data-row .label { color: #888; }
        .view-data-row .value { color: #333; font-weight: 600; }
        
        /* Lightbox */
        .adm-lightbox {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.95);
            z-index: 100000;
            justify-content: center; align-items: center;
            padding: 20px;
        }
        .adm-lightbox.active { display: flex; }
        .adm-lightbox img {
            max-width: 95vw; max-height: 90vh;
            object-fit: contain; border-radius: 6px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        }
        .adm-lightbox .adm-lb-close {
            position: absolute; top: 20px; right: 24px;
            background: rgba(255,255,255,0.15); color: #fff;
            border: none; width: 44px; height: 44px;
            border-radius: 50%; cursor: pointer; font-size: 22px;
            display: flex; align-items: center; justify-content: center;
        }
        .adm-lightbox .adm-lb-close:hover { background: rgba(255,255,255,0.3); }
        .adm-lb-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.15); color: #fff;
            border: none; width: 50px; height: 50px;
            border-radius: 50%; cursor: pointer; font-size: 20px;
            display: flex; align-items: center; justify-content: center;
        }
        .adm-lb-nav:hover { background: rgba(255,255,255,0.3); }
        .adm-lb-nav.prev { left: 20px; }
        .adm-lb-nav.next { right: 20px; }
        
        @media (max-width: 768px) {
            .view-product-grid { grid-template-columns: 1fr; }
            .view-main-image { height: 240px; }
            .toolbar-row { flex-direction: column; align-items: stretch; }
            .toolbar-select { width: 100%; }
        }
    </style>
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
        <?php include "sidebar.php" ?>

        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <h1 style="margin:0; font-size:22px;">
                        <i class="fas fa-box"></i> Products
                    </h1>
                    <p style="margin:4px 0 0; color:#888; font-size:13px;">
                        Manage your product catalog, images, and stock.
                    </p>
                </div>
                <button class="btn-primary" onclick="openModal('addProductModal')">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- STATS ROW -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">Total Products</div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                    </div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">Active</div>
                        <div class="stat-value"><?php echo $stats['active']; ?></div>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">Low Stock</div>
                        <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                    </div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">Out of Stock</div>
                        <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
                    </div>
                </div>
            </div>

            <!-- TOOLBAR -->
            <div class="toolbar-card">
                <form method="GET" action="manage_products.php" id="filterForm" onsubmit="return false;">
                    <div class="toolbar-row">
                        <div class="toolbar-search" id="searchWrap">
                            <i class="fas fa-search"></i>
                            <input type="text"
                                   name="q"
                                   id="searchInput"
                                   placeholder="Search products as you type..."
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   autocomplete="off">
                            <button type="button" class="clear-btn <?php echo $search !== '' ? 'visible' : ''; ?>" id="clearSearchBtn" title="Clear">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <select name="category" class="toolbar-select <?php echo $filter_cat == 0 ? 'is-placeholder' : ''; ?>">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $filter_cat == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="toolbar-select <?php echo $filter_status === '' ? 'is-placeholder' : ''; ?>">
                            <option value="">All Status</option>
                            <option value="active"   <?php echo $filter_status === 'active'   ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="draft"    <?php echo $filter_status === 'draft'    ? 'selected' : ''; ?>>Draft</option>
                        </select>
                        
                        <select name="stock" class="toolbar-select <?php echo $filter_stock === '' ? 'is-placeholder' : ''; ?>">
                            <option value="">All Stock</option>
                            <option value="in"  <?php echo $filter_stock === 'in'  ? 'selected' : ''; ?>>In Stock (&gt;5)</option>
                            <option value="low" <?php echo $filter_stock === 'low' ? 'selected' : ''; ?>>Low Stock (1–5)</option>
                            <option value="out" <?php echo $filter_stock === 'out' ? 'selected' : ''; ?>>Out of Stock (0)</option>
                        </select>
                        
                        <select name="sort" class="toolbar-select">
                            <option value="newest"     <?php echo $sort === 'newest'     ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest"     <?php echo $sort === 'oldest'     ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name"       <?php echo $sort === 'name'       ? 'selected' : ''; ?>>Name (A–Z)</option>
                            <option value="price_low"  <?php echo $sort === 'price_low'  ? 'selected' : ''; ?>>Price (Low → High)</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price (High → Low)</option>
                            <option value="stock_low"  <?php echo $sort === 'stock_low'  ? 'selected' : ''; ?>>Lowest Stock</option>
                        </select>
                        
                        <?php if ($search || $filter_cat || $filter_status || $filter_stock || $sort !== 'newest'): ?>
                            <a href="manage_products.php" class="toolbar-btn secondary">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <div class="toolbar-results">
                    <span id="resultsCount">
                        Showing <strong><?php echo count($products); ?></strong>
                        of <strong><?php echo $total_products; ?></strong> products
                        <?php if ($search || $filter_cat || $filter_status || $filter_stock): ?>
                            (filtered)
                        <?php endif; ?>
                    </span>
                    <?php if ($total_pages > 1): ?>
                        <span id="paginationInfo">Page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong></span>
                    <?php else: ?>
                        <span id="paginationInfo"></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TABLE CARD -->
            <div class="table-card">
                <div id="tableContainer">
                <?php if (count($products) > 0): ?>
                    <div class="table-inner">
                        <table class="admin-table" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Supplier</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <?php 
                                    $img_src = getProductImage($product['image'] ?? null, $product['image_url'] ?? null);
                                    $is_cloudinary = !empty($product['image_url']) && strpos($product['image_url'], 'cloudinary.com') !== false;
                                    $stock_val = intval($product['stock'] ?? 0);
                                    $status_val = $product['status'] ?? 'active';
                                    ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($img_src); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="product-image-thumb"
                                                 onerror="this.src='../uploads/products/no-image.png'">
                                            <?php if ($is_cloudinary): ?>
                                                <br><span class="cloudinary-badge">Cloud</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></code></td>
                                        <td>Ksh <?php echo number_format($product['price'], 0); ?></td>
                                        <td>
                                            <?php if ($stock_val <= 0): ?>
                                                <span class="status-badge status-inactive">0</span>
                                            <?php elseif ($stock_val <= 5): ?>
                                                <span class="status-badge status-draft"><?php echo $stock_val; ?></span>
                                            <?php else: ?>
                                                <span class="status-badge status-active"><?php echo $stock_val; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['supplier'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($status_val); ?>">
                                                <?php echo htmlspecialchars($status_val); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-view" onclick="viewProduct(<?php echo $product['id']; ?>)" title="View details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-edit" onclick="editProduct(<?php echo $product['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" class="btn-delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- PAGINATION -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Showing <strong><?php echo $offset + 1; ?></strong>–<strong><?php echo min($offset + $per_page, $total_products); ?></strong>
                                of <strong><?php echo $total_products; ?></strong>
                            </div>
                            
                            <div class="pagination-links">
                                <?php $prev_disabled = $page <= 1; ?>
                                <a href="#" data-page="<?php echo $page - 1; ?>"
                                   class="page-link <?php echo $prev_disabled ? 'disabled' : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                
                                <?php
                                $range = 2;
                                $start = max(1, $page - $range);
                                $end = min($total_pages, $page + $range);
                                
                                if ($start > 1) {
                                    echo '<a href="#" data-page="1" class="page-link">1</a>';
                                    if ($start > 2) echo '<span class="page-ellipsis">…</span>';
                                }
                                
                                for ($i = $start; $i <= $end; $i++) {
                                    $active = $i === $page;
                                    echo '<a href="#" data-page="' . $i . '" class="page-link ' . ($active ? 'active' : '') . '">' . $i . '</a>';
                                }
                                
                                if ($end < $total_pages) {
                                    if ($end < $total_pages - 1) echo '<span class="page-ellipsis">…</span>';
                                    echo '<a href="#" data-page="' . $total_pages . '" class="page-link">' . $total_pages . '</a>';
                                }
                                
                                $next_disabled = $page >= $total_pages;
                                ?>
                                <a href="#" data-page="<?php echo $page + 1; ?>"
                                   class="page-link <?php echo $next_disabled ? 'disabled' : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:60px 20px; color:#888;">
                        <i class="fas fa-box-open" style="font-size:56px; display:block; margin-bottom:16px; opacity:0.3;"></i>
                        <h3 style="margin:0 0 8px; color:#555;">No products found</h3>
                        <p style="margin:0;">
                            <?php if ($search || $filter_cat || $filter_status || $filter_stock): ?>
                                Try adjusting your filters or <a href="manage_products.php" style="color:#05573c; font-weight:600;">reset</a>.
                            <?php else: ?>
                                Click "Add Product" to get started.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- VIEW PRODUCT MODAL -->
    <div id="viewProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Product Details</h2>
                <span class="close" onclick="closeModal('viewProductModal')">&times;</span>
            </div>
            <div id="viewProductContent">
                <p style="text-align:center;padding:40px;color:#888;">
                    <i class="fas fa-spinner fa-spin"></i> Loading product…
                </p>
            </div>
        </div>
    </div>

    <!-- ADMIN LIGHTBOX -->
    <div class="adm-lightbox" id="admLightbox">
        <button class="adm-lb-close" onclick="closeAdmLightbox()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
        <button class="adm-lb-nav prev" onclick="admLightboxPrev()" aria-label="Previous">
            <i class="fas fa-chevron-left"></i>
        </button>
        <img src="" alt="Preview" id="admLightboxImg">
        <button class="adm-lb-nav next" onclick="admLightboxNext()" aria-label="Next">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <!-- ADD PRODUCT MODAL -->
    <div id="addProductModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add Product</h2>
                <span class="close" onclick="closeModal('addProductModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Product Name *</label>
                    <input type="text" name="name" required placeholder="Enter product name">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> SKU (Optional — auto-generated if blank)</label>
                    <input type="text" name="sku" placeholder="e.g., PRD-001">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Price (Ksh) *</label>
                        <input type="number" name="price" required step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-folder"></i> Category</label>
                        <select name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" rows="3" placeholder="Enter product description"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-cubes"></i> Stock Quantity</label>
                        <input type="number" name="stock" value="0">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-truck"></i> Supplier / Seller</label>
                        <input type="text" name="supplier" placeholder="Enter supplier name">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> Main Image</label>
                        <div class="file-input-wrapper">
                            <button type="button" class="btn-secondary" style="width:100%;">
                                <i class="fas fa-upload"></i> Choose Main Image
                            </button>
                            <input type="file" name="image" accept="image/*" onchange="previewImages(this, 'addMainPreview')">
                        </div>
                        <div class="image-preview-grid" id="addMainPreview"></div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-images"></i> Additional Gallery Images</label>
                    <div class="file-input-wrapper">
                        <button type="button" class="btn-secondary" style="width:100%;">
                            <i class="fas fa-upload"></i> Choose Multiple Images
                        </button>
                        <input type="file" name="product_images[]" accept="image/*" multiple onchange="previewImages(this, 'addGalleryPreview', true)">
                    </div>
                    <div class="image-preview-grid" id="addGalleryPreview"></div>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Add Product
                </button>
            </form>
        </div>
    </div>

    <!-- EDIT PRODUCT MODAL -->
    <div id="editProductModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Product</h2>
                <span class="close" onclick="closeModal('editProductModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editProductId">
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Product Name *</label>
                    <input type="text" name="name" id="editProductName" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> SKU</label>
                    <input type="text" name="sku" id="editProductSku" placeholder="e.g., PRD-001">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Price (Ksh) *</label>
                        <input type="number" name="price" id="editProductPrice" required step="0.01">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-folder"></i> Category</label>
                        <select name="category_id" id="editProductCategory">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" id="editProductDescription" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-cubes"></i> Stock Quantity</label>
                        <input type="number" name="stock" id="editProductStock" value="0">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-truck"></i> Supplier / Seller</label>
                        <input type="text" name="supplier" id="editProductSupplier">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> Main Image</label>
                        <div class="main-image-preview" id="editProductImagePreview"></div>
                        <div class="file-input-wrapper">
                            <button type="button" class="btn-secondary" style="width:100%;">
                                <i class="fas fa-upload"></i> Change Main Image
                            </button>
                            <input type="file" name="image" accept="image/*" onchange="previewImages(this, 'editMainPreview')">
                        </div>
                        <div class="image-preview-grid" id="editMainPreview"></div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status" id="editProductStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-images"></i> Existing Gallery</label>
                    <div class="image-preview-grid" id="editExistingGallery"></div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-plus-circle"></i> Add More Gallery Images</label>
                    <div class="file-input-wrapper">
                        <button type="button" class="btn-secondary" style="width:100%;">
                            <i class="fas fa-upload"></i> Choose Multiple Images
                        </button>
                        <input type="file" name="product_images[]" accept="image/*" multiple onchange="previewImages(this, 'editNewGalleryPreview', true)">
                    </div>
                    <div class="image-preview-grid" id="editNewGalleryPreview"></div>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Update Product
                </button>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // PRODUCT DATA FOR EDIT / VIEW
        // ============================================
        var productData = {};
        
        <?php foreach ($products as $product): ?>
            productData[<?php echo $product['id']; ?>] = {
                id: <?php echo $product['id']; ?>,
                name: '<?php echo jsEscape($product['name']); ?>',
                description: '<?php echo jsEscape($product['description'] ?? ''); ?>',
                price: '<?php echo $product['price']; ?>',
                category_id: '<?php echo $product['category_id'] ?? ''; ?>',
                category_name: '<?php echo jsEscape($product['category_name'] ?? 'Uncategorized'); ?>',
                status: '<?php echo jsEscape($product['status'] ?? 'active'); ?>',
                stock: '<?php echo intval($product['stock'] ?? 0); ?>',
                sku: '<?php echo jsEscape($product['sku'] ?? ''); ?>',
                supplier: '<?php echo jsEscape($product['supplier'] ?? ''); ?>',
                image: '<?php echo $product['image'] ? jsEscape($product['image']) : ''; ?>',
                image_url: '<?php echo $product['image_url'] ? jsEscape($product['image_url']) : ''; ?>'
            };
        <?php endforeach; ?>
        
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // ============================================
        // LIVE SEARCH — debounced AJAX, no page reload
        // ============================================
        (function() {
            const input         = document.getElementById('searchInput');
            const clearBtn      = document.getElementById('clearSearchBtn');
            const form          = document.getElementById('filterForm');
            const tableContainer = document.getElementById('tableContainer');
            const resultsCount   = document.getElementById('resultsCount');
            const searchWrap     = document.getElementById('searchWrap');
            
            if (!input || !form || !tableContainer) return;
            
            let debounceTimer = null;
            
            function updateClearBtn() {
                if (clearBtn) clearBtn.classList.toggle('visible', input.value.length > 0);
            }
            updateClearBtn();
            
            function buildQuery() {
                const data = new FormData(form);
                const params = new URLSearchParams();
                for (const [k, v] of data.entries()) {
                    if (v !== '') params.append(k, v);
                }
                return params.toString();
            }
            
            function liveSearch() {
                const query = buildQuery();
                
                // Loading shimmer
                searchWrap.classList.add('loading');
                tableContainer.style.opacity = '0.5';
                
                fetch('includes/ajax.php?action=admin_search_products&' + query)
                    .then(r => r.json())
                    .then(res => {
                        searchWrap.classList.remove('loading');
                        tableContainer.style.opacity = '1';
                        
                        if (!res.success) {
                            console.warn('Search failed:', res.message);
                            return;
                        }
                        
                        // Rebuild the whole tableContainer
                        if (res.shown === 0) {
                            tableContainer.innerHTML = `
                                <div style="text-align:center; padding:60px 20px; color:#888;">
                                    <i class="fas fa-box-open" style="font-size:56px; display:block; margin-bottom:16px; opacity:0.3;"></i>
                                    <h3 style="margin:0 0 8px; color:#555;">No products found</h3>
                                    <p style="margin:0;">Try adjusting your search or filters.</p>
                                </div>
                            `;
                        } else {
                            // Build pagination HTML
                            let paginationHtml = '';
                            if (res.total_pages > 1) {
                                const startOffset = (res.page - 1) * 12 + 1;
                                const endOffset = Math.min(res.page * 12, res.total);
                                
                                let linksHtml = '';
                                linksHtml += `<a href="#" class="page-link ${res.page <= 1 ? 'disabled' : ''}" data-page="${res.page - 1}"><i class="fas fa-chevron-left"></i></a>`;
                                
                                const range = 2;
                                const startP = Math.max(1, res.page - range);
                                const endP = Math.min(res.total_pages, res.page + range);
                                
                                if (startP > 1) {
                                    linksHtml += `<a href="#" class="page-link" data-page="1">1</a>`;
                                    if (startP > 2) linksHtml += `<span class="page-ellipsis">…</span>`;
                                }
                                for (let i = startP; i <= endP; i++) {
                                    const active = i === res.page;
                                    linksHtml += `<a href="#" class="page-link ${active ? 'active' : ''}" data-page="${i}">${i}</a>`;
                                }
                                if (endP < res.total_pages) {
                                    if (endP < res.total_pages - 1) linksHtml += `<span class="page-ellipsis">…</span>`;
                                    linksHtml += `<a href="#" class="page-link" data-page="${res.total_pages}">${res.total_pages}</a>`;
                                }
                                linksHtml += `<a href="#" class="page-link ${res.page >= res.total_pages ? 'disabled' : ''}" data-page="${res.page + 1}"><i class="fas fa-chevron-right"></i></a>`;
                                
                                paginationHtml = `
                                    <div class="pagination">
                                        <div class="pagination-info">
                                            Showing <strong>${startOffset}</strong>–<strong>${endOffset}</strong> of <strong>${res.total}</strong>
                                        </div>
                                        <div class="pagination-links">${linksHtml}</div>
                                    </div>
                                `;
                            }
                            
                            tableContainer.innerHTML = `
                                <div class="table-inner">
                                    <table class="admin-table" id="productsTable">
                                        <thead>
                                            <tr>
                                                <th>Image</th>
                                                <th>Name</th>
                                                <th>SKU</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Supplier</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>${res.html}</tbody>
                                    </table>
                                </div>
                                ${paginationHtml}
                            `;
                            
                            // Attach pagination listeners
                            tableContainer.querySelectorAll('.pagination-links .page-link').forEach(link => {
                                link.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    if (this.classList.contains('disabled') || this.classList.contains('active')) return;
                                    const pageNum = this.dataset.page;
                                    // Update hidden page input if present, else append to URL
                                    const url = new URL(window.location.href);
                                    url.searchParams.set('page', pageNum);
                                    // Preserve current filters
                                    const curParams = new URLSearchParams(buildQuery());
                                    curParams.forEach((v, k) => url.searchParams.set(k, v));
                                    url.searchParams.set('page', pageNum);
                                    history.replaceState({}, '', url);
                                    liveSearch();
                                });
                            });
                        }
                        
                        // Update results text
                        if (resultsCount) {
                            resultsCount.innerHTML =
                                'Showing <strong>' + res.shown + '</strong> of <strong>' + res.total + '</strong> products' +
                                (query ? ' (filtered)' : '');
                        }
                        
                        // Update URL without reload
                        const newUrl = window.location.pathname + (query ? '?' + query : '');
                        history.replaceState({}, '', newUrl);
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        searchWrap.classList.remove('loading');
                        tableContainer.style.opacity = '1';
                    });
            }
            
            // Debounced input
            input.addEventListener('input', function() {
                updateClearBtn();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(liveSearch, 400);
            });
            
            // Clear
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    input.value = '';
                    updateClearBtn();
                    clearTimeout(debounceTimer);
                    liveSearch();
                    input.focus();
                });
            }
            
            // Enter — immediate
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(debounceTimer);
                    liveSearch();
                }
            });
            
            // Dropdown change — immediate
            form.querySelectorAll('select').forEach(sel => {
                sel.addEventListener('change', function() {
                    // Update is-placeholder visual
                    if (this.name === 'category' || this.name === 'status' || this.name === 'stock') {
                        this.classList.toggle('is-placeholder', this.value === '');
                    }
                    clearTimeout(debounceTimer);
                    liveSearch();
                });
            });
        })();
        
        // ============================================
        // LIVE IMAGE PREVIEW
        // ============================================
        function previewImages(input, previewContainerId, multiple) {
            const container = document.getElementById(previewContainerId);
            if (!container) return;
            container.innerHTML = '';
            
            if (!input.files || input.files.length === 0) return;
            
            const files = multiple ? Array.from(input.files) : [input.files[0]];
            
            files.forEach((file, index) => {
                if (!file.type.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    const wrap = document.createElement('div');
                    wrap.className = 'preview-thumb';
                    wrap.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        ${multiple ? `<span class="thumb-label">#${index + 1}</span>` : ''}
                    `;
                    container.appendChild(wrap);
                };
                reader.readAsDataURL(file);
            });
        }
        
        // ============================================
        // LOAD EXISTING GALLERY
        // ============================================
        function loadExistingGallery(productId) {
            const container = document.getElementById('editExistingGallery');
            container.innerHTML = '<small style="color:#888;">Loading…</small>';
            
            fetch('includes/ajax.php?action=get_product_images&id=' + productId)
                .then(r => r.json())
                .then(res => {
                    container.innerHTML = '';
                    if (!res.success || !res.images || !res.images.length) {
                        container.innerHTML = '<small style="color:#888;">No gallery images</small>';
                        return;
                    }
                    
                    res.images.forEach(img => {
                        const isPrimary = img.is_primary == 1 || img.is_primary === true;
                        const wrap = document.createElement('div');
                        wrap.className = 'existing-thumb' + (isPrimary ? ' primary' : '');
                        wrap.innerHTML = `
                            <img src="${img.image_url}" alt="Gallery image">
                            ${isPrimary ? '<span class="thumb-label">MAIN</span>' : ''}
                            ${!isPrimary ? `<button type="button" class="thumb-delete" onclick="deleteGalleryImage(${img.id})" title="Delete">×</button>` : ''}
                        `;
                        container.appendChild(wrap);
                    });
                })
                .catch(err => {
                    console.error(err);
                    container.innerHTML = '<small style="color:#c00;">Failed to load gallery</small>';
                });
        }
        
        function deleteGalleryImage(imageId) {
            if (!confirm('Delete this gallery image?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_image');
            fd.append('image_id', imageId);
            fetch('manage_products.php', { method: 'POST', body: fd })
                .then(() => {
                    const pid = document.getElementById('editProductId').value;
                    loadExistingGallery(pid);
                })
                .catch(err => { console.error(err); alert('Failed to delete image'); });
        }
        
        // ============================================
        // VIEW PRODUCT MODAL
        // ============================================
        let viewGallery = [];
        let viewGalleryIndex = 0;
        
        function viewProduct(productId) {
            const data = productData[productId];
            if (!data) { alert('Product not found'); return; }
            
            document.getElementById('viewProductContent').innerHTML =
                '<p style="text-align:center;padding:40px;color:#888;">' +
                '<i class="fas fa-spinner fa-spin"></i> Loading gallery…</p>';
            openModal('viewProductModal');
            
            fetch('includes/ajax.php?action=get_product_images&id=' + productId)
                .then(r => r.json())
                .then(res => {
                    let images = [];
                    if (res.success && res.images && res.images.length) {
                        images = res.images.map(i => i.image_url);
                    } else {
                        if (data.image_url) images.push(data.image_url);
                        else if (data.image) images.push('../uploads/products/' + data.image);
                        else images.push('../uploads/products/no-image.png');
                    }
                    viewGallery = images;
                    viewGalleryIndex = 0;
                    renderViewProduct(data);
                })
                .catch(() => {
                    viewGallery = [];
                    if (data.image_url) viewGallery.push(data.image_url);
                    else if (data.image) viewGallery.push('../uploads/products/' + data.image);
                    else viewGallery.push('../uploads/products/no-image.png');
                    viewGalleryIndex = 0;
                    renderViewProduct(data);
                });
        }
        
        function renderViewProduct(data) {
            const hasMultiple = viewGallery.length > 1;
            const isCloudinary = data.image_url && data.image_url.includes('cloudinary.com');
            const stockVal = parseInt(data.stock) || 0;
            
            let thumbsHtml = '';
            if (hasMultiple) {
                thumbsHtml = '<div class="view-thumbs" id="viewThumbs">';
                viewGallery.forEach((url, i) => {
                    thumbsHtml += '<div class="view-thumb ' + (i === 0 ? 'active' : '') + '" data-index="' + i + '">' +
                                  '<img src="' + url + '" alt="Image ' + (i+1) + '"></div>';
                });
                thumbsHtml += '</div>';
            }
            
            const html = `
                <div class="view-product-grid">
                    <div class="view-gallery">
                        <div class="view-main-image" onclick="openAdmLightbox(${viewGalleryIndex})">
                            <img src="${viewGallery[0]}" alt="${escapeHtml(data.name)}" id="viewMainImage">
                            <span class="view-image-counter">
                                <i class="fas fa-images"></i>
                                <span id="viewImageCounter">1</span> / ${viewGallery.length}
                            </span>
                            ${hasMultiple ? `
                                <button type="button" class="view-gallery-nav prev" onclick="event.stopPropagation();viewGalleryPrev()">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button type="button" class="view-gallery-nav next" onclick="event.stopPropagation();viewGalleryNext()">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            ` : ''}
                        </div>
                        ${thumbsHtml}
                    </div>
                    <div class="view-details">
                        <h2>${escapeHtml(data.name)}</h2>
                        <div class="view-price">Ksh ${parseFloat(data.price).toLocaleString()}</div>
                        <div class="view-meta">
                            <span class="view-meta-badge">
                                <i class="fas fa-tag"></i> ${escapeHtml(data.category_name || 'Uncategorized')}
                            </span>
                            <span class="view-meta-badge" style="${stockVal > 0 ? 'background:#d4edda;color:#155724;' : 'background:#f8d7da;color:#721c24;'}">
                                <i class="fas fa-cubes"></i> ${stockVal > 0 ? 'In Stock (' + stockVal + ')' : 'Out of Stock'}
                            </span>
                            <span class="view-meta-badge">
                                <i class="fas fa-toggle-on"></i> ${escapeHtml(data.status)}
                            </span>
                            ${isCloudinary ? '<span class="view-meta-badge cloud"><i class="fas fa-cloud"></i> Cloudinary</span>' : ''}
                        </div>
                        <div class="view-section-title">Description</div>
                        <div class="view-description">${escapeHtml(data.description || 'No description provided.')}</div>
                        <div class="view-section-title">Details</div>
                        <div class="view-data-row"><span class="label">Product ID</span><span class="value">#${data.id}</span></div>
                        ${data.sku ? `<div class="view-data-row"><span class="label">SKU</span><span class="value">${escapeHtml(data.sku)}</span></div>` : ''}
                        ${data.supplier ? `<div class="view-data-row"><span class="label">Supplier</span><span class="value">${escapeHtml(data.supplier)}</span></div>` : ''}
                        <div class="view-data-row"><span class="label">Images</span><span class="value">${viewGallery.length}</span></div>
                    </div>
                </div>
            `;
            document.getElementById('viewProductContent').innerHTML = html;
            document.querySelectorAll('#viewThumbs .view-thumb').forEach(thumb => {
                thumb.addEventListener('click', function() {
                    setViewImage(parseInt(this.dataset.index));
                });
            });
        }
        
        function setViewImage(idx) {
            if (idx < 0) idx = viewGallery.length - 1;
            if (idx >= viewGallery.length) idx = 0;
            viewGalleryIndex = idx;
            const mainImg = document.getElementById('viewMainImage');
            const counter = document.getElementById('viewImageCounter');
            if (mainImg) {
                mainImg.style.opacity = '0.4';
                setTimeout(() => { mainImg.src = viewGallery[idx]; mainImg.style.opacity = '1'; }, 100);
            }
            if (counter) counter.textContent = idx + 1;
            document.querySelectorAll('#viewThumbs .view-thumb').forEach((t, i) => {
                t.classList.toggle('active', i === idx);
            });
        }
        function viewGalleryPrev() { setViewImage(viewGalleryIndex - 1); }
        function viewGalleryNext() { setViewImage(viewGalleryIndex + 1); }
        
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
        
        // Lightbox
        function openAdmLightbox(idx) {
            if (!viewGallery.length) return;
            document.getElementById('admLightboxImg').src = viewGallery[idx];
            document.getElementById('admLightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeAdmLightbox() {
            document.getElementById('admLightbox').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        function admLightboxPrev() { setViewImage(viewGalleryIndex - 1); document.getElementById('admLightboxImg').src = viewGallery[viewGalleryIndex]; }
        function admLightboxNext() { setViewImage(viewGalleryIndex + 1); document.getElementById('admLightboxImg').src = viewGallery[viewGalleryIndex]; }
        
        // Edit product
        function editProduct(productId) {
            var data = productData[productId];
            if (!data) { alert('Product data not found!'); return; }
            
            document.getElementById('editProductId').value = data.id;
            document.getElementById('editProductName').value = data.name;
            document.getElementById('editProductDescription').value = data.description;
            document.getElementById('editProductPrice').value = data.price;
            document.getElementById('editProductCategory').value = data.category_id;
            document.getElementById('editProductStatus').value = data.status;
            document.getElementById('editProductStock').value = data.stock;
            document.getElementById('editProductSku').value = data.sku;
            document.getElementById('editProductSupplier').value = data.supplier;
            
            var imagePreview = document.getElementById('editProductImagePreview');
            if (data.image_url) {
                imagePreview.innerHTML = '<img src="' + data.image_url + '" alt="Current image"><br>' +
                                        '<small style="color:#666;"><i class="fas fa-cloud" style="color:#3448C5;"></i> Cloudinary image</small>';
            } else if (data.image) {
                imagePreview.innerHTML = '<img src="../uploads/products/' + data.image + '" alt="Current image"><br>' +
                                        '<small style="color:#666;">Local image: ' + data.image + '</small>';
            } else {
                imagePreview.innerHTML = '<small style="color:#666;">No image uploaded</small>';
            }
            
            document.getElementById('editMainPreview').innerHTML = '';
            document.getElementById('editNewGalleryPreview').innerHTML = '';
            loadExistingGallery(productId);
            
            openModal('editProductModal');
        }
        
        <?php if ($edit_product): ?>
            window.onload = function() {
                editProduct(<?php echo $edit_product['id']; ?>);
            };
        <?php endif; ?>
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            if (event.target.id === 'admLightbox') closeAdmLightbox();
        }
        
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('admLightbox').classList.contains('active')) {
                if (e.key === 'Escape') closeAdmLightbox();
                if (e.key === 'ArrowLeft') admLightboxPrev();
                if (e.key === 'ArrowRight') admLightboxNext();
                return;
            }
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(function(modal) {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
        
        document.querySelectorAll('.file-input-wrapper input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function() {
                var fileName;
                if (this.multiple && this.files.length > 1) fileName = this.files.length + ' files selected';
                else if (this.files.length > 0) fileName = this.files[0].name;
                else fileName = 'No file chosen';
                var parent = this.closest('.file-input-wrapper');
                var btn = parent.querySelector('button');
                btn.innerHTML = '<i class="fas fa-file"></i> ' + fileName;
            });
        });
    </script>
</body>
</html>
