<?php
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup - WittyMart</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f6fa;
            color: #333;
        }
        .container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        h1 { color: #05573c; margin-top: 0; }
        h2 { margin-top: 30px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .warn { color: #fd7e14; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
        .btn {
            display: inline-block;
            padding: 10px 25px;
            background: #05573c;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        .btn:hover { background: #03402c; }
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        .btn-secondary:hover { background: #5a6268; }
        .log {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
            margin: 15px 0;
            border: 1px solid #e9ecef;
            max-height: 500px;
            overflow-y: auto;
        }
        .log .success { color: #28a745; }
        .log .error { color: #dc3545; }
        .log .info { color: #17a2b8; }
        .log .warn { color: #fd7e14; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📦 Database Setup</h1>
        <p>Creating <strong>wishlist</strong>, <strong>reviews</strong>, and <strong>suppliers</strong> tables...</p>
        <div class='log'>";

try {
    $messages = [];

    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    if (!function_exists('witty_table_exists')) {
        function witty_table_exists($pdo, $table) {
            $stmt = $pdo->prepare("
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = 'public' AND table_name = ?
            ");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }
    }
    if (!function_exists('witty_column_exists')) {
        function witty_column_exists($pdo, $table, $column) {
            $stmt = $pdo->prepare("
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = ? AND column_name = ?
            ");
            $stmt->execute([$table, $column]);
            return (bool) $stmt->fetchColumn();
        }
    }
    if (!function_exists('witty_index_exists')) {
        function witty_index_exists($pdo, $name) {
            $stmt = $pdo->prepare("SELECT 1 FROM pg_indexes WHERE indexname = ?");
            $stmt->execute([$name]);
            return (bool) $stmt->fetchColumn();
        }
    }

    // ============================================
    // 1. WISHLIST TABLE
    // ============================================
    echo "<div class='info'>▶ Creating wishlist table...</div>";
    $pdo->exec("DROP TABLE IF EXISTS wishlist CASCADE");
    echo "<div class='info'>  ✓ Dropped existing wishlist table (if any)</div>";

    $pdo->exec("
        CREATE TABLE wishlist (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            UNIQUE(user_id, product_id)
        )
    ");
    echo "<div class='success'>  ✅ Wishlist table created successfully</div>";

    $pdo->exec("CREATE INDEX idx_wishlist_user_id ON wishlist(user_id)");
    $pdo->exec("CREATE INDEX idx_wishlist_product_id ON wishlist(product_id)");
    echo "<div class='success'>  ✅ Wishlist indexes created</div>";

    // ============================================
    // 2. REVIEWS TABLE
    // ============================================
    echo "<div class='info'>▶ Creating reviews table...</div>";
    $pdo->exec("DROP TABLE IF EXISTS reviews CASCADE");
    echo "<div class='info'>  ✓ Dropped existing reviews table (if any)</div>";

    $pdo->exec("
        CREATE TABLE reviews (
            id SERIAL PRIMARY KEY,
            product_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            rating INTEGER CHECK (rating >= 1 AND rating <= 5),
            comment TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<div class='success'>  ✅ Reviews table created successfully</div>";

    $pdo->exec("CREATE INDEX idx_reviews_product_id ON reviews(product_id)");
    $pdo->exec("CREATE INDEX idx_reviews_user_id ON reviews(user_id)");
    $pdo->exec("CREATE INDEX idx_reviews_status ON reviews(status)");
    echo "<div class='success'>  ✅ Reviews indexes created</div>";

    // Reviews trigger
    echo "<div class='info'>▶ Creating reviews trigger...</div>";
    $pdo->exec("
        CREATE OR REPLACE FUNCTION update_reviews_updated_at()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ language 'plpgsql'
    ");
    $pdo->exec("
        DROP TRIGGER IF EXISTS update_reviews_updated_at ON reviews;
        CREATE TRIGGER update_reviews_updated_at
            BEFORE UPDATE ON reviews
            FOR EACH ROW
            EXECUTE FUNCTION update_reviews_updated_at()
    ");
    echo "<div class='success'>  ✅ Reviews trigger created</div>";

    // ============================================
    // 3. SUPPLIERS SYSTEM
    // ============================================
    echo "<hr style='margin:24px 0; border:0; border-top:1px solid #eee;'>";
    echo "<div class='info' style='font-weight:700; font-size:14px;'>▶ Setting up Suppliers system...</div>";

    // 3a. Create suppliers table
    if (witty_table_exists($pdo, 'suppliers')) {
        echo "<div class='warn'>  ⚠ suppliers table already exists — skipping creation</div>";
    } else {
        $pdo->exec("
            CREATE TABLE suppliers (
                id SERIAL PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                slug VARCHAR(150) UNIQUE,
                contact_person VARCHAR(100),
                email VARCHAR(150),
                phone VARCHAR(30),
                address TEXT,
                notes TEXT,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<div class='success'>  ✅ suppliers table created</div>";
    }

    // Indexes for suppliers
    if (!witty_index_exists($pdo, 'idx_suppliers_name')) {
        $pdo->exec("CREATE INDEX idx_suppliers_name ON suppliers(name)");
        echo "<div class='success'>  ✅ Index idx_suppliers_name created</div>";
    } else {
        echo "<div class='info'>  ✓ Index idx_suppliers_name already exists</div>";
    }

    if (!witty_index_exists($pdo, 'idx_suppliers_status')) {
        $pdo->exec("CREATE INDEX idx_suppliers_status ON suppliers(status)");
        echo "<div class='success'>  ✅ Index idx_suppliers_status created</div>";
    }

    // 3b. Add products.supplier_id column
    if (!witty_column_exists($pdo, 'products', 'supplier_id')) {
        $pdo->exec("
            ALTER TABLE products 
            ADD COLUMN supplier_id INTEGER 
            REFERENCES suppliers(id) ON DELETE SET NULL
        ");
        echo "<div class='success'>  ✅ Column products.supplier_id created</div>";
    } else {
        echo "<div class='warn'>  ⚠ Column products.supplier_id already exists</div>";
    }

    if (!witty_index_exists($pdo, 'idx_products_supplier_id')) {
        $pdo->exec("CREATE INDEX idx_products_supplier_id ON products(supplier_id)");
        echo "<div class='success'>  ✅ Index idx_products_supplier_id created</div>";
    }

    // 3c. Backfill suppliers from products.supplier (free text)
    echo "<div class='info'>▶ Backfilling suppliers from existing product data...</div>";
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT TRIM(supplier) AS name
            FROM products
            WHERE supplier IS NOT NULL 
              AND TRIM(supplier) <> '' 
              AND (supplier_id IS NULL)
        ");
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $created_count = 0;
        $linked_count  = 0;

        foreach ($names as $name) {
            // Skip if already exists
            $check = $pdo->prepare("SELECT id FROM suppliers WHERE LOWER(name) = LOWER(?)");
            $check->execute([$name]);
            $existing_id = $check->fetchColumn();

            if ($existing_id) {
                $supplier_id = $existing_id;
            } else {
                $slug = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)), '-');
                $ins = $pdo->prepare("INSERT INTO suppliers (name, slug) VALUES (?, ?) RETURNING id");
                $ins->execute([$name, $slug]);
                $supplier_id = $ins->fetchColumn();
                $created_count++;
            }

            // Link products
            $upd = $pdo->prepare("
                UPDATE products 
                SET supplier_id = ? 
                WHERE TRIM(supplier) = ? 
                  AND supplier_id IS NULL
            ");
            $upd->execute([$supplier_id, $name]);
            $linked_count += $upd->rowCount();
        }

        if ($created_count > 0 || $linked_count > 0) {
            echo "<div class='success'>  ✅ Created <strong>$created_count</strong> supplier record(s), linked <strong>$linked_count</strong> product(s)</div>";
        } else {
            echo "<div class='info'>  ✓ No new suppliers to backfill</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>  ✗ Backfill error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }

    // 3d. Optional: trigger to keep suppliers.updated_at current
    try {
        $pdo->exec("
            CREATE OR REPLACE FUNCTION update_suppliers_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language 'plpgsql'
        ");
        $pdo->exec("
            DROP TRIGGER IF EXISTS update_suppliers_updated_at ON suppliers;
            CREATE TRIGGER update_suppliers_updated_at
                BEFORE UPDATE ON suppliers
                FOR EACH ROW
                EXECUTE FUNCTION update_suppliers_updated_at()
        ");
        echo "<div class='success'>  ✅ Suppliers trigger created</div>";
    } catch (PDOException $e) {
        echo "<div class='warn'>  ⚠ Suppliers trigger skipped: " . htmlspecialchars($e->getMessage()) . "</div>";
    }

    // ============================================
    // 4. VERIFY ALL TABLES
    // ============================================
    echo "<hr style='margin:24px 0; border:0; border-top:1px solid #eee;'>";
    echo "<div class='info' style='font-weight:700;'>▶ Verifying tables...</div>";

    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name IN ('wishlist', 'reviews', 'suppliers')
        ORDER BY table_name
    ");
    $created_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($created_tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM \"$table\"");
        $count = $stmt->fetchColumn();
        echo "<div class='success'>  ✅ {$table} — {$count} record(s)</div>";
    }

    if (witty_column_exists($pdo, 'products', 'supplier_id')) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE supplier_id IS NOT NULL");
        $linked = $stmt->fetchColumn();
        echo "<div class='info'>  📊 products linked to suppliers: {$linked}</div>";
    }
    
// ============================================
// USER ADDRESSES TABLE
// ============================================
echo "<hr style='margin:24px 0; border:0; border-top:1px solid #eee;'>";
echo "<div class='info' style='font-weight:700; font-size:14px;'>▶ Setting up User Addresses system...</div>";

if (witty_table_exists($pdo, 'user_addresses')) {
    echo "<div class='warn'>  ⚠ user_addresses table already exists — skipping creation</div>";
} else {
    $pdo->exec("
        CREATE TABLE user_addresses (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            label VARCHAR(50) DEFAULT 'Home',
            recipient_name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            county VARCHAR(100) NOT NULL,
            city VARCHAR(100),
            address_line TEXT NOT NULL,
            delivery_instructions TEXT,
            is_default BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<div class='success'>  ✅ user_addresses table created</div>";
}

if (!witty_index_exists($pdo, 'idx_user_addresses_user')) {
    $pdo->exec("CREATE INDEX idx_user_addresses_user ON user_addresses(user_id)");
    echo "<div class='success'>  ✅ Index idx_user_addresses_user created</div>";
}

if (!witty_index_exists($pdo, 'idx_user_addresses_default')) {
    $pdo->exec("CREATE INDEX idx_user_addresses_default ON user_addresses(user_id, is_default) WHERE is_default = TRUE");
    echo "<div class='success'>  ✅ Index idx_user_addresses_default created</div>";
}

// Trigger for updated_at
try {
    $pdo->exec("
        CREATE OR REPLACE FUNCTION update_user_addresses_updated_at()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ language 'plpgsql'
    ");
    $pdo->exec("
        DROP TRIGGER IF EXISTS update_user_addresses_updated_at ON user_addresses;
        CREATE TRIGGER update_user_addresses_updated_at
            BEFORE UPDATE ON user_addresses
            FOR EACH ROW
            EXECUTE FUNCTION update_user_addresses_updated_at()
    ");
    echo "<div class='success'>  ✅ user_addresses trigger created</div>";
} catch (PDOException $e) {
    echo "<div class='warn'>  ⚠ Trigger skipped: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ============================================
// ORDERS: Add delivery columns (if missing)
// ============================================
if (!witty_column_exists($pdo, 'orders', 'delivery_county')) {
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_county VARCHAR(100)");
        echo "<div class='success'>  ✅ orders.delivery_county column added</div>";
    } catch (PDOException $e) {
        echo "<div class='warn'>  ⚠ " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
if (!witty_column_exists($pdo, 'orders', 'delivery_phone')) {
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_phone VARCHAR(30)");
        echo "<div class='success'>  ✅ orders.delivery_phone column added</div>";
    } catch (PDOException $e) {}
}
if (!witty_column_exists($pdo, 'orders', 'delivery_recipient')) {
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_recipient VARCHAR(150)");
        echo "<div class='success'>  ✅ orders.delivery_recipient column added</div>";
    } catch (PDOException $e) {}
}
if (!witty_column_exists($pdo, 'orders', 'address_id')) {
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN address_id INTEGER REFERENCES user_addresses(id) ON DELETE SET NULL");
        echo "<div class='success'>  ✅ orders.address_id column added</div>";
    } catch (PDOException $e) {}
}
    echo "</div>"; // close log div

    // ============================================
    // SUCCESS SUMMARY
    // ============================================
    echo "<h2 class='success'>✅ Setup Complete!</h2>";
    echo "<p>The following tables/columns have been created or updated:</p>";
    echo "<ul>";
    echo "<li><strong>wishlist</strong> — Store user favourite products</li>";
    echo "<li><strong>reviews</strong> — Product reviews and ratings</li>";
    echo "<li><strong>suppliers</strong> — Vendor/supplier records</li>";
    echo "<li><strong>products.supplier_id</strong> — Links products to a supplier</li>";
    echo "</ul>";

    echo "<p><strong>Schema reference:</strong></p>";
    echo "<ul>";
    echo "<li><strong>wishlist</strong>: id, user_id, product_id, created_at</li>";
    echo "<li><strong>reviews</strong>: id, product_id, user_id, rating (1–5), comment, status, created_at, updated_at</li>";
    echo "<li><strong>suppliers</strong>: id, name, slug, contact_person, email, phone, address, notes, status, created_at, updated_at</li>";
    echo "<li><strong>products</strong>: + supplier_id (FK → suppliers.id)</li>";
    echo "<li><strong>user_addresses</strong> — User delivery addresses (with default)</li>";
echo "<li><strong>orders</strong>: + delivery_county, delivery_phone, delivery_recipient, address_id</li>";
    echo "</ul>";

    echo "<p class='info'>Old free-text <code>products.supplier</code> column is preserved for backward compatibility.</p>";

    echo "<div style='margin-top: 20px;'>";
    echo "<a href='suppliers.php' class='btn'><i class='fas fa-truck'></i> Manage Suppliers</a>";
    echo "<a href='dashboard.php' class='btn btn-secondary'><i class='fas fa-tachometer-alt'></i> Go to Dashboard</a>";
    echo "<a href='shop.php' class='btn btn-secondary'><i class='fas fa-shopping-bag'></i> View Shop</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "</div>";

    echo "<h2 class='error'>❌ Error Creating Tables</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Hint:</strong> Make sure the 'users' and 'products' tables exist before running this script.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='javascript:history.back()' class='btn'><i class='fas fa-arrow-left'></i> Go Back</a>";
    echo "</div>";

    error_log('Database setup error: ' . $e->getMessage());
}

echo "
    </div>
</body>
</html>
";
?>
