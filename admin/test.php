<?php
// admin/health.php - Health check
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Health Check</h1>";

// 1. Check PHP version
echo "<h3>PHP Version:</h3>";
echo phpversion() . "<br>";

// 2. Check config file
echo "<h3>Config File:</h3>";
if (file_exists('../includes/config.php')) {
    echo "✅ config.php found<br>";
} else {
    echo "❌ config.php NOT found<br>";
}

// 3. Check database connection
echo "<h3>Database Connection:</h3>";
require_once '../includes/config.php';

if (isset($pdo)) {
    echo "✅ PDO exists<br>";
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "✅ Database query successful<br>";
    } catch (PDOException $e) {
        echo "❌ Database query failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ PDO not defined<br>";
}

// 4. Check session
echo "<h3>Session:</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session active<br>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "❌ Session not active<br>";
    // Try to start session
    session_start();
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "✅ Session started successfully<br>";
    }
}

// 5. Check upload directory
echo "<h3>Upload Directory:</h3>";
$upload_dir = '../uploads/products/';
if (file_exists($upload_dir)) {
    echo "✅ Upload directory exists<br>";
    echo "Path: " . realpath($upload_dir) . "<br>";
    echo "Writable: " . (is_writable($upload_dir) ? '✅ Yes' : '❌ No') . "<br>";
} else {
    echo "❌ Upload directory NOT found<br>";
}

// 6. Check if user is logged in
echo "<h3>Login Status:</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User is logged in (ID: " . $_SESSION['user_id'] . ")<br>";
    echo "Name: " . ($_SESSION['user_name'] ?? 'N/A') . "<br>";
    echo "Role: " . ($_SESSION['user_role'] ?? 'N/A') . "<br>";
} else {
    echo "❌ No user logged in<br>";
}
?>
