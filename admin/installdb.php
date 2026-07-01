<?php
require_once 'includes/config.php';

echo "<h1>Database Test</h1>";

try {
    // Test connection
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    echo "<p>✅ Connected to PostgreSQL: $version</p>";
    
    // Check if users table exists
    $stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'users')");
    $tableExists = $stmt->fetchColumn();
    
    if ($tableExists) {
        echo "<p>✅ Users table exists</p>";
        
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $count = $stmt->fetchColumn();
        echo "<p>Total users: $count</p>";
        
        // List users
        $stmt = $pdo->query("SELECT id, name, email, role FROM users");
        $users = $stmt->fetchAll();
        
        echo "<h2>Users:</h2>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li>ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}, Role: {$user['role']}</li>";
        }
        echo "</ul>";
        
        // Test password verification
        $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->execute(['admin@wittymart.com']);
        $hash = $stmt->fetchColumn();
        
        if ($hash) {
            echo "<p>Admin password hash: " . substr($hash, 0, 30) . "...</p>";
            echo "<p>Password 'admin123' verification: " . (password_verify('admin123', $hash) ? '✅ Valid' : '❌ Invalid') . "</p>";
        } else {
            echo "<p>❌ Admin user not found!</p>";
        }
    } else {
        echo "<p>❌ Users table does not exist!</p>";
        echo "<p>Please run the schema.sql file to create tables.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
