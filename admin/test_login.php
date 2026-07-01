<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all POST data
    error_log('POST data: ' . print_r($_POST, true));
    
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        if (login($email, $password)) {
            redirect('dashboard.php');
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - WittyMart</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Quick styles for the alert */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid transparent;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-danger i {
            margin-right: 8px;
        }
        .login-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .login-logo h1 {
            margin: 10px 0 5px;
            color: #333;
        }
        .login-logo p {
            color: #666;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group label i {
            margin-right: 8px;
            color: #666;
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #0056b3;
        }
        .btn-login i {
            margin-right: 8px;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .login-footer a {
            color: #007bff;
            text-decoration: none;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .loading-overlay {
            display: none;
        }
        /* Remove loading spinner styles */
        .btn-spinner, .btn-loading-text {
            display: none;
        }
        .btn-text {
            display: inline;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <img src="images/logo.png" alt="WittyMart">
                <h1>WittyMart</h1>
                <p>Admin Panel</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Debug info (remove in production) -->
            <?php if (isset($_GET['debug'])): ?>
                <div style="background: #e3f2fd; color: #0d47a1; padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 12px;">
                    <strong>Debug Info:</strong><br>
                    POST data: <?php echo empty($_POST) ? 'Empty' : 'Received'; ?><br>
                    Email: <?php echo htmlspecialchars($_POST['email'] ?? 'Not set'); ?><br>
                    Password: <?php echo isset($_POST['password']) ? 'Set (length: ' . strlen($_POST['password']) . ')' : 'Not set'; ?><br>
                    Session: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not active'; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="email" placeholder="Enter your email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required autofocus>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <p class="login-footer">
                <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Site</a>
                <?php if (!isset($_GET['debug'])): ?>
                    <br><small><a href="?debug=1" style="color: #999; text-decoration: none;">Enable Debug</a></small>
                <?php endif; ?>
            </p>
        </div>
    </div>
</body>
</html>
