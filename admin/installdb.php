<?php

require_once 'includes/config.php';

echo "<h2>Complete Database Setup</h2>";

// Start transaction
pg_query($conn, "BEGIN");

try {
    // Create tables
    $tables = [
        "contact_us" => "CREATE TABLE IF NOT EXISTS contact_us (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "newsletter_subscribers" => "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id SERIAL PRIMARY KEY,
            email VARCHAR(100) NOT NULL UNIQUE,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "agent_chat_requests" => "CREATE TABLE IF NOT EXISTS agent_chat_requests (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            message TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($tables as $name => $sql) {
        pg_query($conn, $sql);
        echo "✅ Table '$name' ready<br>";
    }

    // Insert sample data (optional)
    echo "<br><h3>Inserting Sample Data...</h3>";
    
    // Sample contact us messages
    $sampleMessages = [
        "John Doe" => "johndoe@email.com",
        "Jane Smith" => "janesmith@email.com",
        "Bob Johnson" => "bobj@email.com"
    ];
    
    foreach ($sampleMessages as $name => $email) {
        $sql = "INSERT INTO contact_us (name, email, message, status) 
                VALUES ('$name', '$email', 'Need help with my order #12345', 'unread')";
        pg_query($conn, $sql);
    }
    echo "✅ Sample contact messages inserted<br>";
    
    // Sample newsletter subscribers
    $emails = ['sub1@test.com', 'sub2@test.com', 'sub3@test.com'];
    foreach ($emails as $email) {
        $sql = "INSERT INTO newsletter_subscribers (email, status) 
                VALUES ('$email', 'pending') 
                ON CONFLICT (email) DO NOTHING";
        pg_query($conn, $sql);
    }
    echo "✅ Sample newsletter subscribers inserted<br>";
    
    // Sample agent chat requests
    $sql = "INSERT INTO agent_chat_requests (user_id, message, status) 
            VALUES 
            (1, 'Need help with product return', 'pending'),
            (2, 'Payment issue', 'pending')";
    pg_query($conn, $sql);
    echo "✅ Sample agent chat requests inserted<br>";

    // Commit transaction
    pg_query($conn, "COMMIT");
    
    echo "<br><h3 style='color: green;'>✅ Installation Complete!</h3>";
    
    // Show summary
    echo "<hr>";
    echo "<h4>Summary:</h4>";
    echo "<ul>";
    $tables = ['contact_us', 'newsletter_subscribers', 'agent_chat_requests'];
    foreach ($tables as $table) {
        $result = pg_query($conn, "SELECT COUNT(*) FROM $table");
        $count = pg_fetch_result($result, 0, 0);
        echo "<li>$table: $count records</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
} finally {
    pg_close($conn);
}
?>
