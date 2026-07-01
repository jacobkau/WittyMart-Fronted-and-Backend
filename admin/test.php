<?php
// admin/login.php - Admin Login with Bootstrap

require_once 'includes/config.php';
require_once 'includes/auth.php';

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
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #05573c;
            --primary-hover: #03402c;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #05573c 0%, #02c786 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 1100px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            min-height: 580px;
        }
        
        .login-left {
            flex: 1;
            padding: 45px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-right {
            flex: 1;
            background: linear-gradient(135deg, #05573c 0%, #02c786 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .login-right::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: floatBg 15s ease-in-out infinite;
        }
        
        @keyframes floatBg {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(20px, -20px) rotate(3deg); }
        }
        
        .login-right-content {
            position: relative;
            z-index: 1;
            text-align: center;
            color: #fff;
        }
        
        .login-right-content img {
            max-width: 100%;
            max-height: 280px;
            object-fit: contain;
            filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.2));
            animation: floatImage 6s ease-in-out infinite;
        }
        
        @keyframes floatImage {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }
        
        .login-right-content h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .login-right-content p {
            opacity: 0.9;
            font-size: 1rem;
            margin-top: 5px;
        }
        
        .login-right-content .features {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .login-right-content .features .feature-item {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-right-content .features .feature-item i {
            font-size: 16px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            padding: 3px;
        }
        
        .login-logo h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-top: 10px;
            font-weight: 700;
        }
        
        .login-logo p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        .form-label i {
            color: var(--primary-color);
            margin-right: 8px;
        }
        
        .input-group {
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: none;
            color: var(--primary-color);
        }
        
        .input-group .form-control {
            border: none;
            padding: 12px 15px;
            font-size: 0.95rem;
            background: #f8f9fa;
        }
        
        .input-group .form-control:focus {
            box-shadow: none;
            background: #f8f9fa;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .btn-login:hover:not(:disabled) {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5, 87, 60, 0.3);
        }
        
        .btn-login:disabled {
            opacity: 0.8;
            cursor: not-allowed;
        }
        
        .btn-login .spinner-border {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .login-footer .debug-link {
            color: #999;
            font-size: 0.75rem;
            text-decoration: none;
        }
        
        .login-footer .debug-link:hover {
            text-decoration: underline;
        }
        
        /* Alert Styles */
        .alert-custom {
            border-radius: 10px;
            padding: 12px 18px;
            border: none;
            font-size: 0.9rem;
        }
        
        .alert-custom.alert-danger {
            background: #fde8e8;
            color: #c0392b;
            border-left: 4px solid #e74c3c;
        }
        
        .alert-custom i {
            margin-right: 8px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .login-right {
                display: none;
            }
            
            .login-wrapper {
                max-width: 450px;
                min-height: auto;
            }
            
            .login-left {
                padding: 35px 30px;
            }
        }
        
        @media (max-width: 480px) {
            .login-left {
                padding: 25px 20px;
            }
            
            .login-logo img {
                width: 60px;
                height: 60px;
            }
            
            .login-logo h1 {
                font-size: 1.5rem;
            }
            
            .btn-login {
                padding: 12px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side - Login Form -->
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
            
            <form method="POST" action="test.php" id="loginForm">
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
                <?php if (!isset($_GET['debug'])): ?>
                    <br><small><a href="?debug=1" class="debug-link">Enable Debug</a></small>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Side - Image/Content -->
        <div class="login-right">
            <div class="login-right-content">
                <img src="images/shopping-cart.svg" alt="Shopping" 
                     onerror="this.src='https://via.placeholder.com/400x300/05573c/ffffff?text=🛒+WittyMart'">
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
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ===== DISABLE SUBMIT BUTTON ON CLICK =====
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!email || !password) {
                e.preventDefault();
                return;
            }
            
            // Disable button and show loading state
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            document.getElementById('btnContent').style.display = 'none';
            document.getElementById('btnLoading').style.display = 'inline-block';
            
            // Add loading class for styling
            btn.classList.add('loading');
        });
        
        // ===== TOGGLE PASSWORD VISIBILITY =====
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
        
        // ===== AUTO-HIDE ALERTS =====
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
