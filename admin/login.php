<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$debug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all POST data
    error_log('POST data: ' . print_r($_POST, true));
    
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Debug
    $debug .= "Email: '$email', Password length: " . strlen($password) . "\n";
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
        $debug .= "Empty fields detected\n";
    } else {
        $debug .= "Attempting login...\n";
        if (login($email, $password)) {
            $debug .= "Login successful, redirecting...\n";
            redirect('dashboard.php');
        } else {
            $error = 'Invalid email or password';
            $debug .= "Login failed\n";
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
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Debug info (remove in production) -->
            <?php if ($debug && isset($_GET['debug'])): ?>
                <div class="alert alert-info" style="background: #e3f2fd; color: #0d47a1; padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 12px; white-space: pre-wrap;">
                    <strong>Debug Info:</strong>
                    <?php echo htmlspecialchars($debug); ?>
                </div>
            <?php endif; ?>
            
           <form method="POST" action="login.php" id="loginForm" novalidate>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="email" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required autofocus>
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
                <?php if (isset($_GET['debug'])): ?>
                    <br><small style="color: #999;">Debug mode enabled</small>
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <script>
        // ===== LOGIN FORM HANDLING =====
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const loginBtn = document.getElementById('loginBtn');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            // Auto-hide errors after 5 seconds
            const alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            // Handle form submission
            form.addEventListener('submit', function(e) {
                // Get values and trim
                const email = emailInput.value.trim();
                const password = passwordInput.value.trim();
                
                // Validate
                if (!email || !password) {
                    e.preventDefault();
                    
                    // Show error message
                    let errorAlert = document.querySelector('.alert');
                    if (!errorAlert) {
                        errorAlert = document.createElement('div');
                        errorAlert.className = 'alert alert-danger';
                        errorAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please fill in all fields';
                        const loginBox = document.querySelector('.login-box');
                        const formElement = document.getElementById('loginForm');
                        loginBox.insertBefore(errorAlert, formElement);
                    } else {
                        errorAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please fill in all fields';
                        errorAlert.style.display = 'block';
                        errorAlert.style.opacity = '1';
                    }
                    
                    // Highlight empty fields
                    if (!email) {
                        emailInput.style.borderColor = '#dc3545';
                    }
                    if (!password) {
                        passwordInput.style.borderColor = '#dc3545';
                    }
                    
                    return false;
                }
                
                // Reset border colors
                emailInput.style.borderColor = '';
                passwordInput.style.borderColor = '';
                
                // Show loading state
                loadingOverlay.classList.add('active');
                loginBtn.classList.add('loading');
                emailInput.disabled = true;
                passwordInput.disabled = true;
                
                // Allow form to submit
                return true;
            });
            
            // Clear error styling on input
            emailInput.addEventListener('input', function() {
                this.style.borderColor = '';
                const alert = document.querySelector('.alert');
                if (alert) {
                    alert.style.display = 'none';
                }
            });
            
            passwordInput.addEventListener('input', function() {
                this.style.borderColor = '';
                const alert = document.querySelector('.alert');
                if (alert) {
                    alert.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
