<?php
// config.php - Your database connection file
// Make sure you have this file with your database credentials

// create_tables.php
require_once 'includes/config.php';

try {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Creating Database Tables...</h2>";

    // SQL statements to create tables
    $sqls = [
        // 1. Contact Us table
        "CREATE TABLE IF NOT EXISTS contact_us (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // 2. Newsletter Subscribers table
        "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id SERIAL PRIMARY KEY,
            email VARCHAR(100) NOT NULL UNIQUE,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // 3. Agent Chat Requests table
        "CREATE TABLE IF NOT EXISTS agent_chat_requests (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            message TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    // Execute each SQL statement
    foreach ($sqls as $sql) {
        $result = pg_query($conn, $sql);
        
        if ($result) {
            echo "<p style='color: green;'>✓ Table created successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Error: " . pg_last_error($conn) . "</p>";
        }
    }

    // Verify tables were created
    echo "<h3>Verification:</h3>";
    $tables = ['contact_us', 'newsletter_subscribers', 'agent_chat_requests'];
    
    foreach ($tables as $table) {
        $check = pg_query($conn, "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = '$table')");
        $exists = pg_fetch_result($check, 0, 0);
        
        if ($exists === 't') {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
} finally {
    // Close connection
    if (isset($conn) && $conn) {
        pg_close($conn);
    }
}
?>
