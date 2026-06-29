<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Register / Login - WittyMart</title>    
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="homestyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <!-- Logo -->
            <div class="logo-container">
                <img src="images/Witty Mart.png" alt="WittyMart Logo">
                <h1>WittyMart</h1>
                <p>Smart Shopping for Witty Minds!</p>
            </div>

            <h2 id="formTitle"><i class="fas fa-user-plus"></i> Create Account</h2>

            <!-- Register Form -->
            <form id="signupForm">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" required placeholder="Enter username">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Full Name</label>
                    <div class="input-wrapper">
                        <input type="text" id="name" required placeholder="Enter full name">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <div class="input-wrapper">
                        <input type="tel" id="phone" required placeholder="Enter phone number">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" required placeholder="Enter email">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" required placeholder="Enter password">
                        <button type="button" class="toggle-password" id="togglePassword">Show</button>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirmPassword" required placeholder="Confirm password">
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-user-plus"></i> Register</button>
            </form>

            <!-- Login Form -->
            <form id="loginForm" class="hidden">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-wrapper">
                        <input type="email" id="loginEmail" required placeholder="Enter email">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="loginPassword" required placeholder="Enter password">
                        <button type="button" class="toggle-password" id="toggleLoginPassword">Show</button>
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>

            <div class="progress-bar" id="progressBar"></div>
            <div id="message" class="message"></div>

            <div class="theme-toggle-container">
                <button id="themeToggleBtn"><i class="fas fa-moon"></i> Dark Mode</button>
            </div>

            <div class="switch-form">
                <button id="switchToLogin"><i class="fas fa-sign-in-alt"></i> Already have an account? Login</button>
                <button id="switchToRegister" class="hidden"><i class="fas fa-user-plus"></i> Don't have an account? Register</button>
            </div>
        </div>
    </div>

    <!-- Modal -->
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
    <script src="homescript.js"></script>
</body>
</html>
