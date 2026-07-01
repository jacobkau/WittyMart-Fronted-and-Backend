<?php
// install.php - Database Installation Script

require_once 'includes/config.php';

echo "<h1>WittyMart Database Installation</h1>";
echo "<pre>";

try {
    $db = getDB();
    
    // Read SQL file
    $sql = file_get_contents('schema.sql');
    
    // Execute SQL
    $db->exec($sql);
    
    echo "✅ Database tables created successfully!<br>";
    echo "✅ Admin user created (admin@wittymart.com / admin123)<br>";
    echo "✅ Sample data inserted<br>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "</pre>";
echo "<a href='login.php'>Go to Admin Login</a>";
?>
