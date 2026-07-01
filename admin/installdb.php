<?php
// admin/test.php - Test PHP

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP is working!</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test includes
echo "<h2>Testing includes...</h2>";

if (file_exists('../includes/config.php')) {
    echo "✅ config.php found<br>";
    require_once '../includes/config.php';
    echo "✅ config.php loaded<br>";
} else {
    echo "❌ config.php NOT found<br>";
}

if (file_exists('../includes/auth.php')) {
    echo "✅ auth.php found<br>";
    require_once '../includes/auth.php';
    echo "✅ auth.php loaded<br>";
} else {
    echo "❌ auth.php NOT found<br>";
}

// Test database
echo "<h2>Testing database...</h2>";
try {
    $db = getDB();
    $stmt = $db->query("SELECT 1");
    echo "✅ Database connected successfully!<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

phpinfo();
?>
