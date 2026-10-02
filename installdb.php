<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<!DOCTYPE html><html><head><title>WittyMart DB Installer</title>";
    echo "<style>
        body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; max-width: 900px; margin: 30px auto; padding: 20px; background: #f8f9fa; color: #333; line-height: 1.6; }
        h1 { color: #05573c; margin-bottom: 5px; }
        h2 { color: #333; margin-top: 35px; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px; }
        h3 { color: #555; margin-top: 22px; font-size: 15px; }
        p { margin: 6px 0; }
        ul { margin: 8px 0; padding-left: 22px; }
        li { margin: 3px 0; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
        hr { border: 0; border-top: 1px solid #e0e0e0; margin: 30px 0; }
        .ok { color: #28a745; font-weight: 600; }
        .warn { color: #fd7e14; font-weight: 600; }
        .err { color: #dc3545; font-weight: 600; }
        .box { background: #fff; padding: 18px 24px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .summary { background: #e8f5f0; border-left: 4px solid #05573c; padding: 16px 20px; border-radius: 6px; margin-top: 25px; }
        a.btn { display: inline-block; padding: 10px 22px; background: #05573c; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 10px; }
        a.btn:hover { background: #03402c; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; color: #fff; }
        .badge-new { background: #28a745; }
        .badge-exists { background: #fd7e14; }
        .badge-err { background: #dc3545; }
    </style>";
    echo "</head><body>";

    echo "<h1>🛠️ WittyMart – Database Installer</h1>";
    echo "<p style='color:#666;'>Sets up the product image gallery and supporting tables/columns.</p>";

    $summary = [
        'created'  => [],
        'skipped'  => [],
        'errors'   => [],
    ];

    // Helper functions
    function tableExists($pdo, $table) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.tables 
            WHERE table_schema = 'public' AND table_name = ?
        ");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.columns 
            WHERE table_schema = 'public' AND table_name = ? AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    function indexExists($pdo, $index_name) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM pg_indexes WHERE indexname = ?
        ");
        $stmt->execute([$index_name]);
        return (bool) $stmt->fetchColumn();
    }

    // ============================================
    // 1. USERS TABLE – SAFETY UPDATES
    // ============================================
    echo "<div class='box'>";
    echo "<h2>1. Users Table – Safety Updates</h2>";

    $user_columns = [
        'username'        => "ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE;",
        'status'          => "ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active';",
        'profile_picture' => "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255);",
        'phone'           => "ALTER TABLE users ADD COLUMN phone VARCHAR(20);",
    ];

    foreach ($user_columns as $col => $sql) {
        echo "<h3>• Column: <code>users.$col</code></h3>";
        if (columnExists($pdo, 'users', $col)) {
            echo "<p class='warn'>⚠ Already exists – skipped</p>";
            $summary['skipped'][] = "users.$col";
        } else {
            try {
                $pdo->exec($sql);
                echo "<p class='ok'>✓ Created</p>";
                $summary['created'][] = "users.$col";
            } catch (PDOException $e) {
                echo "<p class='err'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
                $summary['errors'][] = "users.$col: " . $e->getMessage();
            }
        }
    }

    // Backfill usernames for any null rows
    if (columnExists($pdo, 'users', 'username')) {
        try {
            $stmt = $pdo->exec("
                UPDATE users 
                SET username = SPLIT_PART(email, '@', 1) 
                WHERE username IS NULL OR username = ''
            ");
            if ($stmt > 0) {
                echo "<p class='ok'>✓ Backfilled $stmt username(s) from email</p>";
            } else {
                echo "<p class='ok'>✓ All users already have a username</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='err'>✗ Backfill error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    echo "</div>";

    // ============================================
    // 2. PRODUCT_IMAGES TABLE (Multi-image gallery)
    // ============================================
    echo "<div class='box'>";
    echo "<h2>2. Product Images Gallery Table <span class='badge badge-new'>NEW FEATURE</span></h2>";

    if (tableExists($pdo, 'product_images')) {
        echo "<p class='warn'>⚠ Table <code>product_images</code> already exists – verifying columns</p>";
        $summary['skipped'][] = 'product_images (table)';
    } else {
        try {
            $pdo->exec("
                CREATE TABLE product_images (
                    id SERIAL PRIMARY KEY,
                    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                    image_url TEXT NOT NULL,
                    image_public_id VARCHAR(255),
                    display_order INTEGER DEFAULT 0,
                    is_primary BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            echo "<p class='ok'>✓ Table <code>product_images</code> created</p>";
            $summary['created'][] = 'product_images (table)';
        } catch (PDOException $e) {
            echo "<p class='err'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
            $summary['errors'][] = 'product_images table: ' . $e->getMessage();
        }
    }

    // Create indexes
    $indexes = [
        'idx_product_images_product' => "
            CREATE INDEX idx_product_images_product 
            ON product_images(product_id, display_order)
        ",
        'idx_product_images_primary' => "
            CREATE INDEX idx_product_images_primary 
            ON product_images(product_id, is_primary) 
            WHERE is_primary = TRUE
        ",
    ];

    foreach ($indexes as $name => $sql) {
        echo "<h3>• Index: <code>$name</code></h3>";
        if (indexExists($pdo, $name)) {
            echo "<p class='warn'>⚠ Already exists – skipped</p>";
            $summary['skipped'][] = $name;
        } else {
            try {
                $pdo->exec($sql);
                echo "<p class='ok'>✓ Created</p>";
                $summary['created'][] = $name;
            } catch (PDOException $e) {
                echo "<p class='err'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
                $summary['errors'][] = "$name: " . $e->getMessage();
            }
        }
    }
    echo "</div>";

    // ============================================
    // 3. BACKFILL product_images FROM products
    // ============================================
    echo "<div class='box'>";
    echo "<h2>3. Backfill Existing Product Images</h2>";
    echo "<p>Copies each product's current main image into <code>product_images</code> as the primary (MAIN) image.</p>";

    if (!tableExists($pdo, 'product_images')) {
        echo "<p class='err'>✗ Cannot backfill – product_images table missing</p>";
    } else {
        try {
            // Insert primary image for any product that doesn't already have one
            $stmt = $pdo->prepare("
                INSERT INTO product_images 
                    (product_id, image_url, image_public_id, display_order, is_primary)
                SELECT 
                    p.id, 
                    COALESCE(p.image_url, 
                             CASE WHEN p.image IS NOT NULL AND p.image <> '' 
                                  THEN 'uploads/products/' || p.image 
                                  ELSE NULL END),
                    COALESCE(p.image_public_id, ''),
                    0,
                    TRUE
                FROM products p
                WHERE (p.image_url IS NOT NULL AND p.image_url <> '' 
                       OR p.image IS NOT NULL AND p.image <> '')
                  AND NOT EXISTS (
                      SELECT 1 FROM product_images pi WHERE pi.product_id = p.id
                  )
            ");
            $stmt->execute();
            $count = $stmt->rowCount();

            if ($count > 0) {
                echo "<p class='ok'>✓ Backfilled $count product(s) with their main image</p>";
                $summary['created'][] = "$count product_images row(s)";
            } else {
                echo "<p class='ok'>✓ All products already have at least one gallery image</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='err'>✗ Backfill error: " . htmlspecialchars($e->getMessage()) . "</p>";
            $summary['errors'][] = 'backfill: ' . $e->getMessage();
        }
    }
    echo "</div>";

    // ============================================
    // 4. ACTIVITY_LOG TABLE (Safety check)
    // ============================================
    echo "<div class='box'>";
    echo "<h2>4. Activity Log Table</h2>";

    if (tableExists($pdo, 'activity_log')) {
        echo "<p class='warn'>⚠ Table <code>activity_log</code> already exists</p>";
        $summary['skipped'][] = 'activity_log';
    } else {
        try {
            $pdo->exec("
                CREATE TABLE activity_log (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER,
                    user_name VARCHAR(100),
                    action VARCHAR(50),
                    details TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            echo "<p class='ok'>✓ Table <code>activity_log</code> created</p>";
            $summary['created'][] = 'activity_log (table)';
        } catch (PDOException $e) {
            echo "<p class='err'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
            $summary['errors'][] = 'activity_log: ' . $e->getMessage();
        }
    }

    // Indexes for activity_log
    $log_indexes = [
        'idx_activity_log_user_id'    => "CREATE INDEX idx_activity_log_user_id ON activity_log(user_id);",
        'idx_activity_log_action'     => "CREATE INDEX idx_activity_log_action ON activity_log(action);",
        'idx_activity_log_created_at' => "CREATE INDEX idx_activity_log_created_at ON activity_log(created_at);",
    ];

    foreach ($log_indexes as $name => $sql) {
        if (indexExists($pdo, $name)) {
            echo "<p class='warn'>⚠ Index <code>$name</code> already exists</p>";
        } else {
            try {
                $pdo->exec($sql);
                echo "<p class='ok'>✓ Index <code>$name</code> created</p>";
                $summary['created'][] = $name;
            } catch (PDOException $e) {
                echo "<p class='err'>✗ $name: " . htmlspecialchars($e->getMessage()) . "</p>";
                $summary['errors'][] = "$name: " . $e->getMessage();
            }
        }
    }
    echo "</div>";

    // ============================================
    // 5. VERIFY SCHEMA
    // ============================================
    echo "<div class='box'>";
    echo "<h2>5. Verification</h2>";

    // products.image_url and image_public_id
    echo "<h3>• Products table image columns</h3>";
    echo "<ul>";
    foreach (['image', 'image_url', 'image_public_id'] as $col) {
        $exists = columnExists($pdo, 'products', $col);
        $icon = $exists ? '✅' : '❌';
        echo "<li>$icon <code>products.$col</code></li>";
    }
    echo "</ul>";

    // product_images columns
    if (tableExists($pdo, 'product_images')) {
        echo "<h3>• product_images columns</h3>";
        $stmt = $pdo->query("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'product_images' 
            ORDER BY ordinal_position
        ");
        echo "<ul>";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            echo "<li>✅ <code>{$col['column_name']}</code> ({$col['data_type']})</li>";
        }
        echo "</ul>";

        // Row counts
        $stmt = $pdo->query("SELECT COUNT(*) FROM product_images");
        $img_count = $stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(DISTINCT product_id) FROM product_images");
        $prod_count = $stmt->fetchColumn();
        echo "<p class='ok'>✓ <strong>$img_count</strong> image rows across <strong>$prod_count</strong> products</p>";
    } else {
        echo "<p class='err'>✗ product_images table missing</p>";
    }

    echo "</div>";

    // ============================================
    // SUMMARY
    // ============================================
    echo "<div class='summary'>";
    echo "<h2 style='border:none;margin-top:0;'>✅ Installation Complete</h2>";

    if (!empty($summary['created'])) {
        echo "<p class='ok'>Created:</p><ul>";
        foreach ($summary['created'] as $item) {
            echo "<li>✓ " . htmlspecialchars($item) . "</li>";
        }
        echo "</ul>";
    }

    if (!empty($summary['skipped'])) {
        echo "<p class='warn'>Skipped (already existed):</p><ul>";
        foreach ($summary['skipped'] as $item) {
            echo "<li>⚠ " . htmlspecialchars($item) . "</li>";
        }
        echo "</ul>";
    }

    if (!empty($summary['errors'])) {
        echo "<p class='err'>Errors:</p><ul>";
        foreach ($summary['errors'] as $item) {
            echo "<li>✗ " . htmlspecialchars($item) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='ok'>No errors. All good!</p>";
    }

    echo "</div>";

    echo "<p style='margin-top:30px; text-align:center;'>";
    echo "<a href='admin/products.php' class='btn'>← Go to Admin Products</a> &nbsp; ";
    echo "<a href='index.php' class='btn' style='background:#6c757d;'>← Back to Home</a>";
    echo "</p>";

    echo "</body></html>";

} catch (Exception $e) {
    echo "<p class='err'>Fatal: " . htmlspecialchars($e->getMessage()) . "</p>";
}
