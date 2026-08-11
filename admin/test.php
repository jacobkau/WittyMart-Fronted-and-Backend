<?php
require_once 'includes/config.php';

echo "<h1>Cloudinary Debug Info</h1>";

// Check if class exists
echo "<h2>1. Class Check</h2>";
if (class_exists('Cloudinary\Cloudinary')) {
    echo "✅ Cloudinary class found<br>";
} else {
    echo "❌ Cloudinary class NOT found. Run: composer require cloudinary/cloudinary_php<br>";
}

// Check credentials
echo "<h2>2. Credentials Check</h2>";
echo "CLOUDINARY_CLOUD_NAME: " . (defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : 'Not defined') . "<br>";
echo "CLOUDINARY_API_KEY: " . (defined('CLOUDINARY_API_KEY') ? '****' . substr(CLOUDINARY_API_KEY, -4) : 'Not defined') . "<br>";
echo "CLOUDINARY_API_SECRET: " . (defined('CLOUDINARY_API_SECRET') ? '****' . substr(CLOUDINARY_API_SECRET, -4) : 'Not defined') . "<br>";

// Check global $cloudinary
echo "<h2>3. Cloudinary Instance</h2>";
global $cloudinary;
if ($cloudinary) {
    echo "✅ Cloudinary instance is ready<br>";
} else {
    echo "❌ Cloudinary instance is NULL<br>";
}

// Check composer autoloader
echo "<h2>4. Composer Autoloader</h2>";
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "✅ vendor/autoload.php exists<br>";
} else {
    echo "❌ vendor/autoload.php NOT found. Run: composer install<br>";
}

// List installed packages (if composer is available)
echo "<h2>5. Installed Packages</h2>";
if (file_exists(__DIR__ . '/../composer.json')) {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
    if (isset($composer['require'])) {
        echo "<pre>";
        print_r($composer['require']);
        echo "</pre>";
    }
}
