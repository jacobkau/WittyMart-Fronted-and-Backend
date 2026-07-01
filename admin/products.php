<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once 'includes/config.php';
require_once 'includes/auth.php';

requireAdmin();

global $pdo;


$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $name = sanitize($_POST['name'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $price = floatval($_POST['price'] ?? 0);
                    $image = sanitize($_POST['image'] ?? '');
                    $category_id = intval($_POST['category_id'] ?? 0);
                    $stock = intval($_POST['stock'] ?? 0);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO products (name, description, price, image, category_id, stock) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$name, $description, $price, $image, $category_id, $stock])) {
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
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    if ($stmt->execute([$id])) {
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
                    $image = sanitize($_POST['image'] ?? '');
                    $category_id = intval($_POST['category_id'] ?? 0);
                    $stock = intval($_POST['stock'] ?? 0);
                    
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET name = ?, description = ?, price = ?, image = ?, category_id = ?, stock = ? 
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([$name, $description, $price, $image, $category_id, $stock, $id])) {
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
    <div class="admin-wrapper">
        <?php include "sidebar.php"?>
      
        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1 style="margin-bottom:10px:"><i class="fas fa-box"></i> Products</h1>
                <button class="btn-primary" onclick="openModal('addProductModal')">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Products Table -->
            <div class="admin-card">
                <div class="card-body">
                    <?php if (count($products) > 0): ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Category</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/50'); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 class="product-thumb">
                                        </td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo formatPrice($product['price']); ?></td>
                                        <td><?php echo htmlspecialchars($product['stock']); ?></td>
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
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Product Name</label>
                    <input type="text" name="name" required placeholder="Enter product name">
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
                    <label><i class="fas fa-image"></i> Image URL</label>
                    <input type="text" name="image" placeholder="/images/product.jpg">
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
            <form method="POST" id="editProductForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_product_id">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Product Name</label>
                    <input type="text" name="name" id="edit_product_name" required>
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
                    <label><i class="fas fa-image"></i> Image URL</label>
                    <input type="text" name="image" id="edit_product_image">
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
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Product
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Edit product function
        function editProduct(id) {
            // Fetch product data via AJAX
            fetch('includes/ajax.php?action=get_product&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_product_id').value = data.product.id;
                        document.getElementById('edit_product_name').value = data.product.name;
                        document.getElementById('edit_product_description').value = data.product.description || '';
                        document.getElementById('edit_product_price').value = data.product.price;
                        document.getElementById('edit_product_image').value = data.product.image || '';
                        document.getElementById('edit_product_category').value = data.product.category_id || '';
                        document.getElementById('edit_product_stock').value = data.product.stock || 0;
                        openModal('editProductModal');
                    } else {
                        alert('Failed to load product data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading product data');
                });
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>
</html>
