<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: home.php');
        exit();
    }
} catch (PDOException $e) {
    error_log('Get user error: ' . $e->getMessage());
    $user = null;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($name) || empty($email)) {
            $message = 'Name and email are required.';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $user_id]);
                
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $message = 'Profile updated successfully!';
                $messageType = 'success';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
            } catch (PDOException $e) {
                error_log('Update profile error: ' . $e->getMessage());
                $message = 'Failed to update profile.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'change_password') {
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
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch();
                
                if (password_verify($current_password, $user_data['password'])) {
                    $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$new_hashed, $user_id]);
                    
                    $message = 'Password changed successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Current password is incorrect.';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                error_log('Change password error: ' . $e->getMessage());
                $message = 'Failed to change password.';
                $messageType = 'error';
            }
        }
    }
}

// Get user orders
try {
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get orders error: ' . $e->getMessage());
    $orders = [];
}

$page_title = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .profile-header {
            background: #05573c;
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .profile-header h1 {
            margin: 0;
            font-size: 28px;
        }
        
        .profile-header p {
            margin: 5px 0 0;
            opacity: 0.8;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .profile-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .profile-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .profile-card .form-group {
            margin-bottom: 15px;
        }
        
        .profile-card .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .profile-card .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .profile-card .form-group input:focus {
            outline: none;
            border-color: #05573c;
        }
        
        .profile-card .btn-primary {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .profile-card .btn-primary:hover {
            background: #03402c;
        }
        
        .profile-card .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .orders-list {
            margin-top: 30px;
        }
        
        .order-item {
            background: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .order-item .order-number {
            font-weight: 600;
            color: #05573c;
        }
        
        .order-item .order-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .order-item .order-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .order-item .order-status.processing {
            background: #cce5ff;
            color: #004085;
        }
        
        .order-item .order-status.shipped {
            background: #d4edda;
            color: #155724;
        }
        
        .order-item .order-status.delivered {
            background: #28a745;
            color: #fff;
        }
        
        .order-item .order-status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-item .order-total {
            font-weight: 700;
            color: #05573c;
        }
        
        .order-item .order-date {
            color: #888;
            font-size: 13px;
        }
        
        .order-item .view-btn {
            background: #05573c;
            color: #fff;
            padding: 6px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        
        .order-item .view-btn:hover {
            background: #03402c;
        }
        
        .no-orders {
            text-align: center;
            padding: 30px;
            color: #888;
        }
        
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .order-item {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>
    
    <main>
        <div class="profile-container">
            <div class="profile-header">
                <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['name'] ?? ''); ?>!</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-grid">
                <!-- Update Profile -->
                <div class="profile-card">
                    <h2><i class="fas fa-user-edit"></i> Update Profile</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
                
                <!-- Change Password -->
                <div class="profile-card">
                    <h2><i class="fas fa-key"></i> Change Password</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="orders-list">
                <h2><i class="fas fa-shopping-bag"></i> Recent Orders</h2>
                
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-item">
                            <div>
                                <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                                <div class="order-date"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                            </div>
                            <div>
                                <span class="order-status <?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            <div class="order-total">Ksh <?php echo number_format($order['total'], 0); ?></div>
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="view-btn">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-orders">
                        <i class="fas fa-box-open" style="font-size: 40px; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                        <p>You haven't placed any orders yet.</p>
                        <a href="shop.php" style="color: #05573c; font-weight: 600;">Start Shopping</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include "footer.php"; ?>
</body>
</html>
