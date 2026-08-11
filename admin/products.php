<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/cloudinary_helper.php'; 

requireAdmin();

global $pdo, $cloudinary;

$message = '';
$messageType = '';

// ===== IMAGE UPLOAD DIRECTORY (Fallback) =====
$upload_dir = UPLOAD_DIR;
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}


// ===== UPLOAD IMAGE FUNCTION (With Cloudinary Support) =====
function uploadProductImage($file) {
    global $cloudinary;
    
    // Validate file
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => '', 'error' => 'No file uploaded or upload error.'];
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'path' => '', 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.'];
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'path' => '', 'error' => 'File size exceeds 5MB limit.'];
    }
    
    // Generate unique filename for local storage
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Try Cloudinary first if available
    if ($cloudinary) {
        try {
            $public_id = 'products/' . time() . '_' . pathinfo(basename($file['name']), PATHINFO_FILENAME);
            
            $upload_result = $cloudinary->uploadApi()->upload(
                $file['tmp_name'],
                [
                    'public_id' => $public_id,
                    'folder' => 'products',
                    'quality' => 'auto:best',
                    'fetch_format' => 'auto',
                    'transformation' => [
                        ['width' => 800, 'height' => 800, 'crop' => 'limit', 'quality' => 'auto']
                    ]
                ]
            );
            
            error_log('Cloudinary upload successful: ' . $upload_result['public_id']);
            
            // Also save locally as fallback
            $target_path = UPLOAD_DIR . $filename;
            move_uploaded_file($file['tmp_name'], $target_path);
            
            return [
                'success' => true,
                'path' => 'uploads/products/' . $filename,
                'full_path' => $target_path,
                'url' => $upload_result['secure_url'],
                'public_id' => $upload_result['public_id']
            ];
        } catch (Exception $e) {
            error_log('Cloudinary upload error: ' . $e->getMessage());
            // Fall through to local upload
        }
    }
    
    // Fallback to local upload
    $target_path = UPLOAD_DIR . $filename;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        error_log('File uploaded successfully to: ' . $target_path);
        return [
            'success' => true,
            'path' => 'uploads/products/' . $filename,
            'full_path' => $target_path,
            'url' => 'https://wittymart.onrender.com/uploads/products/' . $filename,
            'public_id' => null
        ];
    } else {
        error_log('Failed to move uploaded file to: ' . $target_path);
        return ['success' => false, 'path' => '', 'error' => 'Failed to save file.'];
    }
}

// ===== DELETE IMAGE FUNCTION (With Cloudinary Support) =====
function deleteProductImage($image_path, $public_id = null) {
    global $cloudinary;
    
    // Delete from Cloudinary if public_id exists
    if (!empty($public_id) && $cloudinary) {
        try {
            $cloudinary->uploadApi()->destroy($public_id);
            error_log('Cloudinary image deleted: ' . $public_id);
        } catch (Exception $e) {
            error_log('Cloudinary delete error: ' . $e->getMessage());
        }
    }
    
    // Delete local file
    if (empty($image_path)) {
        return true;
    }
    
    $filename = basename($image_path);
    $full_path = UPLOAD_DIR . $filename;
    
    if (file_exists($full_path) && is_file($full_path)) {
        if (unlink($full_path)) {
            error_log('Deleted local image: ' . $full_path);
            return true;
        } else {
            error_log('Failed to delete local image: ' . $full_path);
        }
    }
    
    return false;
}

// ===== HANDLE FORM SUBMISSIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $name = sanitize($_POST['name'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $price = floatval($_POST['price'] ?? 0);
                    $category_id = intval($_POST['category_id'] ?? 0);
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
                    } else {
                        $sku = sanitize($sku);
                    }
                    
                    // Handle image upload
                    $image_path = '';
                    $image_url = null;
                    $image_public_id = null;
                    
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = uploadProductImage($_FILES['product_image']);
                        if ($upload_result['success']) {
                            $image_path = $upload_result['path'];
                            $image_url = $upload_result['url'] ?? null;
                            $image_public_id = $upload_result['public_id'] ?? null;
                        } else {
                            $message = 'Image upload failed: ' . $upload_result['error'];
                            $messageType = 'error';
                        }
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO products (name, description, price, image, image_url, image_public_id, category_id, stock, supplier, sku) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$name, $description, $price, $image_path, $image_url, $image_public_id, $category_id, $stock, $supplier, $sku])) {
                        logActivity(
                            'add_product',
                            'Added product: ' . $name . ' (SKU: ' . $sku . ')',
                            $_SESSION['user_id'],
                            $_SESSION['user_name']
                        );
                        $message = 'Product added successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to add product.';
                        $messageType = 'error';
                    }
                } catch (PDOException $e) {
                    error_log('Add product error: ' . $e->getMessage());
                    $message = 'Database error: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                try {
                    $id = intval($_POST['id'] ?? 0);
                    
                    $stmt = $pdo->prepare("SELECT image, image_public_id, name FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        // Delete from Cloudinary and local
                        deleteProductImage($product['image'] ?? '', $product['image_public_id'] ?? null);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        logActivity(
                            'delete_product',
                            'Deleted product: ' . ($product['name'] ?? 'Unknown') . ' (ID: ' . $id . ')',
                            $_SESSION['user_id'],
                            $_SESSION['user_name']
                        );
                        $message = 'Product deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete product.';
                        $messageType = 'error';
                    }
                } catch (PDOException $e) {
                    error_log('Delete product error: ' . $e->getMessage());
                    $message = 'Database error: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'edit':
                try {
                    $id = intval($_POST['id'] ?? 0);
                    $name = sanitize($_POST['name'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $price = floatval($_POST['price'] ?? 0);
                    $category_id = intval($_POST['category_id'] ?? 0);
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
                    } else {
                        $sku = sanitize($sku);
                    }
                    
                    $stmt = $pdo->prepare("SELECT image, image_public_id FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $current = $stmt->fetch();
                    $image_path = $current['image'] ?? '';
                    $image_public_id = $current['image_public_id'] ?? null;
                    $image_url = null;
                    
                    if (isset($_FILES['edit_product_image']) && $_FILES['edit_product_image']['error'] === UPLOAD_ERR_OK) {
                        // Delete old images
                        deleteProductImage($image_path, $image_public_id);
                        
                        $upload_result = uploadProductImage($_FILES['edit_product_image']);
                        if ($upload_result['success']) {
                            $image_path = $upload_result['path'];
                            $image_url = $upload_result['url'] ?? null;
                            $image_public_id = $upload_result['public_id'] ?? null;
                        } else {
                            $message = 'Image upload failed: ' . $upload_result['error'];
                            $messageType = 'error';
                        }
                    }
                    
                    // Build update query dynamically
                    $sql = "UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, stock = ?, supplier = ?, sku = ?";
                    $params = [$name, $description, $price, $category_id, $stock, $supplier, $sku];
                    
                    if ($image_url !== null) {
                        $sql .= ", image = ?, image_url = ?, image_public_id = ?";
                        $params[] = $image_path;
                        $params[] = $image_url;
                        $params[] = $image_public_id;
                    }
                    
                    $sql .= " WHERE id = ?";
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute($params)) {
                        logActivity(
                            'update_product',
                            'Updated product: ' . $name . ' (ID: ' . $id . ')',
                            $_SESSION['user_id'],
                            $_SESSION['user_name']
                        );
                        $message = 'Product updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update product.';
                        $messageType = 'error';
                    }
                } catch (PDOException $e) {
                    error_log('Edit product error: ' . $e->getMessage());
                    $message = 'Database error: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// ===== GET PRODUCTS =====
try {
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC
    ");
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
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>
      
        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px; display: flex; justify-content: space-between; align-items: center;">
                <span class="badge badge-info">Total: <?php echo count($products); ?> products</span>
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
            <div class="admin-card">
                <div class="card-body">
                    <!-- Search Toolbar -->
                    <div class="table-toolbar" style="padding:14px;">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchProducts" placeholder="Search products by name, SKU, supplier, or category..." onkeyup="filterTable('searchProducts', 'productsTable')">
                            <span class="result-count" style="font-size: 12px; color: var(--text-muted); margin-left: 10px;"></span>
                            <button class="clear-search-btn" onclick="clearSearch('searchProducts', 'productsTable')" style="background: none; border: none; color: var(--text-muted); cursor: pointer; display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="filter-box" style="display: flex; gap: 10px; align-items: center;">
                            <select id="categoryFilter" onchange="filterProducts()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="stockFilter" onchange="filterProducts()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                                <option value="">All Stock</option>
                                <option value="in-stock">In Stock (&gt;0)</option>
                                <option value="low-stock">Low Stock (&lt;=5)</option>
                                <option value="out-of-stock">Out of Stock (0)</option>
                            </select>
                        </div>
                    </div>

                    <?php if (count($products) > 0): ?>
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $image_url = getProductImageUrl($product['image'] ?? '', $product['image_url'] ?? null);
                                            ?>
                                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 class="product-thumb"
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; background: #f0f0f0;"
                                                 onerror="this.src='https://wittymart.onrender.com/uploads/products/no-image.png'">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <span style="font-size: 8px; color: #3448C5; display: block; text-align: center;">
                                                    <i class="fas fa-cloud"></i> Cloud
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></code></td>
                                        <td><?php echo formatPrice($product['price']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $product['stock'] > 0 ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo htmlspecialchars($product['stock']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['supplier'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td>
                                            <button class="btn-sm btn-edit" onclick="editProduct(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this product?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- No results message -->
                        <div class="no-results-message" style="display: none; text-align: center; padding: 40px 20px; color: var(--text-muted);">
                            <i class="fas fa-search" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                            <h3>No products found</h3>
                            <p>Try adjusting your search terms or filters</p>
                        </div>
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
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add Product</h2>
                <span class="close" onclick="closeModal('addProductModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Product Name</label>
                    <input type="text" name="name" required placeholder="Enter product name">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> SKU (Optional)</label>
                    <input type="text" name="sku" placeholder="e.g., PRD-001">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" rows="3" placeholder="Enter product description"></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Price (Ksh)</label>
                    <input type="number" name="price" step="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Product Image</label>
                    <div class="image-upload-wrapper">
                        <input type="file" name="product_image" id="product_image" accept="image/*" onchange="previewImage(this, 'imagePreview')">
                        <label for="product_image" class="upload-btn">
                            <i class="fas fa-cloud-upload-alt"></i> Choose Image
                        </label>
                        <div id="imagePreview" class="image-preview">
                            <i class="fas fa-image" style="font-size: 40px; color: #ddd;"></i>
                            <p>No image selected</p>
                        </div>
                    </div>
                    <small class="form-text text-muted">Supported formats: JPG, PNG, GIF, WEBP. Max size: 5MB. Will be uploaded to Cloudinary.</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Category</label>
                    <select name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-cubes"></i> Stock Quantity</label>
                    <input type="number" name="stock" value="0">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> Supplier / Seller</label>
                    <input type="text" name="supplier" placeholder="Enter supplier name">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Product
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Product</h2>
                <span class="close" onclick="closeModal('editProductModal')">&times;</span>
            </div>
            <form method="POST" id="editProductForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_product_id">
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Product Name</label>
                    <input type="text" name="name" id="edit_product_name" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> SKU</label>
                    <input type="text" name="sku" id="edit_product_sku" placeholder="e.g., PRD-001">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" id="edit_product_description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Price (Ksh)</label>
                    <input type="number" name="price" id="edit_product_price" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Product Image</label>
                    <div class="image-upload-wrapper">
                        <div id="editImagePreview" class="image-preview">
                            <i class="fas fa-image" style="font-size: 40px; color: #ddd;"></i>
                            <p>No image</p>
                        </div>
                        <input type="file" name="edit_product_image" id="edit_product_image" accept="image/*" onchange="previewImage(this, 'editImagePreview')">
                        <label for="edit_product_image" class="upload-btn">
                            <i class="fas fa-cloud-upload-alt"></i> Change Image
                        </label>
                    </div>
                    <small class="form-text text-muted">Leave empty to keep current image. Will be uploaded to Cloudinary.</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Category</label>
                    <select name="category_id" id="edit_product_category">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-cubes"></i> Stock Quantity</label>
                    <input type="number" name="stock" id="edit_product_stock">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> Supplier / Seller</label>
                    <input type="text" name="supplier" id="edit_product_supplier" placeholder="Enter supplier name">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Product
                </button>
            </form>
        </div>
    </div>

    <style>
        /* Image Upload Styles */
        .image-upload-wrapper {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f8f9fa;
            border: 2px dashed #ccc;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #555;
            font-weight: 500;
            justify-content: center;
        }
        
        .upload-btn:hover {
            background: #e8f5f0;
            border-color: #05573c;
            color: #05573c;
        }
        
        .upload-btn i {
            font-size: 20px;
        }
        
        .image-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #eee;
            min-height: 150px;
        }
        
        .image-preview img {
            max-width: 150px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .image-preview p {
            margin: 10px 0 0;
            color: #999;
            font-size: 14px;
        }
        
        input[type="file"] {
            display: none;
        }
        
        .form-text {
            display: block;
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }

        .product-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 14px;
            background: var(--bg);
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid var(--border);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--white);
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            flex: 1;
            min-width: 200px;
            max-width: 400px;
        }

        .search-box:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }

        .search-box i {
            color: var(--text-muted);
            font-size: 14px;
        }

        .search-box input {
            border: none;
            background: transparent;
            padding: 8px 0;
            outline: none;
            color: var(--text);
            width: 100%;
            font-size: 14px;
        }

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        .clear-search-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .clear-search-btn:hover {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text);
        }

        .search-box input:not(:placeholder-shown) ~ .clear-search-btn {
            display: block;
        }

        .no-results-message {
            display: none;
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .no-results-message i {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
            opacity: 0.3;
        }

        /* Dark mode support */
        body.dark-mode .search-box {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.1);
        }

        body.dark-mode .search-box:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.2);
        }

        body.dark-mode .clear-search-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>

    <script>
        // ===== IMAGE PREVIEW =====
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Product Image" style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 8px;"><p>New image</p>`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // ===== MODAL FUNCTIONS =====
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
        
        // ===== SEARCH FUNCTIONS =====
        function filterTable(inputId, tableId) {
            const input = document.getElementById(inputId);
            const table = document.getElementById(tableId);
            
            if (!input || !table) return;

            const filter = input.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const match = text.includes(filter);
                row.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            // Update result count
            const counter = table.parentElement.querySelector('.result-count');
            if (counter) {
                counter.textContent = `Showing ${visibleCount} of ${rows.length} results`;
            }

            // Show/hide no results message
            const noResultMsg = table.parentElement.querySelector('.no-results-message');
            if (noResultMsg) {
                noResultMsg.style.display = visibleCount === 0 ? 'block' : 'none';
            }

            // Show/hide clear button
            const clearBtn = input.parentElement.querySelector('.clear-search-btn');
            if (clearBtn) {
                clearBtn.style.display = input.value.length > 0 ? 'block' : 'none';
            }
        }

        function clearSearch(inputId, tableId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.value = '';
                filterTable(inputId, tableId);
                input.focus();
            }
        }

        // ===== PRODUCT FILTERS =====
        function filterProducts() {
            const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
            const stockFilter = document.getElementById('stockFilter').value;
            const searchInput = document.getElementById('searchProducts');
            const rows = document.querySelectorAll('#productsTable tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                let show = true;
                const cells = row.querySelectorAll('td');
                
                // Category filter
                if (categoryFilter) {
                    const categoryCell = cells[6];
                    if (categoryCell && !categoryCell.textContent.toLowerCase().includes(categoryFilter)) {
                        show = false;
                    }
                }
                
                // Stock filter
                if (stockFilter && show) {
                    const stockCell = cells[4];
                    if (stockCell) {
                        const stockText = stockCell.textContent.trim();
                        const stockValue = parseInt(stockText);
                        if (stockFilter === 'in-stock' && stockValue <= 0) show = false;
                        else if (stockFilter === 'low-stock' && (stockValue > 5 || stockValue <= 0)) show = false;
                        else if (stockFilter === 'out-of-stock' && stockValue > 0) show = false;
                    }
                }
                
                // Search filter (if search input has value)
                if (show && searchInput && searchInput.value.trim()) {
                    const searchText = row.textContent.toLowerCase();
                    if (!searchText.includes(searchInput.value.toLowerCase().trim())) {
                        show = false;
                    }
                }
                
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            // Update result count
            const table = document.getElementById('productsTable');
            const counter = table.parentElement.querySelector('.result-count');
            if (counter) {
                counter.textContent = `Showing ${visibleCount} of ${rows.length} results`;
            }

            const noResultMsg = table.parentElement.querySelector('.no-results-message');
            if (noResultMsg) {
                noResultMsg.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        // ===== EDIT PRODUCT =====
        function editProduct(id) {
            fetch('includes/ajax.php?action=get_product&id=' + id)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_product_id').value = data.product.id;
                        document.getElementById('edit_product_name').value = data.product.name || '';
                        document.getElementById('edit_product_description').value = data.product.description || '';
                        document.getElementById('edit_product_price').value = data.product.price || 0;
                        document.getElementById('edit_product_category').value = data.product.category_id || '';
                        document.getElementById('edit_product_stock').value = data.product.stock || 0;
                        document.getElementById('edit_product_sku').value = data.product.sku || '';
                        document.getElementById('edit_product_supplier').value = data.product.supplier || '';
                        
                        const imgPreview = document.getElementById('editImagePreview');
                        if (data.product.image_url) {
                            imgPreview.innerHTML = `<img src="${data.product.image_url}" alt="Product Image" style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 8px;"><p>Cloudinary image</p>`;
                        } else if (data.product.image) {
                            const imgUrl = 'https://wittymart.onrender.com/' + data.product.image;
                            imgPreview.innerHTML = `<img src="${imgUrl}" alt="Product Image" style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 8px;"><p>Current image</p>`;
                        } else {
                            imgPreview.innerHTML = `<i class="fas fa-image" style="font-size: 40px; color: #ddd;"></i><p>No image</p>`;
                        }
                        
                        openModal('editProductModal');
                    } else {
                        alert('Failed to load product data: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading product data. Please check the console for details.\n\n' + error.message);
                });
        }

        // ===== AUTO-HIDE ALERTS =====
        setTimeout(() => {
            document.querySelectorAll('.alert-persistent').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>
