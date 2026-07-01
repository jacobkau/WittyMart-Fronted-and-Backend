<?php
// ===== EXTREME DEBUG MODE =====
// Log everything at the very start
error_log('========================================');
error_log('LOGIN.PHP - Request received at ' . date('Y-m-d H:i:s'));
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('CONTENT_TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
error_log('CONTENT_LENGTH: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'Not set'));

// Log raw input
$raw_input = file_get_contents('php://input');
if ($raw_input) {
    error_log('RAW INPUT: ' . $raw_input);
}

// Log POST data
error_log('POST data: ' . print_r($_POST, true));

// Log GET data
error_log('GET data: ' . print_r($_GET, true));

// Log SERVER data (relevant parts)
error_log('SERVER[REQUEST_URI]: ' . ($_SERVER['REQUEST_URI'] ?? 'Not set'));
error_log('SERVER[HTTP_REFERER]: ' . ($_SERVER['HTTP_REFERER'] ?? 'Not set'));
error_log('SERVER[HTTP_USER_AGENT]: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set'));

// Check if it's an AJAX request
error_log('HTTP_X_REQUESTED_WITH: ' . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'Not set'));

// Start session and output buffering
ob_start();

// Include files
require_once 'includes/config.php';
require_once 'includes/auth.php';


// Debug: Log the request
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST data received: ' . print_r($_POST, true));
}

// Redirect if already logged in
if (isLoggedIn()) {
    error_log('Already logged in, redirecting to dashboard');
    redirect('dashboard.php');
}

$error = '';
$debug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    error_log("Processing login - Email: '$email', Password length: " . strlen($password));
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
        error_log('Empty fields detected');
    } else {
        error_log('Attempting login...');
        if (login($email, $password)) {
            error_log('Login successful!');
            ob_end_clean(); // Clean output buffer before redirect
            redirect('dashboard.php');
        } else {
            $error = 'Invalid email or password';
            error_log('Login failed');
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
        /* Quick styles for testing */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: block !important;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .debug-box {
            background: #f8f9fa;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body class="login-page">
    <!-- Loading Overlay -->
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
            
            <!-- Debug info (remove in production) -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="debug-box">
                    <strong>Debug Info:</strong><br>
                    POST data: <?php echo empty($_POST) ? 'Empty' : 'Received'; ?><br>
                    Email: <?php echo htmlspecialchars($_POST['email'] ?? 'Not set'); ?><br>
                    Password: <?php echo isset($_POST['password']) ? 'Set (length: ' . strlen($_POST['password']) . ')' : 'Not set'; ?><br>
                    Session: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not active'; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm" novalidate>
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
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-spinner"></span>
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Login</span>
                    <span class="btn-loading-text"><i class="fas fa-spinner fa-spin"></i> Logging in...</span>
                </button>
            </form>
            
            <p class="login-footer">
                <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Site</a>
                <?php if (!isset($_GET['debug'])): ?>
                    <br><small><a href="?debug=1" style="color: #999;">Enable Debug</a></small>
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <script>
    // ===== SIMPLIFIED LOGIN HANDLING =====
    (function() {
        'use strict';
        
        console.log('Login page loaded');
        
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const loginBtn = document.getElementById('loginBtn');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        if (!form) {
            console.error('Form not found!');
            return;
        }
        
        console.log('Form found, attaching submit handler');
        
        // Handle form submission
        form.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            
            // Get values
            const email = emailInput.value.trim();
            const password = passwordInput.value.trim();
            
            console.log('Email:', email);
            console.log('Password length:', password.length);
            
            // Validate
            if (!email || !password) {
                e.preventDefault();
                console.log('Validation failed - empty fields');
                
                // Show error
                let alert = document.querySelector('.alert');
                if (!alert) {
                    alert = document.createElement('div');
                    alert.className = 'alert alert-danger';
                    alert.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please fill in all fields';
                    const loginBox = document.querySelector('.login-box');
                    loginBox.insertBefore(alert, form);
                } else {
                    alert.style.display = 'block';
                    alert.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please fill in all fields';
                }
                
                if (!email) emailInput.style.borderColor = '#dc3545';
                if (!password) passwordInput.style.borderColor = '#dc3545';
                
                return false;
            }
            
            // Clear errors
            emailInput.style.borderColor = '';
            passwordInput.style.borderColor = '';
            const alert = document.querySelector('.alert');
            if (alert) alert.style.display = 'none';
            
            // Show loading
            if (loadingOverlay) loadingOverlay.classList.add('active');
            if (loginBtn) loginBtn.classList.add('loading');
            emailInput.disabled = true;
            passwordInput.disabled = true;
            
            console.log('Form validation passed, submitting...');
            // Allow form to submit
            return true;
        });
        
        // Clear errors on input
        emailInput.addEventListener('input', function() {
            this.style.borderColor = '';
            const alert = document.querySelector('.alert');
            if (alert) alert.style.display = 'none';
        });
        
        passwordInput.addEventListener('input', function() {
            this.style.borderColor = '';
            const alert = document.querySelector('.alert');
            if (alert) alert.style.display = 'none';
        });
        
        // Auto-hide errors after 5 seconds
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }
        }, 5000);
        
        console.log('Login handler attached successfully');
    })();
    </script>
</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
