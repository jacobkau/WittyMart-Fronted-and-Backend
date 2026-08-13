<?php
require_once 'includes/config.php';


try {
    // Drop existing tables if they exist
    $pdo->exec("DROP TABLE IF EXISTS order_items CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS orders CASCADE");
    
    // Create orders table
    $pdo->exec("
        CREATE TABLE orders (
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
        )
    ");
    
    // Create order_items table
    $pdo->exec("
        CREATE TABLE order_items (
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
        )
    ");
    
    // Create indexes
    $pdo->exec("CREATE INDEX idx_orders_user_id ON orders(user_id)");
    $pdo->exec("CREATE INDEX idx_orders_order_number ON orders(order_number)");
    $pdo->exec("CREATE INDEX idx_orders_status ON orders(status)");
    $pdo->exec("CREATE INDEX idx_orders_created_at ON orders(created_at)");
    $pdo->exec("CREATE INDEX idx_order_items_order_id ON order_items(order_id)");
    $pdo->exec("CREATE INDEX idx_order_items_product_id ON order_items(product_id)");
    
    // Create updated_at trigger function
    $pdo->exec("
        CREATE OR REPLACE FUNCTION update_updated_at_column()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ language 'plpgsql'
    ");
    
    // Create trigger
    $pdo->exec("
        DROP TRIGGER IF EXISTS update_orders_updated_at ON orders;
        CREATE TRIGGER update_orders_updated_at
            BEFORE UPDATE ON orders
            FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column()
    ");
    
    echo "<h1>✅ Orders tables recreated successfully!</h1>";
    echo "<p>The 'orders' and 'order_items' tables have been recreated with all required columns.</p>";
    echo "<a href='checkout.php'>Go to Checkout</a>";
    
} catch (PDOException $e) {
    echo "<h1>❌ Error recreating tables</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log('Setup orders table error: ' . $e->getMessage());
}
?>
