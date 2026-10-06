<?php
// ============================================
// ADMIN LOGOUT
// ============================================
// config.php already starts the session AND
// defines logActivity(), so we don't redeclare them here.
require_once 'includes/config.php';

// ===== LOG LOGOUT =====
if (isset($_SESSION['user_id'])) {
    logActivity(
        'logout',
        'User logged out',
        $_SESSION['user_id'],
        $_SESSION['user_name'] ?? null
    );
}

// ===== DESTROY SESSION =====
$_SESSION = [];

// Destroy the session cookie too (clean logout)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging Out...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #f5f5f5;
            margin: 0;
        }
        .logout-box {
            text-align: center;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .logout-box h1 { color: #05573c; }
        .logout-box .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #05573c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .logout-box p { color: #666; }
        .logout-box a { color: #05573c; text-decoration: none; }
        .logout-box a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="logout-box">
        <h1>👋 Goodbye!</h1>
        <div class="spinner"></div>
        <p>You have been logged out successfully.</p>
        <p><a href="login.php">Login Again</a> | <a href="index.php">Return to Homepage</a></p>
    </div>
    <script>
        // Auto redirect after 3 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
    </script>
</body>
</html>
