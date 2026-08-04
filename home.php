<?php
// ===== TURN OFF ERROR DISPLAY FOR PRODUCTION =====
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ===== CLEAN OUTPUT BUFFER =====
ob_clean();

// Include config for database connection and functions
require_once 'includes/config.php';

// Initialize variables
$message = '';
$messageType = '';
$isLoggedIn = isset($_SESSION['user_id']);

// If user is already logged in, redirect to home
if ($isLoggedIn) {
    header('Location: welcome.php');
    exit();
}

// ===== HANDLE AJAX REQUESTS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Set JSON header before any output
    header('Content-Type: application/json');
    
    // Clean output buffer again
    ob_clean();
    
    $action = $_POST['ajax_action'];
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        // Check if PDO is available
        if (!isset($pdo)) {
            throw new Exception('Database connection not available');
        }
        
        switch ($action) {
            case 'register':
                // Get and sanitize input
                $username = sanitize($_POST['username'] ?? '');
                $name = sanitize($_POST['name'] ?? '');
                $phone = sanitize($_POST['phone'] ?? '');
                $email = sanitize($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirmPassword'] ?? '';
                
                // Validate input
                if (empty($username) || empty($name) || empty($phone) || empty($email) || empty($password)) {
                    $response = ['success' => false, 'message' => 'All fields are required'];
                    break;
                }
                
                // Validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $response = ['success' => false, 'message' => 'Invalid email address'];
                    break;
                }
                
                // Check if passwords match
                if ($password !== $confirmPassword) {
                    $response = ['success' => false, 'message' => 'Passwords do not match'];
                    break;
                }
                
                // Check password strength (minimum 6 characters)
                if (strlen($password) < 6) {
                    $response = ['success' => false, 'message' => 'Password must be at least 6 characters'];
                    break;
                }
                
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $response = ['success' => false, 'message' => 'Email already registered. Please login.'];
                    break;
                }
                
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $response = ['success' => false, 'message' => 'Username already taken. Please choose another.'];
                    break;
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user into database
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, name, phone, email, password, role, status) 
                    VALUES (?, ?, ?, ?, ?, 'user', 'active')
                ");
                
                if ($stmt->execute([$username, $name, $phone, $email, $hashedPassword])) {
                    $userId = $pdo->lastInsertId();
                    
                    // Log the user in immediately after registration
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['is_admin'] = false;
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Registration successful! Welcome ' . $name . '!',
                        'redirect' => 'welcome.php'
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Registration failed. Please try again.'];
                }
                break;
                
            case 'login':
                $email = sanitize($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if (empty($email) || empty($password)) {
                    $response = ['success' => false, 'message' => 'Email and password are required'];
                    break;
                }
                
                // Get user from database
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Check if account is active
                    if (isset($user['status']) && $user['status'] === 'inactive') {
                        $response = ['success' => false, 'message' => 'Your account has been deactivated. Please contact support.'];
                        break;
                    }
                    
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_phone'] = $user['phone'] ?? '';
                        $_SESSION['user_role'] = $user['role'] ?? 'user';
                        $_SESSION['is_admin'] = ($user['role'] === 'admin');
                        
                        $response = [
                            'success' => true,
                            'message' => 'Login successful! Welcome back ' . $user['name'] . '!',
                            'redirect' => ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'welcome.php'
                        ];
                    } else {
                        $response = ['success' => false, 'message' => 'Invalid email or password'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Invalid email or password'];
                }
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Invalid action'];
        }
    } catch (PDOException $e) {
        error_log('Auth error: ' . $e->getMessage());
        $response = ['success' => false, 'message' => 'Database error. Please try again.'];
    } catch (Exception $e) {
        error_log('Auth error: ' . $e->getMessage());
        $response = ['success' => false, 'message' => 'Server error. Please try again.'];
    }
    
    // Clean buffer and output JSON
    ob_clean();
    echo json_encode($response);
    exit();
}

// Check if user is logged in (again, for page load)
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Register / Login - WittyMart</title>    
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="homestyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Additional styles for auth page */
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
        }
        
        .auth-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 550px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo-container img {
            max-width: 100px;
            height: auto;
        }
        
        .logo-container h1 {
            color: #05573c;
            font-size: 24px;
            margin: 8px 0 3px;
        }
        
        .logo-container p {
            color: #666;
            font-size: 13px;
            margin: 0;
        }
        
        .auth-container h2 {
            text-align: center;
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .auth-container h2 i {
            color: #05573c;
        }
        
        /* Two column layout for registration */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-row .form-group {
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
            font-size: 13px;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #05573c;
            width: 18px;
            font-size: 13px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: #f9f9f9;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: #05573c;
            background: #fff;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .toggle-password:hover {
            color: #05573c;
            background: #f0f0f0;
        }
        
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
        }
        
        .btn-submit:hover {
            background: #03402c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(5, 87, 60, 0.3);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-submit i {
            margin-right: 8px;
        }
        
        .hidden {
            display: none !important;
        }
        
        .progress-bar {
            width: 100%;
            height: 3px;
            background: #e0e0e0;
            border-radius: 2px;
            margin: 12px 0;
            overflow: hidden;
        }
        
        .progress-bar .progress-fill {
            height: 100%;
            background: #05573c;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .message {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 8px 0;
            display: none;
            font-size: 13px;
        }
        
        .message.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message i {
            margin-right: 8px;
        }
        
        .switch-form {
            text-align: center;
            margin-top: 15px;
        }
        
        .switch-form button {
            background: none;
            border: none;
            color: #05573c;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .switch-form button:hover {
            color: #03402c;
            text-decoration: underline;
        }
        
        .switch-form button i {
            margin-right: 6px;
        }
        
        .theme-toggle-container {
            text-align:right;
            margin-top: 12px;
            margin-bottom:12px;
        }
        
        .theme-toggle-container button {
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            padding: 4px 15px;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
        }
        
        .theme-toggle-container button:hover {
            color: #05573c;
            border-color: #05573c;
            background: #f5f5f5;
        }
        
        .auth-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        
        .auth-modal .modal-content {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        
        .auth-modal .modal-content p:first-child {
            font-size: 20px;
            font-weight: 600;
            color: #05573c;
        }
        
        .auth-modal .modal-content p:first-child i {
            margin-right: 10px;
        }
        
        .auth-modal .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .auth-modal .modal-buttons button {
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .auth-modal .modal-buttons #confirmBtn {
            background: #05573c;
            color: #fff;
        }
        
        .auth-modal .modal-buttons #confirmBtn:hover {
            background: #03402c;
        }
        
        .auth-modal .modal-buttons #cancelBtn {
            background: #e0e0e0;
            color: #555;
        }
        
        .auth-modal .modal-buttons #cancelBtn:hover {
            background: #d0d0d0;
        }
        
        /* Dark Mode Styles */
        body.dark-mode .auth-container {
            background: #1a1a2e;
        }
        
        body.dark-mode .auth-container h2 {
            color: #eee;
        }
        
        body.dark-mode .form-group label {
            color: #bbb;
        }
        
        body.dark-mode .input-wrapper input {
            background: #2a2a3e;
            border-color: #3a3a5e;
            color: #eee;
        }
        
        body.dark-mode .input-wrapper input:focus {
            border-color: #05573c;
            background: #2a2a3e;
        }
        
        body.dark-mode .logo-container p {
            color: #999;
        }
        
        body.dark-mode .toggle-password {
            color: #888;
        }
        
        body.dark-mode .switch-form button {
            color: #4a8a6a;
        }
        
        body.dark-mode .theme-toggle-container button {
            color: #888;
            border-color: #3a3a5e;
        }
        
        body.dark-mode .auth-modal .modal-content {
            background: #1a1a2e;
            color: #eee;
        }
        
        body.dark-mode .auth-modal .modal-buttons #cancelBtn {
            background: #3a3a5e;
            color: #bbb;
        }
        
        body.dark-mode .auth-wrapper {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Full width for login form */
        #loginForm .form-group {
            margin-bottom: 15px;
        }
        
        /* Responsive */
        @media (max-width: 500px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .form-row .form-group {
                margin-bottom: 15px;
            }
            
            .auth-container {
                padding: 25px;
                max-width: 100%;
            }
            
            .logo-container img {
                max-width: 80px;
            }
            
            .logo-container h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        
        <div class="auth-container">
            <div class="theme-toggle-container">
                <button id="themeToggleBtn"><i class="fas fa-moon"></i></button>
            </div>
            <br>
            <!-- Logo -->
            <div class="logo-container">
                <img src="images/logo.png" style="border:1px solid #000" alt="WittyMart Logo">
                <h1>WittyMart</h1>
                <p>Smart Shopping for Witty Minds!</p>
            </div>

            <h2 id="formTitle"><i class="fas fa-user-plus"></i> Create Account</h2>  
            
            <!-- Login Form -->
            <form id="loginForm" class="hidden" method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-wrapper">
                        <input type="email" id="loginEmail" name="email" required placeholder="Enter email">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="loginPassword" name="password" required placeholder="Enter password">
                        <button type="button" class="toggle-password" id="toggleLoginPassword">Show</button>
                    </div>
                </div>
                <button type="submit" class="btn-submit" id="loginBtn"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
            

            <!-- Register Form - Two Columns -->
            <form id="signupForm" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <div class="input-wrapper">
                            <input type="text" id="username" name="username" required placeholder="Enter username" minlength="3" maxlength="50">
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-circle"></i> Full Name</label>
                        <div class="input-wrapper">
                            <input type="text" id="name" name="name" required placeholder="Enter full name" minlength="2" maxlength="100">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <div class="input-wrapper">
                            <input type="tel" id="phone" name="phone" required placeholder="Enter phone number">
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email" required placeholder="Enter email">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" required placeholder="Enter password" minlength="6">
                            <button type="button" class="toggle-password" id="togglePassword">Show</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Confirm password" minlength="6">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="registerBtn"><i class="fas fa-user-plus"></i> Register</button>
            </form>
            
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div id="message" class="message"></div>

            

            <div class="switch-form">
                <button id="switchToLogin"><i class="fas fa-sign-in-alt"></i> Already have an account? Login</button>
                <button id="switchToRegister" class="hidden"><i class="fas fa-user-plus"></i> Don't have an account? Register</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="auth-modal">
        <div class="modal-content">
            <p><i class="fas fa-check-circle"></i> Confirm Registration</p>
            <p>Are you sure you want to create this account?</p>
            <div class="modal-buttons">
                <button id="confirmBtn">Yes, Register</button>
                <button id="cancelBtn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // DOM ELEMENTS
        // ============================================
        const signupForm = document.getElementById('signupForm');
        const loginForm = document.getElementById('loginForm');
        const switchToLogin = document.getElementById('switchToLogin');
        const switchToRegister = document.getElementById('switchToRegister');
        const formTitle = document.getElementById('formTitle');
        const messageDiv = document.getElementById('message');
        const progressFill = document.getElementById('progressFill');
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const confirmModal = document.getElementById('confirmModal');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');

        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            if (password.type === 'password') {
                password.type = 'text';
                this.textContent = 'Hide';
            } else {
                password.type = 'password';
                this.textContent = 'Show';
            }
        });

        document.getElementById('toggleLoginPassword').addEventListener('click', function() {
            const password = document.getElementById('loginPassword');
            if (password.type === 'password') {
                password.type = 'text';
                this.textContent = 'Hide';
            } else {
                password.type = 'password';
                this.textContent = 'Show';
            }
        });

        // ============================================
        // SWITCH BETWEEN LOGIN AND REGISTER
        // ============================================
        switchToLogin.addEventListener('click', function() {
            signupForm.classList.add('hidden');
            loginForm.classList.remove('hidden');
            switchToLogin.classList.add('hidden');
            switchToRegister.classList.remove('hidden');
            formTitle.innerHTML = '<i class="fas fa-sign-in-alt"></i> Welcome Back';
            clearMessage();
        });

        switchToRegister.addEventListener('click', function() {
            loginForm.classList.add('hidden');
            signupForm.classList.remove('hidden');
            switchToRegister.classList.add('hidden');
            switchToLogin.classList.remove('hidden');
            formTitle.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
            clearMessage();
        });

        // ============================================
        // SHOW PROGRESS
        // ============================================
        function showProgress() {
            progressFill.style.width = '50%';
        }

        function hideProgress() {
            progressFill.style.width = '0%';
        }

        // ============================================
        // SHOW MESSAGE
        // ============================================
        function showMessage(text, type) {
            messageDiv.className = 'message ' + type;
            messageDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + text;
            messageDiv.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                    messageDiv.style.opacity = '1';
                }, 500);
            }, 5000);
        }

        function clearMessage() {
            messageDiv.className = 'message';
            messageDiv.style.display = 'none';
            messageDiv.innerHTML = '';
        }

        // ============================================
        // HANDLE REGISTER FORM SUBMISSION
        // ============================================
        signupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate passwords match
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                showMessage('Passwords do not match!', 'error');
                return;
            }
            
            if (password.length < 6) {
                showMessage('Password must be at least 6 characters!', 'error');
                return;
            }
            
            // Show confirmation modal
            confirmModal.style.display = 'flex';
        });

        // ============================================
        // CONFIRM REGISTRATION
        // ============================================
        confirmBtn.addEventListener('click', function() {
            confirmModal.style.display = 'none';
            
            // Get form data
            const formData = new FormData(signupForm);
            formData.append('ajax_action', 'register');
            
            showProgress();
            
            // Send AJAX request
            fetch('home.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideProgress();
                if (data.success) {
                    showMessage(data.message, 'success');
                    // Redirect after short delay
                    setTimeout(() => {
                        window.location.href = data.redirect || 'welcome.php';
                    }, 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                hideProgress();
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
        });

        // ============================================
        // CANCEL REGISTRATION
        // ============================================
        cancelBtn.addEventListener('click', function() {
            confirmModal.style.display = 'none';
        });

        // Close modal on outside click
        confirmModal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });

        // ============================================
        // HANDLE LOGIN FORM SUBMISSION
        // ============================================
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(loginForm);
            formData.append('ajax_action', 'login');
            
            showProgress();
            
            fetch('home.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideProgress();
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || 'welcome.php';
                    }, 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                hideProgress();
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
        });

        // ============================================
        // THEME TOGGLE
        // ============================================
        themeToggleBtn.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            this.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            
            // Save preference
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // Load saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
        }

        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function(e) {
            // Enter key on password field triggers form submission
            if (e.key === 'Enter') {
                const active = document.activeElement;
                if (active && active.type === 'password') {
                    const form = active.closest('form');
                    if (form) {
                        form.dispatchEvent(new Event('submit'));
                    }
                }
            }
            
            // Escape key closes modal
            if (e.key === 'Escape' && confirmModal.style.display === 'flex') {
                confirmModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
