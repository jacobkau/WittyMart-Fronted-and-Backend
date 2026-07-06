<?php
// admin/test.php - Test for errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "PHP is working!<br>";

// Test database connection
require_once '../includes/config.php';

if (isset($pdo)) {
    echo "Database connection successful!<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT 1");
    echo "Query successful!<br>";
} else {
    echo "Database connection failed!<br>";
}
?>
