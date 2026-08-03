<?php
session_start();
require_once '../includes/config.php';
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $title = sanitize($_POST['title'] ?? '');
                $subtitle = sanitize($_POST['subtitle'] ?? '');
                $link = sanitize($_POST['link'] ?? '');
                $button_text = sanitize($_POST['button_text'] ?? 'Shop Now');
                $display_order = intval($_POST['display_order'] ?? 0);
                $status = sanitize($_POST['status'] ?? 'active');
                
                // Handle image upload
                $image_path = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/slider/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $image_name = time() . '_' . basename($_FILES['image']['name']);
                    $upload_path = $upload_dir . $image_name;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                        $image_path = 'uploads/slider/' . $image_name;
                    }
                }
                
                if ($title && $image_path) {
                    $stmt = $pdo->prepare("
                        INSERT INTO slider_images (title, subtitle, image_path, link, button_text, display_order, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $subtitle, $image_path, $link, $button_text, $display_order, $status]);
                    $message = 'Slider image added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Title and image are required.';
                    $messageType = 'error';
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $title = sanitize($_POST['title'] ?? '');
                $subtitle = sanitize($_POST['subtitle'] ?? '');
                $link = sanitize($_POST['link'] ?? '');
                $button_text = sanitize($_POST['button_text'] ?? 'Shop Now');
                $display_order = intval($_POST['display_order'] ?? 0);
                $status = sanitize($_POST['status'] ?? 'active');
                
                $image_path = $_POST['current_image'] ?? '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/slider/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $image_name = time() . '_' . basename($_FILES['image']['name']);
                    $upload_path = $upload_dir . $image_name;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                        // Delete old image
                        if ($image_path && file_exists('../' . $image_path)) {
                            unlink('../' . $image_path);
                        }
                        $image_path = 'uploads/slider/' . $image_name;
                    }
                }
                
                if ($title && $id) {
                    $stmt = $pdo->prepare("
                        UPDATE slider_images 
                        SET title = ?, subtitle = ?, image_path = ?, link = ?, button_text = ?, display_order = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $subtitle, $image_path, $link, $button_text, $display_order, $status, $id]);
                    $message = 'Slider image updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Title and valid ID are required.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $stmt = $pdo->prepare("SELECT image_path FROM slider_images WHERE id = ?");
                $stmt->execute([$id]);
                $slider = $stmt->fetch();
                
                if ($slider && $slider['image_path'] && file_exists('../' . $slider['image_path'])) {
                    unlink('../' . $slider['image_path']);
                }
                
                $stmt = $pdo->prepare("DELETE FROM slider_images WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Slider image deleted successfully!';
                $messageType = 'success';
                break;
        }
    } catch (PDOException $e) {
        error_log('Slider error: ' . $e->getMessage());
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get all slider images
try {
    $stmt = $pdo->query("SELECT * FROM slider_images ORDER BY display_order ASC, created_at DESC");
    $sliders = $stmt->fetchAll();
} catch (PDOException $e) {
    $sliders = [];
}

$page_title = 'Manage Slider';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Slider - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <button class="btn-primary" onclick="openModal('addModal')">
                    <i class="fas fa-plus"></i> Add Slide
                </button>
            </header>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="admin-card">
                <div class="card-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Subtitle</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sliders as $slide): ?>
                                <tr>
                                    <td>
                                        <img src="../<?php echo $slide['image_path']; ?>" 
                                             alt="<?php echo htmlspecialchars($slide['title']); ?>" 
                                             style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    </td>
                                    <td><?php echo htmlspecialchars($slide['title']); ?></td>
                                    <td><?php echo htmlspecialchars($slide['subtitle']); ?></td>
                                    <td><?php echo $slide['display_order']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $slide['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $slide['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-sm btn-edit" onclick="editSlide(<?php echo $slide['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                                            <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Delete this slide?')">
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
    
    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Slide</h2>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Subtitle</label>
                    <input type="text" name="subtitle">
                </div>
                <div class="form-group">
                    <label>Image *</label>
                    <input type="file" name="image" accept="image/*" required>
                </div>
                <div class="form-group">
                    <label>Link (URL)</label>
                    <input type="text" name="link" placeholder="e.g., shop.php">
                </div>
                <div class="form-group">
                    <label>Button Text</label>
                    <input type="text
