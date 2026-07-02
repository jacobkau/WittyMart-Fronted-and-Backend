<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Creating Database Tables...</h2>";

    // SQL statements to create tables
    $sqls = [
        "CREATE TABLE IF NOT EXISTS contact_us (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id SERIAL PRIMARY KEY,
            email VARCHAR(100) NOT NULL UNIQUE,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS agent_chat_requests (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            message TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    // Execute using PDO
    foreach ($sqls as $sql) {
        try {
            $result = $pdo->exec($sql);
            echo "<p style='color: green;'>✓ Table created successfully</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Table already exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
            }
        }
    }

    // Verify tables
    echo "<h3>Verification:</h3>";
    $tables = ['contact_us', 'newsletter_subscribers', 'agent_chat_requests'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = '$table')");
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                // Get row count
                $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $countStmt->fetchColumn();
                echo "<p style='color: green;'>✓ Table '$table' exists ($count records)</p>";
            } else {
                echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Error checking '$table': " . $e->getMessage() . "</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
