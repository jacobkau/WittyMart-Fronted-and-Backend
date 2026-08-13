<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Updating Database Tables...</h2>";

    // ===== 1. ADD STATUS COLUMN TO PRODUCTS TABLE =====
    echo "<h3>Adding Order_Items table to Products Table...</h3>";
    
    try {
        $pdo->exec("
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    shipping_fee DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending',
    payment_method VARCHAR(50),
    shipping_address TEXT,
    shipping_city VARCHAR(100),
    delivery_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
); ");
     $pdo->exec("
     CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INTEGER NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);");
        
            $pdo->exec("
            CREATE INDEX idx_orders_user_id ON orders(user_id);");
$pdo->exec("CREATE INDEX idx_orders_order_number ON orders(order_number);");
$pdo->exec("CREATE INDEX idx_orders_status ON orders(status);");
$pdo->exec("CREATE INDEX idx_order_items_order_id ON order_items(order_id);");


            

        echo "<p style='color: green;'>✓ tables added successfully</p>";
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
