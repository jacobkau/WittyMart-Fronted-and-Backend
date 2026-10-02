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
 * Returns array of ['url' => ..., 'public_id' => ...] for successful uploads.
 */
function uploadMultipleProductImages($files) {
    $uploaded = [];
    if (empty($files['name'][0])) return $uploaded;
    
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > 5 * 1024 * 1024) continue; // 5MB cap
        
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
                
                $image_url = null;
                $image_public_id = null;
                $image_name = null;
                $upload_message = '';
                
                // Primary image (single file input named "image")
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadToCloudinary($_FILES['image']['tmp_name'], 'products');
                    
                    if ($upload_result['success']) {
                        $image_url = $upload_result['url'];
                        $image_public_id = $upload_result['public_id'];
                        $upload_message = 'Main image uploaded to Cloudinary. ';
                        
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                        $image_name = time() . '_' . basename($_FILES['image']['name']);
                        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
                    }
                }
                
                // Additional gallery images
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
                        
                        // Insert primary image
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
                        
                        // Insert additional gallery images
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
                            logActivity('add_product', 'Added product: ' . $name,
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
                        // Sync primary image row
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
                        
                        // ===== HANDLE ADDITIONAL GALLERY IMAGES =====
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

// ===== GET PRODUCTS =====
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get products error: ' . $e->getMessage());
    $products = [];
}

// ===== GET CATEGORIES =====
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
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
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
        .status-draft { background-color: #ffc107; color: #333; }
        
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
        
        /* ===== IMAGE PREVIEW GRID ===== */
        .image-preview-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            min-height: 40px;
        }
        
        .preview-thumb {
            position: relative;
            width: 70px;
            height: 70px;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            background: #f5f5f5;
        }
        .preview-thumb img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        .preview-thumb .thumb-label {
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
        
        /* Existing gallery image thumbs (in edit modal) */
        .existing-thumb {
            position: relative;
            width: 70px;
            height: 70px;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            background: #f5f5f5;
        }
        .existing-thumb.primary {
            border-color: #05573c;
            box-shadow: 0 0 0 2px rgba(5, 87, 60, 0.15);
        }
        .existing-thumb img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        .existing-thumb .thumb-label {
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
        .existing-thumb .thumb-delete:hover {
            background: #dc3545;
            transform: scale(1.1);
        }
        
        /* Main image preview (edit) */
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
        
        /* ===== LIGHTBOX ===== */
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
        }
    </style>
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
        <?php include "sidebar.php" ?>

        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px">
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

            <!-- Products Table -->
            <div class="admin-card" style="padding:14px">
                <div class="card-body" style="padding:14px">
                    <?php if (count($products) > 0): ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo getProductImage($product['image'] ?? null, $product['image_url'] ?? null); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="product-image-thumb"
                                                 onerror="this.src='../uploads/products/no-image.png'">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <span class="cloudinary-badge">Cloud</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)); ?>...</td>
                                        <td>Ksh <?php echo number_format($product['price'], 0); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($product['status'] ?? 'active'); ?>">
                                                <?php echo htmlspecialchars($product['status'] ?? 'active'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($product['created_at'] ?? 'now')); ?></td>
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
                    <?php else: ?>
                        <p class="text-muted text-center" style="padding: 40px 0;">
                            <i class="fas fa-box" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                            No products found
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- ============================================
         VIEW PRODUCT MODAL
         ============================================ -->
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

    <!-- ============================================
         ADMIN LIGHTBOX
         ============================================ -->
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

    <!-- ============================================
         ADD PRODUCT MODAL
         ============================================ -->
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
                        <label><i class="fas fa-cubes"></i> Stock</label>
                        <input type="number" name="stock" value="0">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-barcode"></i> SKU</label>
                        <input type="text" name="sku" placeholder="Optional">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> Supplier</label>
                    <input type="text" name="supplier" placeholder="Supplier name">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> Main Image</label>
                        <div class="file-input-wrapper">
                            <button type="button" class="btn-secondary" style="width:100%;">
                                <i class="fas fa-upload"></i> Choose Main Image
                            </button>
                            <input type="file" name="image" accept="image/*" id="addMainImage" onchange="previewImages(this, 'addMainPreview')">
                        </div>
                        <div class="image-preview-grid" id="addMainPreview"></div>
                        <small style="display:block; margin-top:5px; color:#666;">
                            <i class="fas fa-cloud-upload-alt"></i> This becomes the primary image.
                        </small>
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
                        <input type="file" name="product_images[]" accept="image/*" multiple id="addGalleryImages" onchange="previewImages(this, 'addGalleryPreview', true)">
                    </div>
                    <div class="image-preview-grid" id="addGalleryPreview"></div>
                    <small style="display:block; margin-top:5px; color:#666;">
                        <i class="fas fa-cloud-upload-alt"></i> Select multiple. Max 5MB each.
                    </small>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Add Product
                </button>
            </form>
        </div>
    </div>

    <!-- ============================================
         EDIT PRODUCT MODAL
         ============================================ -->
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
                        <label><i class="fas fa-cubes"></i> Stock</label>
                        <input type="number" name="stock" id="editProductStock" value="0">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-barcode"></i> SKU</label>
                        <input type="text" name="sku" id="editProductSku">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> Supplier</label>
                    <input type="text" name="supplier" id="editProductSupplier">
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
                        <small style="display:block; margin-top:5px; color:#666;">
                            Leave empty to keep current.
                        </small>
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
        // Store product data for editing
        var productData = {};
        
        <?php foreach ($products as $product): ?>
            productData[<?php echo $product['id']; ?>] = {
                id: <?php echo $product['id']; ?>,
                name: '<?php echo jsEscape($product['name']); ?>',
                description: '<?php echo jsEscape($product['description'] ?? ''); ?>',
                price: '<?php echo $product['price']; ?>',
                category_id: '<?php echo $product['category_id'] ?? ''; ?>',
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
        // LOAD EXISTING GALLERY (Edit modal)
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
        
        // Delete a single gallery image
        function deleteGalleryImage(imageId) {
            if (!confirm('Delete this gallery image?')) return;
            
            const fd = new FormData();
            fd.append('action', 'delete_image');
            fd.append('image_id', imageId);
            
            fetch('products.php', { method: 'POST', body: fd })
                .then(() => {
                    // Reload the gallery
                    const pid = document.getElementById('editProductId').value;
                    loadExistingGallery(pid);
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to delete image');
                });
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
                .catch(err => {
                    console.error(err);
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
                                <i class="fas fa-tag"></i> ${escapeHtml(productData[data.id].category_name || 'Uncategorized')}
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
                setTimeout(() => {
                    mainImg.src = viewGallery[idx];
                    mainImg.style.opacity = '1';
                }, 100);
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
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        
        // ============================================
        // ADMIN LIGHTBOX
        // ============================================
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
        
        // ============================================
        // EDIT PRODUCT
        // ============================================
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
            
            // Main image preview
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
            
            // Clear any leftover preview grids
            document.getElementById('editMainPreview').innerHTML = '';
            document.getElementById('editNewGalleryPreview').innerHTML = '';
            
            // Load gallery
            loadExistingGallery(productId);
            
            openModal('editProductModal');
        }
        
        <?php if ($edit_product): ?>
            window.onload = function() {
                editProduct(<?php echo $edit_product['id']; ?>);
            };
        <?php endif; ?>
        
        // ============================================
        // GLOBAL MODAL / EVENT HANDLERS
        // ============================================
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            if (event.target.id === 'admLightbox') {
                closeAdmLightbox();
            }
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
        
        // File input button label update
        document.querySelectorAll('.file-input-wrapper input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function() {
                var fileName;
                if (this.multiple && this.files.length > 1) {
                    fileName = this.files.length + ' files selected';
                } else if (this.files.length > 0) {
                    fileName = this.files[0].name;
                } else {
                    fileName = 'No file chosen';
                }
                var parent = this.closest('.file-input-wrapper');
                var btn = parent.querySelector('button');
                btn.innerHTML = '<i class="fas fa-file"></i> ' + fileName;
            });
        });
    </script>
</body>
</html>
