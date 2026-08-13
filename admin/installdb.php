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
            max-width: 800px;
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
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
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
            max-height: 300px;
            overflow-y: auto;
        }
        .log .success { color: #28a745; }
        .log .error { color: #dc3545; }
        .log .info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📦 Database Setup</h1>
        <p>Creating reviews and wishlist tables...</p>
        <div class='log'>";

try {
    $messages = [];
    
    // ============================================
    // CREATE WISHLIST TABLE
    // ============================================
    echo "<div class='info'>▶ Creating wishlist table...</div>";
    
    // Drop existing wishlist table
    $pdo->exec("DROP TABLE IF EXISTS wishlist CASCADE");
    echo "<div class='info'>  ✓ Dropped existing wishlist table (if any)</div>";
    
    // Create wishlist table
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
    
    // Create wishlist indexes
    $pdo->exec("CREATE INDEX idx_wishlist_user_id ON wishlist(user_id)");
    $pdo->exec("CREATE INDEX idx_wishlist_product_id ON wishlist(product_id)");
    echo "<div class='success'>  ✅ Wishlist indexes created</div>";
    
    // ============================================
    // CREATE REVIEWS TABLE
    // ============================================
    echo "<div class='info'>▶ Creating reviews table...</div>";
    
    // Drop existing reviews table
    $pdo->exec("DROP TABLE IF EXISTS reviews CASCADE");
    echo "<div class='info'>  ✓ Dropped existing reviews table (if any)</div>";
    
    // Create reviews table
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
    
    // Create reviews indexes
    $pdo->exec("CREATE INDEX idx_reviews_product_id ON reviews(product_id)");
    $pdo->exec("CREATE INDEX idx_reviews_user_id ON reviews(user_id)");
    $pdo->exec("CREATE INDEX idx_reviews_status ON reviews(status)");
    echo "<div class='success'>  ✅ Reviews indexes created</div>";
    
    // ============================================
    // CREATE REVIEWS TRIGGER FOR UPDATED_AT
    // ============================================
    echo "<div class='info'>▶ Creating reviews trigger...</div>";
    
    // Check if function exists, create if not
    $pdo->exec("
        CREATE OR REPLACE FUNCTION update_reviews_updated_at()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ language 'plpgsql'
    ");
    
    // Create trigger
    $pdo->exec("
        DROP TRIGGER IF EXISTS update_reviews_updated_at ON reviews;
        CREATE TRIGGER update_reviews_updated_at
            BEFORE UPDATE ON reviews
            FOR EACH ROW
            EXECUTE FUNCTION update_reviews_updated_at()
    ");
    echo "<div class='success'>  ✅ Reviews trigger created</div>";
    
    // ============================================
    // VERIFY TABLES
    // ============================================
    echo "<div class='info'>▶ Verifying tables...</div>";
    
    // Check if tables exist
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name IN ('wishlist', 'reviews')
        ORDER BY table_name
    ");
    $created_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($created_tables) === 2) {
        echo "<div class='success'>  ✅ All tables verified: " . implode(', ', $created_tables) . "</div>";
    } else {
        echo "<div class='error'>  ⚠️ Some tables may not have been created correctly</div>";
    }
    
    // Get table counts
    foreach ($created_tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM \"$table\"");
        $count = $stmt->fetchColumn();
        echo "<div class='info'>  📊 {$table}: {$count} records</div>";
    }
    
    echo "</div>"; // Close log div
    
    // ============================================
    // SUCCESS MESSAGE
    // ============================================
    echo "<h2 class='success'>✅ Setup Complete!</h2>";
    echo "<p>The following tables have been created successfully:</p>";
    echo "<ul>";
    echo "<li><strong>wishlist</strong> - Store user favorite products</li>";
    echo "<li><strong>reviews</strong> - Product reviews and ratings</li>";
    echo "</ul>";
    
    echo "<p><strong>Table Details:</strong></p>";
    echo "<ul>";
    echo "<li><strong>wishlist</strong> - Columns: id, user_id, product_id, created_at</li>";
    echo "<li><strong>reviews</strong> - Columns: id, product_id, user_id, rating (1-5), comment, status, created_at, updated_at</li>";
    echo "</ul>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='dashboard.php' class='btn'><i class='fas fa-tachometer-alt'></i> Go to Dashboard</a>";
    echo "<a href='shop.php' class='btn btn-secondary'><i class='fas fa-shopping-bag'></i> View Shop</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "</div>"; // Close log div
    
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
