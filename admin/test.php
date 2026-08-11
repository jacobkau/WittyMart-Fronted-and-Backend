<?php
// Create a test file: test_cloudinary.php
require_once 'includes/config.php';

if (class_exists('Cloudinary\Cloudinary')) {
    echo "Cloudinary is installed!<br>";
    echo "Cloud Name: " . CLOUDINARY_CLOUD_NAME . "<br>";
    
    global $cloudinary;
    if ($cloudinary) {
        echo "Cloudinary instance is ready!";
    } else {
        echo "Cloudinary instance is null";
    }
} else {
    echo "Cloudinary is NOT installed!";
}
?>
