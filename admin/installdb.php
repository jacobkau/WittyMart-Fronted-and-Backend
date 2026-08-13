<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Updating Database Tables...</h2>";

    // ===== 1. ADD STATUS COLUMN TO PRODUCTS TABLE =====
    echo "<h3>Adding Order_Items table to Products Table...</h3>";
    
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(10,2) DEFAULT 0;");


            

        echo "<p style='color: green;'>✓ COLUMN added successfully</p>";
        echo "<p style='color: green;'>✓ UPDATED</p>";
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || 
            strpos($e->getMessage(), 'duplicate table') !== false) {
            echo "<p style='color: orange;'>⚠Tables already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding tables: " . $e->getMessage() . "</p>";
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
