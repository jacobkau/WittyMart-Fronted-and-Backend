<?php
// admin/login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Store email for logging
        $login_email = $email;
        
        if (login($email, $password)) {
            // login() function already logs the activity internally
            // No need to call logActivity again here
            redirect('dashboard.php');
        } else {
            // Log failed login attempt (login() already does this internally)
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
    <link rel="stylesheet" href="login.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper">
      
<div class="login-right">
    <div class="login-right-content">
        <div class="graphic-icon">
            <i class="fas fa-store-alt"></i>
        </div>
        <h2>Welcome Back!</h2>
        <p>Manage your store with ease</p>
        <div class="features">
            <span class="feature-item"><i class="fas fa-box"></i> Products</span>
            <span class="feature-item"><i class="fas fa-shopping-cart"></i> Orders</span>
            <span class="feature-item"><i class="fas fa-users"></i> Customers</span>
        </div>
        <div style="margin-top: 20px; font-size: 0.85rem; opacity: 0.7;">
            <i class="fas fa-shield-alt"></i> Secure Admin Access
        </div>
    </div>
</div>
        
        
        <div class="login-left">
            <div class="login-logo">
                <img src="images/logo.png" alt="WittyMart">
                <h1>WittyMart</h1>
                <p>Admin Panel</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-custom alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control" 
                               placeholder="Enter your email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Enter your password" required>
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword" style="border: none; background: #f8f9fa;">
                            <i class="fas fa-eye" id="passwordIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span id="btnContent">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </span>
                    <span id="btnLoading" style="display: none;">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        Logging in...
                    </span>
                </button>
            </form>
            
            <div class="login-footer">
                <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Site</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!email || !password) {
                e.preventDefault();
                return;
            }
            
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            document.getElementById('btnContent').style.display = 'none';
            document.getElementById('btnLoading').style.display = 'inline-block';
            btn.classList.add('loading');
        });
        
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert-custom');
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
