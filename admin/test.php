<?php
// admin/debug.php - Show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Mode</h1>";

// Check if config.php exists and can be loaded
echo "<h3>Loading config.php...</h3>";
if (file_exists(__DIR__ . '/../includes/config.php')) {
    echo "✅ config.php found at: " . __DIR__ . '/../includes/config.php' . "<br>";
    require_once __DIR__ . '/../includes/config.php';
    echo "✅ config.php loaded successfully<br>";
} else {
    die("❌ config.php NOT found at: " . __DIR__ . '/../includes/config.php');
}

// Check if PDO exists
echo "<h3>Checking PDO...</h3>";
if (isset($pdo)) {
    echo "✅ PDO exists<br>";
} else {
    die("❌ PDO not defined");
}

// Check session
echo "<h3>Checking Session...</h3>";
echo "Session status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";

// Check if user is logged in
echo "<h3>Login Status:</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User is logged in (ID: " . $_SESSION['user_id'] . ")<br>";
    echo "Name: " . ($_SESSION['user_name'] ?? 'N/A') . "<br>";
} else {
    echo "❌ No user logged in<br>";
}

echo "<h3>All good! Your admin panel should work.</h3>";
?>
