<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Updating Database Tables...</h2>";

    // ===== 1. ADD STATUS COLUMN TO PRODUCTS TABLE =====
    echo "<h3>Adding Status Column to Products Table...</h3>";
    
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';");
        echo "<p style='color: green;'>✓ Status column added successfully</p>";
        
        // Update existing products to have default status
        $pdo->exec("UPDATE products SET status = 'active' WHERE status IS NULL;");
        echo "<p style='color: green;'>✓ Existing products updated with default status</p>";
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || 
            strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "<p style='color: orange;'>⚠ Status column already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding status column: " . $e->getMessage() . "</p>";
        }
    }

    // ===== 2. CREATE SLIDER IMAGES TABLE =====
    echo "<h3>Creating Slider Images Table...</h3>";
    
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS slider_images (
                id SERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255),
                image_path VARCHAR(255) NOT NULL,
                link VARCHAR(255),
                button_text VARCHAR(50) DEFAULT 'Shop Now',
                display_order INT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<p style='color: green;'>✓ Slider images table created successfully</p>";
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p style='color: orange;'>⚠ Slider images table already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error creating slider images table: " . $e->getMessage() . "</p>";
        }
    }

    // ===== 3. CREATE TRIGGER FOR SLIDER IMAGES =====
    echo "<h3>Creating Trigger for Slider Images...</h3>";
    
    try {
        // Create the function if it doesn't exist
        $pdo->exec("
            CREATE OR REPLACE FUNCTION update_slider_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language 'plpgsql'
        ");
        echo "<p style='color: green;'>✓ Update function created successfully</p>";
        
        // Drop existing trigger if it exists
        $pdo->exec("DROP TRIGGER IF EXISTS update_slider_updated_at ON slider_images;");
        
        // Create the trigger
        $pdo->exec("
            CREATE TRIGGER update_slider_updated_at 
            BEFORE UPDATE ON slider_images
            FOR EACH ROW 
            EXECUTE FUNCTION update_slider_updated_at_column();
        ");
        echo "<p style='color: green;'>✓ Slider images trigger created successfully</p>";
        
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ Trigger may already exist: " . $e->getMessage() . "</p>";
    }

    // ===== 4. INSERT SAMPLE SLIDER IMAGES =====
    echo "<h3>Inserting Sample Slider Images...</h3>";
    
    try {
        // Check if slider images already exist
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM slider_images");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            $pdo->exec("
                INSERT INTO slider_images (title, subtitle, image_path, link, button_text, display_order, status) VALUES 
                ('Smartphone Pro X', 'Grab the latest smartphone at 20% off!', 'images/smart.jpg', 'shop.php?category=electronics', 'Shop Now', 1, 'active'),
                ('Noise Cancelling Headphones', 'Experience sound like never before.', 'images/head1.jpeg', 'shop.php?category=accessories', 'Shop Now', 2, 'active'),
                ('Fitness Smartwatch', 'Track your health goals in style.', 'images/watch5.jpg', 'shop.php?category=wearables', 'Shop Now', 3, 'active'),
                ('Smart Home Devices', 'Make your home smarter with our devices.', 'images/laptops.jpeg', 'shop.php?category=electronics', 'Shop Now', 4, 'active')
            ");
            echo "<p style='color: green;'>✓ Sample slider images inserted successfully</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Sample slider images already exist (skipped)</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Error inserting sample slider images: " . $e->getMessage() . "</p>";
    }

    // ===== 5. CREATE UPLOAD DIRECTORY FOR SLIDER IMAGES =====
    echo "<h3>Creating Upload Directory...</h3>";
    
    $upload_dir = __DIR__ . '/uploads/slider/';
    if (!file_exists($upload_dir)) {
        if (mkdir($upload_dir, 0777, true)) {
            echo "<p style='color: green;'>✓ Upload directory created: " . htmlspecialchars($upload_dir) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Failed to create upload directory. Please create it manually.</p>";
        }
    } else {
        echo "<p style='color: green;'>✓ Upload directory already exists</p>";
    }

    // ===== SUMMARY =====
    echo "<h3 style='color: green; margin-top: 20px;'>✅ Database update completed successfully!</h3>";
    echo "<ul style='margin-top: 10px;'>";
    echo "<li>✓ Products table: Status column added</li>";
    echo "<li>✓ Slider images table: Created with all fields</li>";
    echo "<li>✓ Trigger: Added for automatic timestamp updates</li>";
    echo "<li>✓ Sample data: Inserted 4 sample slider images</li>";
    echo "<li>✓ Upload directory: Created for slider images</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
