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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                $category_id = intval($_POST['category_id'] ?? 0);
                $status = sanitize($_POST['status'] ?? 'active');
                
                // Handle image upload - UPLOAD TO CLOUDINARY
                $image_url = null;
                $image_public_id = null;
                $image_name = null; // Keep for backward compatibility
                $upload_message = '';
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // First, upload to Cloudinary
                    $upload_result = uploadToCloudinary($_FILES['image']['tmp_name'], 'products');
                    
                    if ($upload_result['success']) {
                        $image_url = $upload_result['url'];
                        $image_public_id = $upload_result['public_id'];
                        $upload_message = 'Image uploaded to Cloudinary successfully!';
                        
                        // Also save locally as fallback (optional)
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $image_name = time() . '_' . basename($_FILES['image']['name']);
                        $upload_path = $upload_dir . $image_name;
                        move_uploaded_file($_FILES['image']['tmp_name'], $upload_path);
                    } else {
                        // Fallback to local upload if Cloudinary fails
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $image_name = time() . '_' . basename($_FILES['image']['name']);
                        $upload_path = $upload_dir . $image_name;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                            $upload_message = 'Image saved locally (Cloudinary upload failed)';
                        } else {
                            $image_name = null;
                            $upload_message = 'Image upload failed';
                        }
                    }
                }
                
                if ($name && $price > 0) {
                    // Update SQL to include image_url and image_public_id
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, image, image_url, image_public_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$name, $description, $price, $category_id, $image_name, $image_url, $image_public_id, $status])) {
                        if (function_exists('logActivity')) {
                            logActivity(
                                'add_product',
                                'Added product: ' . $name,
                                $_SESSION['user_id'],
                                $_SESSION['user_name']
                            );
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
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                $category_id = intval($_POST['category_id'] ?? 0);
                $status = sanitize($_POST['status'] ?? 'active');
                
                // Get existing product to delete old Cloudinary image if needed
                $stmt = $pdo->prepare("SELECT image_public_id, image FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $existing_product = $stmt->fetch();
                
                // Handle image upload
                $image_url = null;
                $image_public_id = null;
                $image_name = null;
                $upload_message = '';
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // Delete old Cloudinary image if exists
                    if (!empty($existing_product['image_public_id'])) {
                        $delete_result = deleteFromCloudinary($existing_product['image_public_id']);
                        if ($delete_result['success']) {
                            $upload_message = 'Old image deleted from Cloudinary. ';
                        }
                    }
                    
                    // Upload new image to Cloudinary
                    $upload_result = uploadToCloudinary($_FILES['image']['tmp_name'], 'products');
                    
                    if ($upload_result['success']) {
                        $image_url = $upload_result['url'];
                        $image_public_id = $upload_result['public_id'];
                        $upload_message .= 'New image uploaded to Cloudinary!';
                        
                        // Also save locally as fallback
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $image_name = time() . '_' . basename($_FILES['image']['name']);
                        $upload_path = $upload_dir . $image_name;
                        move_uploaded_file($_FILES['image']['tmp_name'], $upload_path);
                    } else {
                        // Fallback to local
                        $upload_dir = UPLOAD_DIR;
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $image_name = time() . '_' . basename($_FILES['image']['name']);
                        $upload_path = $upload_dir . $image_name;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                            $upload_message = 'Image saved locally (Cloudinary upload failed)';
                        } else {
                            $image_name = null;
                            $upload_message = 'Image upload failed';
                        }
                    }
                }
                
                if ($name && $price > 0 && $id) {
                    // Build the update query dynamically
                    $sql = "UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, status = ?";
                    $params = [$name, $description, $price, $category_id, $status];
                    
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
                        if (function_exists('logActivity')) {
                            logActivity(
                                'update_product',
                                'Updated product: ' . $name . ' (ID: ' . $id . ')',
                                $_SESSION['user_id'],
                                $_SESSION['user_name']
                            );
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
                
            case 'delete':
                $id = intval($_POST['id']);
                
                // Check if product is in cart
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE product_id = ?");
                $stmt->execute([$id]);
                $cart_count = $stmt->fetch()['count'];
                
                if ($cart_count > 0) {
                    $message = 'Cannot delete product as it is in a cart.';
                    $messageType = 'error';
                } else {
                    // Get product info to delete Cloudinary image
                    $stmt = $pdo->prepare("SELECT image_public_id FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $product = $stmt->fetch();
                    
                    // Delete from Cloudinary if exists
                    $delete_message = '';
                    if (!empty($product['image_public_id'])) {
                        $delete_result = deleteFromCloudinary($product['image_public_id']);
                        if ($delete_result['success']) {
                            $delete_message = 'Image deleted from Cloudinary. ';
                        }
                    }
                    
                    // Delete from database
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        if (function_exists('logActivity')) {
                            logActivity(
                                'delete_product',
                                'Deleted product ID: ' . $id,
                                $_SESSION['user_id'],
                                $_SESSION['user_name']
                            );
                        }
                        $message = 'Product deleted successfully! ' . $delete_message;
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete product.';
                        $messageType = 'error';
                    }
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

// ===== GET CATEGORIES FOR DROPDOWN =====
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
    $categories = [];
}

// ===== GET PRODUCT FOR EDITING =====
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

// Helper function to get product image URL - UPDATED for Cloudinary
function getProductImage($image_name, $image_url = null) {
    // If Cloudinary URL exists, use it
    if (!empty($image_url)) {
        return $image_url;
    }
    
    // Fallback to local image
    if (!empty($image_name) && file_exists(UPLOAD_DIR . $image_name)) {
        return '../uploads/products/' . $image_name;
    }
    
    return '../uploads/products/no-image.png';
}

// Helper function to escape JavaScript strings
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
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
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
    <?php include "header.php"?>
    <div class="admin-wrapper">
        <?php include "sidebar.php" ?>

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
                                                 class="product-image-thumb">
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
                            No products found
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
                        <label><i class="fas fa-image"></i> Product Image</label>
                        <div class="file-input-wrapper">
                            <button type="button" class="btn-secondary" style="width:100%;">
                                <i class="fas fa-upload"></i> Choose Image
                            </button>
                            <input type="file" name="image" accept="image/*">
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
                        <label><i class="fas fa-image"></i> Product Image</label>
                        <div id="editProductImagePreview" style="margin-bottom:10px;"></div>
                        <div class="file-input-wrapper">
                            <button type="button" class="btn-secondary" style="width:100%;">
                                <i class="fas fa-upload"></i> Change Image
                            </button>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <small style="display:block; margin-top:5px; color:#666;">
                            <i class="fas fa-cloud-upload-alt"></i> Will be uploaded to Cloudinary
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
        // Store product data for editing - UPDATED with Cloudinary data
        var productData = {};
        
        <?php foreach ($products as $product): ?>
            productData[<?php echo $product['id']; ?>] = {
                id: <?php echo $product['id']; ?>,
                name: '<?php echo jsEscape($product['name']); ?>',
                description: '<?php echo jsEscape($product['description'] ?? ''); ?>',
                price: '<?php echo $product['price']; ?>',
                category_id: '<?php echo $product['category_id'] ?? ''; ?>',
                status: '<?php echo $product['status'] ?? 'active'; ?>',
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
            // Get product data
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
            
            // Show current image - UPDATED for Cloudinary
            var imagePreview = document.getElementById('editProductImagePreview');
            if (data.image_url) {
                imagePreview.innerHTML = '<img src="' + data.image_url + '" alt="Current image" style="max-height:100px; border-radius:4px;"><br>' +
                                        '<small style="color:#666;">' +
                                        '<i class="fas fa-cloud" style="color:#3448C5;"></i> Cloudinary image' +
                                        '</small>';
            } else if (data.image) {
                imagePreview.innerHTML = '<img src="<?php echo '../uploads/products/'; ?>' + data.image + '" alt="Current image" style="max-height:100px; border-radius:4px;"><br>' +
                                        '<small style="color:#666;">Local image: ' + data.image + '</small>';
            } else {
                imagePreview.innerHTML = '<small style="color:#666;">No image uploaded</small>';
            }
            
            // Open the modal
            openModal('editProductModal');
        }
        
        // Auto-open edit modal if edit parameter is set
        <?php if ($edit_product): ?>
            window.onload = function() {
                editProduct(<?php echo $edit_product['id']; ?>);
            };
        <?php endif; ?>
        
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
            input.addEventListener('change', function(e) {
                var fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                var parent = this.closest('.file-input-wrapper');
                var btn = parent.querySelector('button');
                btn.innerHTML = '<i class="fas fa-file"></i> ' + fileName;
            });
        });
    </script>
</body>
</html>
