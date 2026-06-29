<?php
// admin/products.php - Product Management

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = Database::getInstance();

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $data = [
                    'name' => sanitize($_POST['name']),
                    'description' => sanitize($_POST['description']),
                    'price' => floatval($_POST['price']),
                    'image' => sanitize($_POST['image']),
                    'category_id' => intval($_POST['category_id']),
                    'stock' => intval($_POST['stock'])
                ];
                
                if ($db->addProduct($data)) {
                    $message = 'Product added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add product.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                if ($db->deleteProduct($id)) {
                    $message = 'Product deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete product.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

$products = $db->getProducts();
$categories = getCategories();
$page_title = 'Products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - WittyMart Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <img src="../images/Witty Mart.png" alt="WittyMart">
                <h2>WittyMart</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="products.php" class="active"><i class="fas fa-box"></i> Products</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="categories.php"><i class="fas fa-tags"></i> Categories</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Products</h1>
                <button class="btn-primary" onclick="openModal('addProductModal')">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Products Table -->
            <div class="admin-card">
                <div class="card-body">
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
                                        <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="product-thumb">
                                    </td>
                                    <td><?php echo $product['name']; ?></td>
                                    <td><?php echo formatPrice($product['price']); ?></td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td><?php echo $product['category_id']; ?></td>
                                    <td>
                                        <button class="btn-sm btn-edit" onclick="editProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Are you sure?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Product</h2>
                <span class="close" onclick="closeModal('addProductModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Price</label>
                    <input type="number" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Image URL</label>
                    <input type="text" name="image" placeholder="/images/product.jpg">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Stock</label>
                    <input type="number" name="stock" value="0">
                </div>
                <button type="submit" class="btn-primary">Add Product</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
