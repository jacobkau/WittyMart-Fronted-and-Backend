<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Creating Database Tables...</h2>";

    // First, create all tables
    $table_sqls = [
        // Users table modifications
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) NULL;",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL;",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;",
        
        // Cart table (user-based)
        "CREATE TABLE IF NOT EXISTS cart (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, product_id)
        );",
        
        // Cart items table (session-based for guests)
        "CREATE TABLE IF NOT EXISTS cart_items (
            id SERIAL PRIMARY KEY,
            session_id VARCHAR(255) NULL,
            user_id INTEGER NULL REFERENCES users(id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",
        
        // Orders table
        "CREATE TABLE IF NOT EXISTS orders (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            total_amount DECIMAL(10, 2) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'shipped', 'delivered', 'cancelled')),
            payment_status VARCHAR(50) DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid', 'failed', 'refunded')),
            payment_method VARCHAR(50) NULL,
            shipping_address TEXT NOT NULL,
            shipping_city VARCHAR(100) NOT NULL,
            shipping_country VARCHAR(100) NOT NULL,
            shipping_postal_code VARCHAR(20),
            tracking_number VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",
        
        // Order items
        "CREATE TABLE IF NOT EXISTS order_items (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            product_name VARCHAR(255) NOT NULL,
            quantity INTEGER NOT NULL CHECK (quantity > 0),
            price DECIMAL(10, 2) NOT NULL,
            total DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",
        
        // Wishlist
        "CREATE TABLE IF NOT EXISTS wishlist (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, product_id)
        );"
    ];

    echo "<h3>Creating Tables...</h3>";
    foreach ($table_sqls as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✓ Table created/updated successfully</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Table already exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Error creating table: " . $e->getMessage() . "</p>";
            }
        }
    }

    // Now create indexes (tables now exist)
    echo "<h3>Creating Indexes...</h3>";
    $index_sqls = [
        "CREATE INDEX IF NOT EXISTS idx_cart_user_id ON cart(user_id);",
        "CREATE INDEX IF NOT EXISTS idx_cart_product_id ON cart(product_id);",
        "CREATE INDEX IF NOT EXISTS idx_cart_items_user_id ON cart_items(user_id);",
        "CREATE INDEX IF NOT EXISTS idx_cart_items_session_id ON cart_items(session_id);",
        "CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id);",
        "CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);",
        "CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);",
        "CREATE INDEX IF NOT EXISTS idx_wishlist_user_id ON wishlist(user_id);"
    ];

    foreach ($index_sqls as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✓ Index created successfully</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Index already exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Error creating index: " . $e->getMessage() . "</p>";
            }
        }
    }

    // Create triggers
    echo "<h3>Creating Triggers...</h3>";
    $trigger_sqls = [
        "CREATE OR REPLACE FUNCTION update_updated_at_column()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ language 'plpgsql';",
        
        "DROP TRIGGER IF EXISTS update_cart_updated_at ON cart;",
        "CREATE TRIGGER update_cart_updated_at BEFORE UPDATE ON cart
        FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();",
        
        "DROP TRIGGER IF EXISTS update_cart_items_updated_at ON cart_items;",
        "CREATE TRIGGER update_cart_items_updated_at BEFORE UPDATE ON cart_items
        FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();",
        
        "DROP TRIGGER IF EXISTS update_orders_updated_at ON orders;",
        "CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders
        FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();"
    ];

    foreach ($trigger_sqls as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✓ Trigger created successfully</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Error creating trigger: " . $e->getMessage() . "</p>";
        }
    }

    echo "<h3 style='color: green; margin-top: 20px;'>✅ Database setup complete!</h3>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
