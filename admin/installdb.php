<?php
// test_db.php - Test database connection

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

require_once '../includes/config.php';

try {
    // Test connection
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Database connection successful!<br>";
    
    // Test tables exist
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = $stmt->fetchAll();
    
    echo "<h2>Tables:</h2>";
    foreach ($tables as $table) {
        echo "- " . $table['table_name'] . "<br>";
    }
    
    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch();
    echo "<br>👤 Users count: " . $count['count'] . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Code: " . $e->getCode() . "<br>";
}

// Check config variables
echo "<h2>Configuration:</h2>";
echo "DB_HOST: " . ($db_config['host'] ?? 'Not set') . "<br>";
echo "DB_NAME: " . ($db_config['dbname'] ?? 'Not set') . "<br>";
echo "DB_USER: " . ($db_config['user'] ?? 'Not set') . "<br>";
echo "DATABASE_URL: " . (getenv('DATABASE_URL') ? 'Set' : 'Not set') . "<br>";
?>
