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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = sanitize($_POST['name'] ?? '');
    $slug = generateSlug($name);
    
    try {
        switch ($action) {
            case 'add':
                if ($name) {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
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
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
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
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetch()['count'];
                
                if ($count > 0) {
                    $message = 'Cannot delete category with products. Move products first.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
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
    } catch (PDOException $e) {
        error_log('Category action error: ' . $e->getMessage());
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ===== GET CATEGORIES =====
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
    $categories = [];
}

$page_title = 'Categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
      <?php include "sidebar.php" ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px">
                <button class="btn-primary" onclick="openModal('addCategoryModal')">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Categories Table -->
            <div class="admin-card">
                <div class="card-body">
                    <?php if (count($categories) > 0): ?>
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
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($category['id']); ?></td>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                        <td>
                                            <?php
                                            try {
                                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                                                $stmt->execute([$category['id']]);
                                                echo $stmt->fetch()['count'];
                                            } catch (PDOException $e) {
                                                echo '0';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                        <td>
                                            <button class="btn-sm btn-edit" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>')">
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
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted text-center" style="padding: 40px 0;">
                            <i class="fas fa-tags" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                            No categories found
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add Category</h2>
                <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Category Name</label>
                    <input type="text" name="name" required placeholder="Enter category name">
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Category
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Category</h2>
                <span class="close" onclick="closeModal('editCategoryModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editCategoryId">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Category Name</label>
                    <input type="text" name="name" id="editCategoryName" required placeholder="Enter category name">
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Category
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
        
        function editCategory(id, name) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            openModal('editCategoryModal');
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
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>
</html>
