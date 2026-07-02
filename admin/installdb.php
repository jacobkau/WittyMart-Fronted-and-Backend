<?php


ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php'; 

try {
    $sql = "
        ALTER TABLE products
        ADD COLUMN IF NOT EXISTS supplier VARCHAR(255),
        ADD COLUMN IF NOT EXISTS sku VARCHAR(100);
    ";

    $pdo->exec($sql);

    echo "<h3 style='color:green;'>✅ Products table updated successfully.</h3>";
    echo "<p>The <strong>supplier</strong> and <strong>sku</strong> columns now exist.</p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Database Error</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
