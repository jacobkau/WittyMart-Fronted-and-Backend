<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = sanitize($_POST['name'] ?? '');
    $slug = generateSlug($name);
    
    switch ($action) {
        case 'add':
            if ($name) {
                $stmt = $db->getPDO()->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                if ($stmt->execute([$name, $slug])) {
                    $message = 'Category added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add category.';
                    $messageType = 'error';
                }
            }
            break;
            
        case 'edit':
            $id = intval($_POST['id']);
            if ($name && $id) {
                $stmt = $db->getPDO()->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                if ($stmt->execute([$name, $slug, $id])) {
                    $message = 'Category updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update category.';
                    $messageType = 'error';
                }
            }
            break;
            
        case 'delete':
            $id = intval($_POST['id']);
            // Check if category has products
            $stmt = $db->getPDO()->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch()['count'];
            
            if ($count > 0) {
                $message = 'Cannot delete category with products. Move products first.';
                $messageType = 'error';
            } else {
                $stmt = $db->getPDO()->prepare("DELETE FROM categories WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $message = 'Category deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete category.';
                    $messageType = 'error';
                }
            }
            break;
    }
}

$categories = getCategories();
$page_title = 'Categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - WittyMart Admin</title>
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
                <a href="products.php"><i class="fas fa-box"></i> Products</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="categories.php" class="active"><i class="fas fa-tags"></i> Categories</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1><i class="fas fa-tags"></i> Categories</h1>
                <button class="btn-primary" onclick="openModal('addCategoryModal')">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </header>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Categories Table -->
            <div class="admin-card">
                <div class="card-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Products</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($categories) > 0): ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>#<?php echo $category['id']; ?></td>
                                        <td><?php echo $category['name']; ?></td>
                                        <td><?php echo $category['slug']; ?></td>
                                        <td>
                                            <?php
                                            $stmt = $db->getPDO()->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                                            $stmt->execute([$category['id']]);
                                            echo $stmt->fetch()['count'];
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                        <td>
                                            <button class="btn-sm btn-edit" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo $category['name']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this category?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        <i class="fas fa-tags" style="font-size: 48px; display: block; margin: 20px 0;"></i>
                                        No categories found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Category</h2>
                <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" required placeholder="Enter category name">
                </div>
                <button type="submit" class="btn-primary">Add Category</button>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Category</h2>
                <span class="close" onclick="closeModal('editCategoryModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editCategoryId">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" id="editCategoryName" required placeholder="Enter category name">
                </div>
                <button type="submit" class="btn-primary">Update Category</button>
            </form>
        </div>
    </div>

    <script>
        function editCategory(id, name) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            openModal('editCategoryModal');
        }
    </script>
</body>
</html>
