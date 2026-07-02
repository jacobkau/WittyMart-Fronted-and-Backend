<?php
// update_products_table.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php'; // Adjust path if necessary

try {
    $sql = "
        ALTER TABLE products
        ADD COLUMN IF NOT EXISTS supplier VARCHAR(255),
        ADD COLUMN IF NOT EXISTS sku VARCHAR(100),
        ADD CONSTRAINT products_sku_unique UNIQUE (sku);
    ";

    $pdo->exec($sql);

    echo "<h3 style='color:green;'>✅ Products table updated successfully.</h3>";
    echo "<p>The <strong>supplier</strong> and <strong>sku</strong> columns now exist.</p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Database Error</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
