<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Updating Database Tables...</h2>";

    // ===== 1. ADD STATUS COLUMN TO PRODUCTS TABLE =====
    echo "<h3>Adding Status Column to Products Table...</h3>";
    
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN image_public_id VARCHAR(255);");

        echo "<p style='color: green;'>✓ Status column alted successfully</p>";
        
        // Update existing products to have default status
        $pdo->exec("ALTER TABLE products ADD COLUMN image_url TEXT;");
        echo "<p style='color: green;'>✓ UPDATED</p>";
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || 
            strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "<p style='color: orange;'>⚠ Status column already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding status column: " . $e->getMessage() . "</p>";
        }
    }

  
    // ===== SUMMARY =====
    echo "<h3 style='color: green; margin-top: 20px;'>✅ Database update completed successfully!</h3>";
    echo "<ul style='margin-top: 10px;'>";
    echo "<li>✓ DATABASE ALTED</li>";
     echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
