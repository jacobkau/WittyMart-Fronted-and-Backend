<?php
require_once 'includes/config.php';
require_once 'includes/cloudinary_helper.php'; // Add Cloudinary helper
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

// ============================================
// CLOUDINARY IMAGE FUNCTIONS FOR SLIDER
// ============================================

/**
 * Upload slider image to Cloudinary (with local fallback)
 */
function uploadSliderImageToCloudinary($file, $folder = 'slider') {
    global $cloudinary;
    
    $result = [
        'success' => false,
        'path' => '',
        'url' => '',
        'public_id' => '',
        'error' => ''
    ];
    
    // Validate file
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'No file uploaded or upload error.';
        return $result;
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        $result['error'] = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.';
        return $result;
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        $result['error'] = 'File size exceeds 5MB limit.';
        return $result;
    }
    
    // Generate unique filename for local storage
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Try Cloudinary first if available
    if ($cloudinary) {
        try {
            $upload_result = uploadToCloudinary($file['tmp_name'], $folder);
            
            if ($upload_result['success']) {
                $result['success'] = true;
                $result['url'] = $upload_result['url'];
                $result['public_id'] = $upload_result['public_id'];
                $result['path'] = 'uploads/slider/' . $filename; // Keep for fallback
                
                // Also save locally as fallback
                $upload_dir = '../uploads/slider/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $target_path = $upload_dir . $filename;
                move_uploaded_file($file['tmp_name'], $target_path);
                
                error_log('Cloudinary slider upload successful: ' . $upload_result['public_id']);
                return $result;
            } else {
                error_log('Cloudinary slider upload failed: ' . ($upload_result['error'] ?? 'Unknown error'));
                // Fall through to local upload
            }
        } catch (Exception $e) {
            error_log('Cloudinary slider upload exception: ' . $e->getMessage());
            // Fall through to local upload
        }
    }
    
    // Fallback to local upload
    $upload_dir = '../uploads/slider/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $result['success'] = true;
        $result['path'] = 'uploads/slider/' . $filename;
        $result['url'] = BASE_URL . 'uploads/slider/' . $filename;
        error_log('Local slider upload successful: ' . $filename);
    } else {
        $result['error'] = 'Failed to save file locally.';
        error_log('Failed to move slider uploaded file to: ' . $target_path);
    }
    
    return $result;
}

/**
 * Delete slider image from Cloudinary (if exists)
 */
function deleteSliderImageFromCloudinary($image_path) {
    global $cloudinary;
    
    if (empty($image_path)) {
        return true;
    }
    
    // Check if it's a Cloudinary URL by looking for cloudinary.com in the path
    if (strpos($image_path, 'cloudinary.com') !== false) {
        // Try to extract public_id from URL
        $pattern = '/\/upload\/(?:v\d+\/)?([^\/]+\/[^\/]+)(?:\.[^.]+)?$/';
        if (preg_match($pattern, $image_path, $matches)) {
            $public_id = $matches[1];
            return deleteFromCloudinary($public_id);
        }
        // Try alternative pattern for direct URLs
        $parsed_url = parse_url($image_path);
        $path = ltrim($parsed_url['path'] ?? '', '/');
        $parts = explode('/', $path);
        $upload_index = array_search('upload', $parts);
        if ($upload_index !== false && isset($parts[$upload_index + 1])) {
            $start = $upload_index + 1;
            if (isset($parts[$start]) && strpos($parts[$start], 'v') === 0) {
                $start++;
            }
            if (isset($parts[$start])) {
                $public_id = implode('/', array_slice($parts, $start));
                $public_id = preg_replace('/\.[^.]+$/', '', $public_id);
                return deleteFromCloudinary($public_id);
            }
        }
    }
    
    // Local file deletion
    $relative_path = ltrim($image_path, '/');
    $full_path = '../' . $relative_path;
    
    if (file_exists($full_path) && is_file($full_path)) {
        if (unlink($full_path)) {
            error_log('Deleted local slider image: ' . $full_path);
            return true;
        } else {
            error_log('Failed to delete local slider image: ' . $full_path);
        }
    } else {
        error_log('Local slider image not found for deletion: ' . $full_path);
    }
    
    return false;
}

/**
 * Get slider image URL (supports Cloudinary and local)
 */
function getSliderImageUrl($image_path) {
    // If no image, return placeholder
    if (empty($image_path)) {
        return BASE_URL . 'uploads/slider/default-slide.jpg';
    }
    
    // If it's already a full URL (Cloudinary), return it
    if (strpos($image_path, 'http://') === 0 || strpos($image_path, 'https://') === 0) {
        return $image_path;
    }
    
    // Clean the path - remove leading slashes and '../'
    $image_path = ltrim($image_path, '/');
    $image_path = str_replace('../', '', $image_path);
    
    // Return full URL
    return BASE_URL . $image_path;
}

// ============================================
// HANDLE CRUD OPERATIONS
// ============================================

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
                
                // Handle image upload with Cloudinary
                $image_path = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadSliderImageToCloudinary($_FILES['image']);
                    if ($upload_result['success']) {
                        // Store the URL or path - prefer Cloudinary URL
                        $image_path = !empty($upload_result['url']) ? $upload_result['url'] : $upload_result['path'];
                    } else {
                        $message = 'Image upload failed: ' . $upload_result['error'];
                        $messageType = 'error';
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
                    // Delete old image if exists
                    if (!empty($image_path)) {
                        deleteSliderImageFromCloudinary($image_path);
                    }
                    
                    $upload_result = uploadSliderImageToCloudinary($_FILES['image']);
                    if ($upload_result['success']) {
                        $image_path = !empty($upload_result['url']) ? $upload_result['url'] : $upload_result['path'];
                    } else {
                        $message = 'Image upload failed: ' . $upload_result['error'];
                        $messageType = 'error';
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
                
                if ($slider && $slider['image_path']) {
                    deleteSliderImageFromCloudinary($slider['image_path']);
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
        .cloudinary-badge {
            display: inline-block;
            font-size: 8px;
            background: #3448C5;
            color: #fff;
            padding: 1px 6px;
            border-radius: 3px;
            margin-top: 2px;
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
        .file-input-wrapper .btn-secondary {
            background: #f8f9fa;
            border: 2px dashed #ccc;
            padding: 10px 20px;
            border-radius: 8px;
            width: 100%;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #555;
        }
        .file-input-wrapper .btn-secondary:hover {
            background: #e8f5f0;
            border-color: #05573c;
            color: #05573c;
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
                                            <img src="<?php echo htmlspecialchars(getSliderImageUrl($slide['image_path'])); ?>" 
                                                 alt="<?php echo htmlspecialchars($slide['title']); ?>"
                                                 onerror="this.src='<?php echo BASE_URL; ?>uploads/slider/default-slide.jpg'">
                                            <?php if (strpos($slide['image_path'] ?? '', 'cloudinary.com') !== false): ?>
                                                <br><span class="cloudinary-badge"><i class="fas fa-cloud"></i> Cloud</span>
                                            <?php endif; ?>
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
                    <div class="file-input-wrapper">
                        <div class="btn-secondary">
                            <i class="fas fa-cloud-upload-alt"></i> Choose Image
                        </div>
                        <input type="file" name="image" accept="image/*" required>
                    </div>
                    <small style="color:#999;">Recommended size: 1200x500px. Will be uploaded to Cloudinary if available.</small>
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
                    <div class="file-input-wrapper">
                        <div class="btn-secondary">
                            <i class="fas fa-cloud-upload-alt"></i> Change Image
                        </div>
                        <input type="file" name="image" accept="image/*">
                    </div>
                    <small style="color:#999;">Leave empty to keep current image. Will be uploaded to Cloudinary if available.</small>
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
                            const imgUrl = slide.image_path.includes('cloudinary.com') 
                                ? slide.image_path 
                                : '../' + slide.image_path;
                            preview.innerHTML = '<img src="' + imgUrl + '" alt="Current image" style="max-width:200px;max-height:120px;object-fit:cover;border-radius:4px;margin:10px 0;">' +
                                (slide.image_path.includes('cloudinary.com') ? '<br><span style="font-size:10px;color:#3448C5;"><i class="fas fa-cloud"></i> Cloudinary</span>' : '');
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

        // ===== FILE INPUT STYLING =====
        document.querySelectorAll('.file-input-wrapper input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function(e) {
                var fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                var parent = this.closest('.file-input-wrapper');
                var btn = parent.querySelector('.btn-secondary');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-file"></i> ' + fileName;
                }
            });
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
