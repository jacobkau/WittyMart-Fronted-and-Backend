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
                INSERT INTO testimonials (customer_name, content, rating, status, display_order, created_at) 
                VALUES (?, ?, ?, ?, 0, NOW())
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
    } elseif ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');
        try {
            $stmt = $pdo->prepare("UPDATE testimonials SET status = ? WHERE id = ?");
            if ($stmt->execute([$status, $id])) {
                $message = 'Status updated successfully!';
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

// ===== GET SINGLE TESTIMONIAL FOR EDITING =====
$edit_testimonial = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_testimonial = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get testimonial for edit error: ' . $e->getMessage());
    }
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
    <style>
        .modal-content {
            max-width: 600px;
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
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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
        .btn-toggle {
            background-color: #17a2b8;
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
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        .admin-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            flex-wrap: wrap;
            gap: 10px;
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
        .rating-stars {
            color: #ffc107;
            font-size: 16px;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
    </style>
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
                                        <td><?php echo htmlspecialchars(substr($testimonial['content'], 0, 100)); ?>...</td>
                                        <td>
                                            <span class="rating-stars">
                                                <?php echo str_repeat('⭐', $testimonial['rating']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $testimonial['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($testimonial['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
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
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo $testimonial['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $testimonial['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn-sm btn-toggle" title="Toggle Status">
                                                        <i class="fas <?php echo $testimonial['status'] === 'active' ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
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
                            <i class="fas fa-comment-dots" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                            No testimonials yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
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
                    <label>Customer Name *</label>
                    <input type="text" name="name" required placeholder="Enter customer name">
                </div>
                <div class="form-group">
                    <label>Testimonial *</label>
                    <textarea name="content" rows="4" required placeholder="Write the testimonial content"></textarea>
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
                        <option value="active">Active (Show on site)</option>
                        <option value="inactive">Inactive (Hidden)</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width:100%;">Add Testimonial</button>
            </form>
        </div>
    </div>

    <!-- Edit Testimonial Modal -->
    <div id="editTestimonialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Testimonial</h2>
                <span class="close" onclick="closeModal('editTestimonialModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Customer Name *</label>
                    <input type="text" name="name" id="edit_name" required placeholder="Enter customer name">
                </div>
                <div class="form-group">
                    <label>Testimonial *</label>
                    <textarea name="content" id="edit_content" rows="4" required placeholder="Write the testimonial content"></textarea>
                </div>
                <div class="form-group">
                    <label>Rating</label>
                    <select name="rating" id="edit_rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>"><?php echo str_repeat('⭐', $i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Active (Show on site)</option>
                        <option value="inactive">Inactive (Hidden)</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width:100%;">Update Testimonial</button>
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

        // ===== EDIT TESTIMONIAL =====
        function editTestimonial(id) {
            // Fetch testimonial data via AJAX
            fetch('testimonials.php?action=get&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const testimonial = data.testimonial;
                        document.getElementById('edit_id').value = testimonial.id;
                        document.getElementById('edit_name').value = testimonial.customer_name;
                        document.getElementById('edit_content').value = testimonial.content;
                        document.getElementById('edit_rating').value = testimonial.rating;
                        document.getElementById('edit_status').value = testimonial.status;
                        openModal('editTestimonialModal');
                    } else {
                        alert('Failed to load testimonial data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading testimonial data');
                });
        }
    </script>

    <?php
    // Handle AJAX request for getting single testimonial
    if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $testimonial = $stmt->fetch();
            if ($testimonial) {
                echo json_encode(['success' => true, 'testimonial' => $testimonial]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Testimonial not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    ?>
</body>
</html>
