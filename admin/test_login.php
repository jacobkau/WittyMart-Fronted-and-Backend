<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('=== NOSCRIPT TEST POST ===');
    error_log('Email: ' . ($_POST['email'] ?? 'Not set'));
    error_log('Password: ' . (isset($_POST['password']) ? 'Set (length: ' . strlen($_POST['password']) . ')' : 'Not set'));
    
    echo "<h2>Form Submitted!</h2>";
    echo "<pre>";
    echo "Email: " . htmlspecialchars($_POST['email'] ?? 'Not set') . "\n";
    echo "Password length: " . strlen($_POST['password'] ?? '') . "\n";
    echo "POST data:\n";
    print_r($_POST);
    echo "</pre>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>No JavaScript Test</title>
</head>
<body>
    <h1>Test Form (No JavaScript)</h1>
    <form method="POST" action="">
        <div>
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        <div>
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Submit</button>
    </form>
</body>
</html>
