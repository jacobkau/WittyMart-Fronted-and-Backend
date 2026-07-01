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
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Button loading state */
        .btn-login.loading {
            opacity: 0.7;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .btn-login.loading .btn-text {
            display: none;
        }
        
        .btn-login .btn-loading-text {
            display: none;
        }
        
        .btn-login.loading .btn-loading-text {
            display: inline;
        }
        
        .btn-login .btn-spinner {
            display: none;
        }
        
        .btn-login.loading .btn-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Alert styles */
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
        
        /* Overlay for loading */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .loading-text {
            margin-top: 20px;
            font-size: 18px;
            color: #333;
        }
        .loading-dots::after {
            content: '';
            animation: dots 1.5s steps(4, end) infinite;
        }
        @keyframes dots {
            0% { content: ''; }
            25% { content: '.'; }
            50% { content: '..'; }
            75% { content: '...'; }
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
            
            <form method="POST" action="login.php" id="loginForm">
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
                    <br><small><a href="?debug=1" style="color: #999; text-decoration: none;">Enable Debug</a></small>
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <script>
        // ===== DISABLE SUBMIT BUTTON AFTER CLICK =====
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('loginBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
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
                
                // ===== DISABLE THE SUBMIT BUTTON =====
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                
                // Disable input fields
                emailInput.disabled = true;
                passwordInput.disabled = true;
                
                // Show loading overlay
                loadingOverlay.classList.add('active');
                
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
