<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

requireAdmin();

global $pdo;

$message = '';
$messageType = '';
$user = getCurrentUser();

// ===== HANDLE PROFILE UPDATE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            
            if (empty($name) || empty($email)) {
                $message = 'Name and email are required.';
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
                $messageType = 'error';
            } else {
                try {
                    // Check if email already exists for another user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        $message = 'Email already in use by another account.';
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                        if ($stmt->execute([$name, $email, $phone, $_SESSION['user_id']])) {
                            // Update session
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_email'] = $email;
                            
                            // Log activity
                            logActivity('profile_update', 'Updated profile information');
                            
                            $message = 'Profile updated successfully!';
                            $messageType = 'success';
                            
                            // Refresh user data
                            $user = getCurrentUser();
                        } else {
                            $message = 'Failed to update profile.';
                            $messageType = 'error';
                        }
                    }
                } catch (PDOException $e) {
                    error_log('Profile update error: ' . $e->getMessage());
                    $message = 'Database error occurred.';
                    $messageType = 'error';
                }
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message = 'All password fields are required.';
                $messageType = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = 'New passwords do not match.';
                $messageType = 'error';
            } elseif (strlen($new_password) < 6) {
                $message = 'Password must be at least 6 characters.';
                $messageType = 'error';
            } else {
                try {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user_data = $stmt->fetch();
                    
                    if (!$user_data || !password_verify($current_password, $user_data['password'])) {
                        $message = 'Current password is incorrect.';
                        $messageType = 'error';
                    } else {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                            // Log activity
                            logActivity('password_change', 'Changed account password');
                            
                            $message = 'Password changed successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to change password.';
                            $messageType = 'error';
                        }
                    }
                } catch (PDOException $e) {
                    error_log('Password change error: ' . $e->getMessage());
                    $message = 'Database error occurred.';
                    $messageType = 'error';
                }
            }
            break;
            
        case 'update_notifications':
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $order_updates = isset($_POST['order_updates']) ? 1 : 0;
         
            logActivity('notification_update', 'Updated notification preferences');
            
            $message = 'Notification preferences updated successfully!';
            $messageType = 'success';
            break;
         case 'update_profile_picture':
         
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $max_file_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $message = 'Invalid file type. Please upload JPG, PNG, GIF, or WEBP.';
                    $messageType = 'error';
                } elseif ($file['size'] > $max_file_size) {
                    $message = 'File too large. Maximum size is 5MB.';
                    $messageType = 'error';
                } else {
                    try {
                     
                        $upload_dir = 'uploads/profile_pictures/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                    
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                        $filepath = $upload_dir . $filename;
                        
                        
                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                           
                            if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                                unlink($user['profile_picture']);
                            }
                            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                            if ($stmt->execute([$filepath, $_SESSION['user_id']])) {
                                logActivity('profile_picture_update', 'Updated profile picture');
                                $message = 'Profile picture updated successfully!';
                                $messageType = 'success';
                                $user = getCurrentUser();
                            } else {
                                $message = 'Failed to update database.';
                                $messageType = 'error';
                            }
                        } else {
                            $message = 'Failed to upload file.';
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        error_log('Profile picture upload error: ' . $e->getMessage());
                        $message = 'An error occurred during upload.';
                        $messageType = 'error';
                    }
                }
            } else {
                $message = 'Please select a file to upload.';
                $messageType = 'error';
            }
            break;
    }
}

// ===== GET USER ACTIVITY =====
$activities = getUserActivities($_SESSION['user_id'], 10);

$page_title = 'Profile Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - WittyMart Admin</title>
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
             
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Profile Grid -->
            <div class="profile-grid" style="margin-bottom:25px:">
                <!-- Profile Information -->
                <div class="admin-card" style="padding:14px">
                    <div class="card-header">
                        <h2> Profile Information</h2>
                    </div>
                    <div class="card-body" style="padding:14px">

                          <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
                            <input type="hidden" name="action" value="update_profile_picture">
                            <div class="profile-avatar-section">
                                <div class="profile-avatar">
                                    <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="profile-img">
                                    <?php else: ?>
                                        <div class="avatar-circle">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="avatar-upload">
                                    <label for="profile_picture" class="btn-upload">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </label>
                                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display: none;">
                                    <button type="submit" class="btn-upload-submit" style="display: none;" id="uploadBtn">
                                        <i class="fas fa-upload"></i> Upload
                                    </button>
                                    <p class="text-muted small" style="margin-top: 8px;">Max size: 5MB. Supported: JPG, PNG, GIF, WEBP</p>
                                </div>
                            </div>
                        </form>
                        
                        <form method="POST" style="padding:14px">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="profile-avatar">
                                <div class="avatar-circle">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <div>
                                    <h3><?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></h3>
                                    <p class="text-muted"><?php echo htmlspecialchars($user['role'] ?? 'Administrator'); ?></p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Full Name</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter phone number">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Joined</label>
                                <input type="text" value="<?php echo date('F d, Y', strtotime($user['created_at'] ?? 'now')); ?>" disabled>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="admin-card" style="padding:14px">
                    <div class="card-header">
                        <h2><i class="fas fa-lock"></i> Change Password</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label><i class="fas fa-key"></i> Current Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="current_password" id="current_password" required placeholder="Enter current password">
                                    <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="new_password" id="new_password" required placeholder="Enter new password">
                                    <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
                                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="admin-card" style="padding:14px">
                    <div class="card-header">
                        <h2><i class="fas fa-clock"></i> Recent Activity</h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($activities) > 0): ?>
                            <div class="activity-list">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-<?php echo getActivityIcon($activity['action']); ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p><?php echo htmlspecialchars($activity['description'] ?? $activity['action']); ?></p>
                                            <span class="activity-time">
                                                <i class="fas fa-clock"></i> 
                                                <?php echo timeAgo($activity['created_at']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center" style="padding: 20px 0;">
                                <i class="fas fa-inbox" style="font-size: 24px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                No recent activity
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notification Preferences -->
                <div class="admin-card" style="padding:14px">
                    <div class="card-header">
                        <h2><i class="fas fa-bell"></i> Notification Preferences</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <div class="notification-option">
                                <div>
                                    <h4><i class="fas fa-envelope"></i> Email Notifications</h4>
                                    <p class="text-muted">Receive email notifications about system updates</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="email_notifications" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="notification-option">
                                <div>
                                    <h4><i class="fas fa-shopping-cart"></i> Order Updates</h4>
                                    <p class="text-muted">Get notified about new orders and status changes</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="order_updates" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        /* Profile Page Styles */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .profile-avatar {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: var(--bg);
            border-radius: 10px;
            margin-bottom: 20px;
        }
         .profile-avatar .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-circle {
            font-size: 60px;
            color: var(--primary);
        }
        
        .avatar-circle i {
            font-size: 80px;
        }
        .avatar-upload {
            flex: 1;
        }
        
        .btn-upload {
            display: inline-block;
            padding: 8px 20px;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 14px;
        }
        
        .btn-upload:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-upload-submit {
            display: inline-block;
            padding: 8px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .btn-upload-submit:hover {
            background: #218838;
        }
        
        .password-wrapper {
            display: flex;
            position: relative;
        }
        
        .password-wrapper input {
            flex: 1;
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
            font-size: 14px;
        }
        
        .password-wrapper .toggle-password:hover {
            color: var(--primary);
        }
        
        .password-strength {
            height: 4px;
            margin-top: 8px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .password-strength.weak { background: #dc3545; width: 25%; }
        .password-strength.medium { background: #ffc107; width: 50%; }
        .password-strength.strong { background: #28a745; width: 75%; }
        .password-strength.very-strong { background: #28a745; width: 100%; }
        
        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(5, 87, 60, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content p {
            margin: 0;
            font-size: 14px;
            color: var(--text);
        }
        
        .activity-time {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .notification-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .notification-option:last-child {
            border-bottom: none;
        }
        
        .notification-option h4 {
            margin: 0;
            font-size: 14px;
            color: var(--text);
        }
        
        .notification-option h4 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .notification-option p {
            margin: 5px 0 0;
            font-size: 12px;
        }
        
        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
            flex-shrink: 0;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: 0.3s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background: #fff;
            transition: 0.3s;
            border-radius: 50%;
        }
        
        .switch input:checked + .slider {
            background: var(--primary);
        }
        
        .switch input:checked + .slider:before {
            transform: translateX(22px);
        }
        .user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    color: var(--text);
}

.user-info i {
    color: var(--primary);
    font-size: 18px;
}

.role-badge {
    display: inline-block;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-badge.admin {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
}

.role-badge.super_admin {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: #fff;
    box-shadow: 0 2px 10px rgba(245, 87, 108, 0.3);
}

.role-badge.user {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: #fff;
    box-shadow: 0 2px 10px rgba(79, 172, 254, 0.3);
}

.role-badge.manager {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    color: #1a1a2e;
    box-shadow: 0 2px 10px rgba(67, 233, 123, 0.3);
}


.role-badge.super_admin {
    animation: pulseGlow 2s ease-in-out infinite;
}

@keyframes pulseGlow {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
        
        /* Responsive */
        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .profile-avatar {
                flex-direction: column;
                text-align: center;
            }
            
            .notification-option {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>

    <script>
        // Toggle password visibility
        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const button = input.parentElement.querySelector('.toggle-password');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Password strength indicator
        document.getElementById('new_password')?.addEventListener('keyup', function() {
            const strength = document.getElementById('passwordStrength');
            const password = this.value;
            
            let score = 0;
            if (password.length >= 6) score++;
            if (password.length >= 10) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            const levels = ['', 'weak', 'medium', 'strong', 'very-strong'];
            strength.className = 'password-strength ' + (levels[score] || '');
            strength.style.display = password ? 'block' : 'none';
        });
          document.getElementById('profile_picture')?.addEventListener('change', function() {
            const uploadBtn = document.getElementById('uploadBtn');
            if (this.files.length > 0) {
                uploadBtn.style.display = 'inline-block';
                // Auto-submit after a short delay to show the button
                setTimeout(() => {
                    document.getElementById('profilePictureForm').submit();
                }, 500);
            } else {
                uploadBtn.style.display = 'none';
            }
        });
        
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
