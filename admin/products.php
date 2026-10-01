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

// ===== HANDLE FORM SUBMISSIONS =====
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
                
                // Auto-generate SKU if empty
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
                
                // Handle image upload
                $image_url = null;
                $image_public_id = null;
                $image_name = null;
                $upload_message = '';
                
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    // Upload to Cloudinary
                    $upload_result = uploadToCloudinary($_FILES['product_image']['tmp_name'], 'products');
                    
                    if ($upload_result['success']) {
                        $image_url = $upload_result['url'];
                        $image_public_id = $upload_result['public_id'];
                        $upload_message = 'Image uploaded to Cloudinary.';
                        
                        // Also save locally as fallback
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['product_image']['name']));
                        move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_dir . $image_name);
                    } else {
                        // Fallback to local
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['product_image']['name']));
                        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_dir . $image_name)) {
                            $upload_message = 'Image saved locally (Cloudinary upload failed).';
                        } else {
                            $image_name = null;
                            $upload_message = 'Image upload failed.';
                        }
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
                
                // Auto-generate SKU if empty
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
                
                // Get existing product to handle old image
                $stmt = $pdo->prepare("SELECT image, image_url, image_public_id FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                
                $image_url = null;
                $image_public_id = null;
                $image_name = null;
                $upload_message = '';
                
                if (isset($_FILES['edit_product_image']) && $_FILES['edit_product_image']['error'] === UPLOAD_ERR_OK) {
                    // Delete old Cloudinary image
                    if (!empty($existing['image_public_id'])) {
                        $delete_result = deleteFromCloudinary($existing['image_public_id']);
                        if ($delete_result['success']) {
                            $upload_message = 'Old Cloudinary image deleted. ';
                        }
                    }
                    
                    // Delete old local image
                    if (!empty($existing['image']) && file_exists(UPLOAD_DIR . $existing['image'])) {
                        @unlink(UPLOAD_DIR . $existing['image']);
                    }
                    
                    // Upload new image to Cloudinary
                    $upload_result = uploadToCloudinary($_FILES['edit_product_image']['tmp_name'], 'products');
                    
                    if ($upload_result['success']) {
                        $image_url = $upload_result['url'];
                        $image_public_id = $upload_result['public_id'];
                        $upload_message .= 'New image uploaded to Cloudinary.';
                        
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['edit_product_image']['name']));
                        move_uploaded_file($_FILES['edit_product_image']['tmp_name'], $upload_dir . $image_name);
                    } else {
                        // Local fallback
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['edit_product_image']['name']));
                        if (move_uploaded_file($_FILES['edit_product_image']['tmp_name'], $upload_dir . $image_name)) {
                            $upload_message = 'Image saved locally (Cloudinary upload failed).';
                        } else {
                            $image_name = null;
                            $upload_message = 'Image upload failed.';
                        }
                    }
                }
                
                if ($name && $price > 0 && $id) {
                    // Build dynamic update
                    $sql = "UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, status = ?, stock = ?, supplier = ?, sku = ?";
                    $params = [$name, $description, $price, $category_id, $status, $stock, $supplier, $sku];
                    
                    if ($image_url !== null || $image_name !== null) {
                        $sql .= ", image = ?, image_url = ?, image_public_id = ?";
                        $params[] = $image_name;
                        $params[] = $image_url;
                        $params[] = $image_public_id;
                    }
                    
                    $sql .= " WHERE id = ?";
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute($params)) {
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
                $id = intval($_POST['id'] ?? 0);
                
                // Check if product is in cart
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE product_id = ?");
                $stmt->execute([$id]);
                $cart_count = $stmt->fetch()['count'];
                
                if ($cart_count > 0) {
                    $message = 'Cannot delete product — it is currently in a customer cart.';
                    $messageType = 'error';
                    break;
                }
                
                // Get product info
                $stmt = $pdo->prepare("SELECT image, image_public_id, name FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $product = $stmt->fetch();
                
                $delete_message = '';
                
                // Delete from Cloudinary
                if (!empty($product['image_public_id'])) {
                    $delete_result = deleteFromCloudinary($product['image_public_id']);
                    if ($delete_result['success']) {
                        $delete_message = 'Cloudinary image deleted. ';
                    }
                }
                
                // Delete local file
                if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])) {
                    @unlink(UPLOAD_DIR . $product['image']);
                }
                
                // Delete from DB
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                if ($stmt->execute([$id])) {
                    if (function_exists('logActivity')) {
                        logActivity('delete_product', 'Deleted product: ' . ($product['name'] ?? 'Unknown') . ' (ID: ' . $id . ')',
                            $_SESSION['user_id'], $_SESSION['user_name']);
                    }
                    $message = 'Product deleted successfully! ' . $delete_message;
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete product.';
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

// ===== HELPER: Resolve product image URL =====
if (!function_exists('getAdminProductImage')) {
    function getAdminProductImage($image_name, $image_url = null) {
        // Priority: Cloudinary URL > local file > placeholder
        if (!empty($image_url)) {
            return $image_url;
        }
        if (!empty($image_name) && file_exists(UPLOAD_DIR . $image_name)) {
            return '../uploads/products/' . $image_name;
        }
        return '../uploads/products/no-image.png';
    }
}

// ===== HELPER: Escape JS strings =====
if (!function_exists('jsEscape')) {
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
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            background: #f0f0f0;
        }
        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
        .status-draft { background-color: #ffc107; color: #333; }
        .status-deleted { background-color: #6c757d; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .image-preview {
            max-height: 100px;
            margin: 10px 0;
        }
        .image-preview img {
            max-height: 100px;
            border-radius: 4px;
        }
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .btn-edit {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-buttons form {
            display: inline;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .cloudinary-badge {
            background-color: #3448C5;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>

        <!-- Main Content -->
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
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <?php 
                                    $img_src = getAdminProductImage($product['image'] ?? null, $product['image_url'] ?? null);
                                    $is_cloudinary = !empty($product['image_url']) && strpos($product['image_url'], 'cloudinary.com') !== false;
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
                                            <span class="status-badge <?php echo ($product['stock'] ?? 0) > 0 ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo htmlspecialchars($product['stock'] ?? 0); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($product['status'] ?? 'active'); ?>">
                                                <?php echo htmlspecialchars($product['status'] ?? 'active'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-edit" onclick="editProduct(<?php echo $product['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" class="btn-delete">
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
                            No products found. Click "Add Product" to get started.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
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
                        <label><i class="fas fa-image"></i> Product Image</label>
                        <div class="file-input-wrapper">
                            <button type="button" class="btn-secondary" style="width:100%;">
                                <i class="fas fa-upload"></i> Choose Image
                            </button>
                            <input type="file" name="product_image" accept="image/*">
                        </div>
                        <small style="display:block; margin-top:5px; color:#666;">
                            <i class="fas fa-cloud-upload-alt"></i> Will be uploaded to Cloudinary
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
                
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Add Product
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Product</h2>
                <span class="close" onclick="closeModal('editProductModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editProductId">
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Product Name *</label>
                    <input type="text" name="name" id="editProductName" required placeholder="Enter product name">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> SKU</label>
                    <input type="text" name="sku" id="editProductSku" placeholder="e.g., PRD-001">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Price (Ksh) *</label>
                        <input type="number" name="price" id="editProductPrice" required step="0.01" placeholder="0.00">
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
                    <textarea name="description" id="editProductDescription" rows="3" placeholder="Enter product description"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-cubes"></i> Stock Quantity</label>
                        <input type="number" name="stock" id="editProductStock" value="0">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-truck"></i> Supplier / Seller</label>
                        <input type="text" name="supplier" id="editProductSupplier" placeholder="Enter supplier name">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> Product Image</label>
                        <div id="editProductImagePreview" style="margin-bottom:10px;"></div>
                        <div class="file-input-wrapper">
                            <button type="button" class="btn-secondary" style="width:100%;">
                                <i class="fas fa-upload"></i> Change Image
                            </button>
                            <input type="file" name="edit_product_image" accept="image/*">
                        </div>
                        <small style="display:block; margin-top:5px; color:#666;">
                            Leave empty to keep current image
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
                supplier: '<?php echo jsEscape($product['supplier'] ?? ''); ?>',
                sku: '<?php echo jsEscape($product['sku'] ?? ''); ?>',
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
        
        function editProduct(productId) {
            var data = productData[productId];
            if (!data) {
                alert('Product data not found!');
                return;
            }
            
            // Populate edit form
            document.getElementById('editProductId').value = data.id;
            document.getElementById('editProductName').value = data.name;
            document.getElementById('editProductDescription').value = data.description;
            document.getElementById('editProductPrice').value = data.price;
            document.getElementById('editProductCategory').value = data.category_id;
            document.getElementById('editProductStatus').value = data.status;
            document.getElementById('editProductStock').value = data.stock;
            document.getElementById('editProductSupplier').value = data.supplier;
            document.getElementById('editProductSku').value = data.sku;
            
            // Show current image
            var imagePreview = document.getElementById('editProductImagePreview');
            if (data.image_url) {
                imagePreview.innerHTML = '<img src="' + data.image_url + '" alt="Current image" style="max-height:100px; border-radius:4px;">' +
                                        '<br><small style="color:#666;">' +
                                        '<i class="fas fa-cloud" style="color:#3448C5;"></i> Cloudinary image' +
                                        '</small>';
            } else if (data.image) {
                imagePreview.innerHTML = '<img src="../uploads/products/' + data.image + '" alt="Current image" style="max-height:100px; border-radius:4px;">' +
                                        '<br><small style="color:#666;">Local image: ' + data.image + '</small>';
            } else {
                imagePreview.innerHTML = '<small style="color:#666;">No image uploaded</small>';
            }
            
            openModal('editProductModal');
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(function(modal) {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
        
        // File input styling - show filename
        document.querySelectorAll('.file-input-wrapper input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function() {
                var fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                var parent = this.closest('.file-input-wrapper');
                var btn = parent.querySelector('button');
                btn.innerHTML = '<i class="fas fa-file"></i> ' + fileName;
            });
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert-persistent').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() { alert.remove(); }, 500);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>
