<?php
// test_login.php - Minimal test version
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log what we received
    error_log('=== TEST LOGIN POST ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
    error_log('CONTENT_TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
        error_log('Empty fields detected - Email: "' . $email . '", Password length: ' . strlen($password));
    } else {
        $success = "Received: Email = $email, Password length = " . strlen($password);
        error_log('SUCCESS: ' . $success);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Login</title>
    <style>
        body { font-family: Arial; max-width: 400px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .debug { background: #f0f0f0; padding: 10px; margin-top: 20px; border-radius: 4px; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Test Login Form</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <form method="POST" action="" id="testForm">
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" id="testEmail" required>
        </div>
        
        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" id="testPassword" required>
        </div>
        
        <button type="submit">Test Login</button>
    </form>
    
    <div class="debug">
        <strong>Debug Info:</strong><br>
        Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?><br>
        PHP Version: <?php echo phpversion(); ?><br>
        Request Method: <?php echo $_SERVER['REQUEST_METHOD'] ?? 'Not set'; ?><br>
        POST data: <?php echo empty($_POST) ? 'Empty' : 'Has data'; ?>
    </div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            console.log('Form submitted');
            console.log('Email:', document.getElementById('testEmail').value);
            console.log('Password:', document.getElementById('testPassword').value);
            // Don't prevent default - let it submit normally
        });
    </script>
</body>
</html>
