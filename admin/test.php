<?php
// admin/test_login.php - Debug Login Page
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Login Test</h1>";

// Test 1: Check if config.php loads
echo "<h3>1. Loading config.php...</h3>";
require_once __DIR__ . 'includes/config.php';
echo "✅ config.php loaded<br>";

// Test 2: Check if auth.php loads
echo "<h3>2. Loading auth.php...</h3>";
require_once __DIR__ . 'includes/auth.php';
echo "✅ auth.php loaded<br>";

// Test 3: Check session
echo "<h3>3. Session Status:</h3>";
echo "Session status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";

// Test 4: Check if user is logged in
echo "<h3>4. Login Status:</h3>";
if (isLoggedIn()) {
    echo "✅ User is logged in<br>";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'N/A') . "<br>";
    echo "User Name: " . ($_SESSION['user_name'] ?? 'N/A') . "<br>";
} else {
    echo "❌ Not logged in<br>";
}

// Test 5: Simple form test
echo "<h3>5. Form Test:</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "Form submitted!<br>";
    echo "Email: " . htmlspecialchars($_POST['email'] ?? '') . "<br>";
    echo "Password: " . (isset($_POST['password']) ? '******' : 'Not set') . "<br>";
    
    // Test login function
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        echo "Calling login() function...<br>";
        $result = login($email, $password);
        echo "Login result: " . ($result ? '✅ Success' : '❌ Failed') . "<br>";
    }
}
?>
<form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Test Login</button>
</form>
