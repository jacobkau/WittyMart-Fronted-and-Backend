<?php
require_once 'includes/config.php';
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

// ===== HANDLE FORM SUBMISSIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $rating = intval($_POST['rating'] ?? 5);
        $status = sanitize($_POST['status'] ?? 'active');
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO testimonials (customer_name, content, rating, status) 
                VALUES (?, ?, ?, ?)
            ");
            if ($stmt->execute([$name, $content, $rating, $status])) {
                $message = 'Testimonial added successfully!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $rating = intval($_POST['rating'] ?? 5);
        $status = sanitize($_POST['status'] ?? 'active');
        
        try {
            $stmt = $pdo->prepare("
                UPDATE testimonials 
                SET customer_name = ?, content = ?, rating = ?, status = ? 
                WHERE id = ?
            ");
            if ($stmt->execute([$name, $content, $rating, $status, $id])) {
                $message = 'Testimonial updated successfully!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = 'Testimonial deleted successfully!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ===== GET TESTIMONIALS =====
try {
    $stmt = $pdo->query("SELECT * FROM testimonials ORDER BY display_order ASC, created_at DESC");
    $testimonials = $stmt->fetchAll();
} catch (PDOException $e) {
    $testimonials = [];
}

$page_title = 'Manage Testimonials';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
       <?php include "sidebar.php"?>


<main class="admin-main">
  <div class="admin-card">
    <div class="card-header">
        <h2><i class="fas fa-comment-dots"></i> Testimonials</h2>
        <span class="badge badge-info">Total: <?php echo count($testimonials); ?></span>
        <button class="btn-primary" onclick="openModal('addTestimonialModal')">
            <i class="fas fa-plus"></i> Add Testimonial
        </button>
    </div>
    <div class="card-body">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($testimonials)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Content</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testimonials as $testimonial): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($testimonial['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($testimonial['content'], 0, 100)) . '...'; ?></td>
                            <td>
                                <?php echo str_repeat('⭐', $testimonial['rating']); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $testimonial['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($testimonial['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-sm btn-edit" onclick="editTestimonial(<?php echo $testimonial['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $testimonial['id']; ?>">
                                    <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Delete this testimonial?')">
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
                <i class="fas fa-comment-dots" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                No testimonials yet.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Add Testimonial Modal -->
<div id="addTestimonialModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Add Testimonial</h2>
            <span class="close" onclick="closeModal('addTestimonialModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Customer Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Testimonial</label>
                <textarea name="content" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <label>Rating</label>
                <select name="rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?php echo $i; ?>"><?php echo str_repeat('⭐', $i); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">Add Testimonial</button>
        </form>
    </div>
</div>
</main>
</div>
</body>
<script>
function editTestimonial(id) {
    alert('Edit functionality coming soon!');
}
</script>
