<?php
// update_products_simple.php - Simple Update Script

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

global $pdo;

echo "<h1>Updating Products Table...</h1><pre>";

try {
    // Check if supplier column exists
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'products' AND column_name = 'supplier'
    ");
    
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN supplier VARCHAR(100)");
        echo "✅ supplier column added\n";
    } else {
        echo "⚠️ supplier column already exists\n";
    }
    
    // Check if SKU column exists
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'products' AND column_name = 'sku'
    ");
    
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(50)");
        echo "✅ sku column added\n";
    } else {
        echo "⚠️ sku column already exists\n";
    }
    
    // Create uploads directory
    $upload_dir = __DIR__ . '/uploads/products/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        echo "✅ Uploads directory created\n";
    } else {
        echo "⚠️ Uploads directory already exists\n";
    }
    
    echo "\n🎉 Update complete!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<a href='admin/dashboard.php'>Go to Dashboard</a>";
?>
