<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Adding Status Column to Products Table...</h2>";

    // Add status column to products table
    $table_sqls = [
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';",
    ];

    echo "<h3>Adding Status Column...</h3>";
    foreach ($table_sqls as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✓ Status column added successfully</p>";
            
            // Update existing products to have default status
            $update_sql = "UPDATE products SET status = 'active' WHERE status IS NULL;";
            $pdo->exec($update_sql);
            echo "<p style='color: green;'>✓ Existing products updated with default status</p>";
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'duplicate column') !== false) {
                echo "<p style='color: orange;'>⚠ Status column already exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
            }
        }
    }

    echo "<h3 style='color: green; margin-top: 20px;'>✅ Status column added successfully!</h3>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
