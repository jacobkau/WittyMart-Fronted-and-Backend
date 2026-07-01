<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

// Debug: Check if admin user exists (remove after first login)
if (isset($_GET['debug'])) {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id, email, name, role FROM users WHERE email = 'admin@wittymart.com'");
        $user = $stmt->fetch();
        echo "<pre>Admin user exists: " . ($user ? 'YES' : 'NO') . "\n";
        if ($user) {
            print_r($user);
        }
        echo "</pre>";
    } catch (Exception $e) {
        echo "Debug error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Debug POST data (remove after fixing)
    if (isset($_GET['debug'])) {
        echo "<pre>POST Email: " . htmlspecialchars($email) . "\n";
        echo "POST Password length: " . strlen($password) . "\n";
        echo "Password empty? " . (empty($password) ? 'YES' : 'NO') . "\n";
        echo "</pre>";
    }
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (login($email, $password)) {
        redirect('dashboard.php');
    } else {
        $error = 'Invalid email or password';
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
    
</head>
<body class="login-page">
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Logging in<span class="loading-dots"></span></div>
    </div>
    
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
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="email" placeholder="Enter your email" required autofocus value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-spinner"></span>
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Login</span>
                    <span class="btn-loading-text"><i class="fas fa-spinner fa-spin"></i> Logging in...</span>
                </button>
            </form>
            
            <p class="login-footer">
                <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Site</a>
            </p>
        </div>
    </div>

</body>
</html>
