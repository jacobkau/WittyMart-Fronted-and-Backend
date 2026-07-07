<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

// Only super admin can access this page
requireAdmin();

global $pdo;

$message = '';
$messageType = '';
$user = getCurrentUser();

// Check if user is super admin (you can add a super_admin field or check email)
$is_super_admin = ($_SESSION['user_email'] === 'kaujacob4@gmail.com');

if (!$is_super_admin) {
    $message = 'Access denied. Only super admin can manage administrators.';
    $messageType = 'error';
}

// ===== HANDLE ADMIN ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_super_admin) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_admin':
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = sanitize($_POST['role'] ?? 'admin');
            $phone = sanitize($_POST['phone'] ?? '');
            
            if (empty($name) || empty($email) || empty($password)) {
                $message = 'Name, email, and password are required.';
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Invalid email address.';
                $messageType = 'error';
            } elseif (strlen($password) < 6) {
                $message = 'Password must be at least 6 characters.';
                $messageType = 'error';
            } else {
                try {
                    // Check if email exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $message = 'Email already registered.';
                        $messageType = 'error';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            INSERT INTO users (name, email, password, role, phone) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        if ($stmt->execute([$name, $email, $hashed_password, $role, $phone])) {
                          logActivity(
            'add_admin',
            'Added new admin: ' . $email . ' (Role: ' . $role . ')',
            $_SESSION['user_id'],
            $_SESSION['user_name']
        );;
                            
                            $message = 'Admin added successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to add admin.';
                            $messageType = 'error';
                        }
                    }
                } catch (PDOException $e) {
                    error_log('Add admin error: ' . $e->getMessage());
                    $message = 'Database error occurred.';
                    $messageType = 'error';
                }
            }
            break;
            
        case 'update_admin':
            $id = intval($_POST['id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $role = sanitize($_POST['role'] ?? 'admin');
            $phone = sanitize($_POST['phone'] ?? '');
            $status = sanitize($_POST['status'] ?? 'active');
            
            if (empty($name) || empty($email)) {
                $message = 'Name and email are required.';
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Invalid email address.';
                $messageType = 'error';
            } else {
                try {
                    // Check if email exists for another user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id]);
                    if ($stmt->fetch()) {
                        $message = 'Email already in use by another account.';
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET name = ?, email = ?, role = ?, phone = ?, status = ? 
                            WHERE id = ?
                        ");
                        if ($stmt->execute([$name, $email, $role, $phone, $status, $id])) {
                             logActivity(
            'update_admin',
            'Updated admin: ' . $email . ' (Role: ' . $role . ', Status: ' . $status . ')',
            $_SESSION['user_id'],
            $_SESSION['user_name']
        );
                            
                            $message = 'Admin updated successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update admin.';
                            $messageType = 'error';
                        }
                    }
                } catch (PDOException $e) {
                    error_log('Update admin error: ' . $e->getMessage());
                    $message = 'Database error occurred.';
                    $messageType = 'error';
                }
            }
            break;
            
        case 'delete_admin':
            $id = intval($_POST['id'] ?? 0);
            
            // Prevent deleting yourself
            if ($id == $_SESSION['user_id']) {
                $message = 'You cannot delete your own account.';
                $messageType = 'error';
            } else {
                try {
                    // Get admin email for logging
                    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $admin = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('admin', 'super_admin')");
                    if ($stmt->execute([$id]) && $stmt->rowCount() > 0) {
                         logActivity(
            'delete_admin',
            'Deleted admin: ' . ($admin['email'] ?? 'Unknown'),
            $_SESSION['user_id'],
            $_SESSION['user_name']
        );
                        $message = 'Admin deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Admin not found or cannot be deleted.';
                        $messageType = 'error';
                    }
                } catch (PDOException $e) {
                    error_log('Delete admin error: ' . $e->getMessage());
                    $message = 'Database error occurred.';
                    $messageType = 'error';
                }
            }
            break;
            
        case 'reset_password':
            $id = intval($_POST['id'] ?? 0);
            $new_password = $_POST['new_password'] ?? '';
            
            if (empty($new_password) || strlen($new_password) < 6) {
                $message = 'Password must be at least 6 characters.';
                $messageType = 'error';
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $id])) {
                         logActivity(
            'reset_password',
            'Reset password for admin ID: ' . $id,
            $_SESSION['user_id'],
            $_SESSION['user_name']
        );
        
                        $message = 'Password reset successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to reset password.';
                        $messageType = 'error';
                    }
                } catch (PDOException $e) {
                    error_log('Reset password error: ' . $e->getMessage());
                    $message = 'Database error occurred.';
                    $messageType = 'error';
                }
            }
            break;
    }
}

// ===== GET ALL ADMINS =====
try {
    $stmt = $pdo->query("
        SELECT * FROM users 
        WHERE role IN ('admin', 'super_admin') 
        ORDER BY created_at DESC
    ");
    $admins = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get admins error: ' . $e->getMessage());
    $admins = [];
}

$page_title = 'Admin Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - WittyMart</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
                <?php if ($is_super_admin): ?>
                    <button class="btn-primary" style="margin-bottom:20px" onclick="openModal('addAdminModal')">
                        <i class="fas fa-plus"></i> Add Admin
                    </button>
                <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!$is_super_admin): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    You have limited permissions. Only super admin can manage administrators.
                </div>
            <?php endif; ?>

            <!-- Admins Table -->
            <div class="admin-card" style="padding:14px">
                <div class="card-header">
                    <h2><i class="fas fa-users-cog"></i> Administrators</h2>
                    <span class="badge badge-info">Total: <?php echo count($admins); ?></span>
                </div>
                <div class="card-body" style="padding:14px">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchAdmins" placeholder="Search admins..." onkeyup="filterTable('searchAdmins', 'adminsTable')">
                        </div>
                    </div>

                    <?php if (count($admins) > 0): ?>
                        <table class="admin-table" id="adminsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                   <tr>
    <td>
        <span class="fw-bold text-primary">#<?php echo htmlspecialchars($admin['id']); ?></span>
    </td>
    <td>
        <div class="d-flex align-items-center gap-2">
            <div class="admin-avatar-circle" style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo '#' . substr(md5($admin['name']), 0, 6); ?>; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
            </div>
            <div>
                <div class="fw-semibold"><?php echo htmlspecialchars($admin['name']); ?></div>
                <?php if ($admin['id'] == $_SESSION['user_id']): ?>
                    <span class="badge badge-primary" style="font-size: 9px; padding: 1px 8px;">You</span>
                <?php endif; ?>
            </div>
        </div>
    </td>
    <td>
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fas fa-envelope" style="color: var(--text-muted); font-size: 12px;"></i>
            <?php echo htmlspecialchars($admin['email']); ?>
        </div>
    </td>
    <td>
        <span class="badge badge-<?php echo $admin['role'] === 'super_admin' ? 'super_admin' : 'admin'; ?>">
            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($admin['role']))); ?>
        </span>
    </td>
    <td>
        <?php if (!empty($admin['phone'])): ?>
            <div style="display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-phone" style="color: var(--text-muted); font-size: 12px;"></i>
                <?php echo htmlspecialchars($admin['phone']); ?>
            </div>
        <?php else: ?>
            <span class="text-muted" style="font-size: 12px;">N/A</span>
        <?php endif; ?>
    </td>
    <td>
        <span class="badge badge-<?php echo ($admin['status'] ?? 'active') === 'active' ? 'active' : (($admin['status'] ?? '') === 'suspended' ? 'suspended' : 'inactive'); ?>">
            <?php echo ucfirst(htmlspecialchars($admin['status'] ?? 'Active')); ?>
        </span>
    </td>
    <td>
        <div style="display: flex; flex-direction: column; font-size: 12px;">
            <span><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></span>
            <span class="text-muted" style="font-size: 10px;"><?php echo date('h:i A', strtotime($admin['created_at'])); ?></span>
        </div>
    </td>
    <td>
        <div class="action-buttons" style="display: flex; gap: 5px; flex-wrap: wrap;">
            <?php if ($is_super_admin && $admin['id'] != $_SESSION['user_id']): ?>
                <button class="btn-sm btn-edit" onclick="editAdmin(<?php echo $admin['id']; ?>)" title="Edit Admin">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-sm btn-warning" onclick="resetPassword(<?php echo $admin['id']; ?>)" title="Reset Password">
                    <i class="fas fa-key"></i>
                </button>
                <button class="btn-sm btn-delete" onclick="deleteAdmin(<?php echo $admin['id']; ?>)" title="Delete Admin">
                    <i class="fas fa-trash"></i>
                </button>
            <?php elseif ($admin['id'] == $_SESSION['user_id']): ?>
                <span class="text-muted" style="font-size: 11px;">
                    <i class="fas fa-lock"></i> Current
                </span>
            <?php else: ?>
                <span class="text-muted" style="font-size: 11px;">No actions</span>
            <?php endif; ?>
        </div>
    </td>
</tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted text-center" style="padding: 40px 0;">
                            <i class="fas fa-users" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                            No administrators found
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Admin Modal -->
    <div id="addAdminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add Administrator</h2>
                <span class="close" onclick="closeModal('addAdminModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_admin">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="name" required placeholder="Enter full name">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" required placeholder="Enter email address">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="tel" name="phone" placeholder="Enter phone number">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="add_password" required placeholder="Enter password">
                        <button type="button" class="toggle-password" onclick="togglePassword('add_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="form-text text-muted">Password must be at least 6 characters.</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-tag"></i> Role</label>
                    <select name="role">
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Admin
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editAdminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit Administrator</h2>
                <span class="close" onclick="closeModal('editAdminModal')">&times;</span>
            </div>
            <form method="POST" id="editAdminForm">
                <input type="hidden" name="action" value="update_admin">
                <input type="hidden" name="id" id="edit_admin_id">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="name" id="edit_admin_name" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" id="edit_admin_email" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="tel" name="phone" id="edit_admin_phone">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-tag"></i> Role</label>
                    <select name="role" id="edit_admin_role">
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="status" id="edit_admin_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Admin
                </button>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-key"></i> Reset Password</h2>
                <span class="close" onclick="closeModal('resetPasswordModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="reset_admin_id">
                
                <p>Enter a new password for this administrator.</p>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="reset_password" required placeholder="Enter new password">
                        <button type="button" class="toggle-password" onclick="togglePassword('reset_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="form-text text-muted">Password must be at least 6 characters.</small>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete</h2>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div style="text-align: center; padding: 20px 0;">
                <i class="fas fa-user-minus" style="font-size: 48px; color: #dc3545; margin-bottom: 15px;"></i>
                <p>Are you sure you want to delete this administrator?</p>
                <p class="text-muted">This action cannot be undone.</p>
            </div>
            <form method="POST" id="deleteAdminForm">
                <input type="hidden" name="action" value="delete_admin">
                <input type="hidden" name="id" id="delete_admin_id">
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="btn-secondary" onclick="closeModal('deleteModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash"></i> Delete Admin
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Additional styles for admin management */
        .badge-super_admin { background: #ffc107; color: #333; }
        .badge-admin { background: #17a2b8; color: #fff; }
        .badge-active { background: #28a745; color: #fff; }
        .badge-inactive { background: #6c757d; color: #fff; }
        .badge-suspended { background: #dc3545; color: #fff; }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            width: 100%;
            padding-right: 45px;
        }
        
        .password-wrapper .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 5px 10px;
        }
        
        .password-wrapper .toggle-password:hover {
            color: var(--primary);
        }
        
        .form-text {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }
    </style>

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
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
        
        // ===== TOGGLE PASSWORD =====
        function togglePassword(id) {
            const input = document.getElementById(id);
            const button = input.parentElement.querySelector('.toggle-password');
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                button.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }
        
        // ===== EDIT ADMIN =====
        function editAdmin(id) {
            // Fetch admin data via AJAX
            fetch('includes/ajax.php?action=get_admin&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_admin_id').value = data.admin.id;
                        document.getElementById('edit_admin_name').value = data.admin.name;
                        document.getElementById('edit_admin_email').value = data.admin.email;
                        document.getElementById('edit_admin_phone').value = data.admin.phone || '';
                        document.getElementById('edit_admin_role').value = data.admin.role;
                        document.getElementById('edit_admin_status').value = data.admin.status || 'active';
                        openModal('editAdminModal');
                    } else {
                        alert('Failed to load admin data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading admin data');
                });
        }
        
        // ===== RESET PASSWORD =====
        function resetPassword(id) {
            document.getElementById('reset_admin_id').value = id;
            document.getElementById('reset_password').value = '';
            openModal('resetPasswordModal');
        }
        
        // ===== DELETE ADMIN =====
        function deleteAdmin(id) {
            document.getElementById('delete_admin_id').value = id;
            openModal('deleteModal');
        }
        
        // ===== FILTER TABLE =====
        function filterTable(inputId, tableId) {
            const input = document.getElementById(inputId);
            const table = document.getElementById(tableId);
            if (!input || !table) return;

            const filter = input.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert-persistent').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>
