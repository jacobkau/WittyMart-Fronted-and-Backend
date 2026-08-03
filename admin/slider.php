<?php
require_once 'includes/config.php';
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

// Get single slider for editing via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_slide' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("SELECT * FROM slider_images WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $slide = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'slide' => $slide]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
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
    <style>
        .modal-content {
            max-width: 600px;
        }
        .image-preview {
            max-width: 200px;
            max-height: 120px;
            object-fit: cover;
            border-radius: 4px;
            margin: 10px 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-sm {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-edit {
            background-color: #28a745;
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-primary {
            background-color: #05573c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .admin-table img {
            width: 80px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 {
            margin: 0;
        }
        .close {
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }
        .close:hover {
            color: #333;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .admin-wrapper {
            display: flex;
        }
        .admin-main {
            flex: 1;
            padding: 20px;
        }
        .admin-header {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .admin-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-body {
            padding: 20px;
            overflow-x: auto;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        .admin-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .admin-table tr:hover {
            background: #f8f9fa;
        }
    </style>
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
                    <?php if (count($sliders) > 0): ?>
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
                                                 alt="<?php echo htmlspecialchars($slide['title']); ?>">
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
                    <?php else: ?>
                        <p style="text-align:center;padding:40px 0;color:#999;">
                            <i class="fas fa-image" style="font-size:48px;display:block;margin-bottom:10px;"></i>
                            No slider images found. Click "Add Slide" to get started.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add Slide</h2>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Title *</label>
                    <input type="text" name="title" required placeholder="Enter slide title">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Subtitle</label>
                    <input type="text" name="subtitle" placeholder="Enter subtitle">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Image *</label>
                    <input type="file" name="image" accept="image/*" required>
                    <small style="color:#999;">Recommended size: 1200x500px</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-link"></i> Link (URL)</label>
                    <input type="text" name="link" placeholder="e.g., shop.php">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-hand-pointer"></i> Button Text</label>
                    <input type="text" name="button_text" value="Shop Now" placeholder="Button text">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-up"></i> Display Order</label>
                    <input type="number" name="display_order" value="0" min="0">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%;">
                    <i class="fas fa-save"></i> Add Slide
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Slide</h2>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="current_image" id="edit_current_image">
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Title *</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Subtitle</label>
                    <input type="text" name="subtitle" id="edit_subtitle">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Current Image</label>
                    <div id="edit_image_preview"></div>
                    <input type="file" name="image" accept="image/*">
                    <small style="color:#999;">Leave empty to keep current image</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-link"></i> Link (URL)</label>
                    <input type="text" name="link" id="edit_link" placeholder="e.g., shop.php">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-hand-pointer"></i> Button Text</label>
                    <input type="text" name="button_text" id="edit_button_text" value="Shop Now">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-up"></i> Display Order</label>
                    <input type="number" name="display_order" id="edit_display_order" min="0">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%;">
                    <i class="fas fa-save"></i> Update Slide
                </button>
            </form>
        </div>
    </div>

    <script>
        // ===== MODAL FUNCTIONS =====
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
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(function(modal) {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });

        // ===== EDIT SLIDE FUNCTION =====
        function editSlide(id) {
            // Show loading state
            const editModal = document.getElementById('editModal');
            
            // Fetch slide data
            fetch('slider.php?action=get_slide&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const slide = data.slide;
                        
                        // Populate form fields
                        document.getElementById('edit_id').value = slide.id;
                        document.getElementById('edit_title').value = slide.title || '';
                        document.getElementById('edit_subtitle').value = slide.subtitle || '';
                        document.getElementById('edit_link').value = slide.link || '';
                        document.getElementById('edit_button_text').value = slide.button_text || 'Shop Now';
                        document.getElementById('edit_display_order').value = slide.display_order || 0;
                        document.getElementById('edit_status').value = slide.status || 'active';
                        document.getElementById('edit_current_image').value = slide.image_path || '';
                        
                        // Show current image
                        const preview = document.getElementById('edit_image_preview');
                        if (slide.image_path) {
                            preview.innerHTML = '<img src="../' + slide.image_path + '" alt="Current image" style="max-width:200px;max-height:120px;object-fit:cover;border-radius:4px;margin:10px 0;">';
                        } else {
                            preview.innerHTML = '<p style="color:#999;">No image uploaded</p>';
                        }
                        
                        // Open the modal
                        openModal('editModal');
                    } else {
                        alert('Failed to load slide data: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading slide data. Please check the console for details.');
                });
        }

        // ===== IMAGE PREVIEW FOR ADD MODAL =====
        document.querySelector('#addModal input[type="file"]').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Show preview if you have a preview element
                    // You can add a preview div in the add modal if needed
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // ===== AUTO-HIDE ALERTS =====
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>
