<?php
// test_upload.php - Test file uploads with absolute path

// Use absolute path from root
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

echo "Upload directory: " . $upload_dir . "<br>";
echo "Is writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "<br>";

// Also check the web-accessible path
$web_dir = 'uploads/products/';
if (!file_exists($web_dir)) {
    mkdir($web_dir, 0777, true);
}
echo "Web directory: " . realpath($web_dir) . "<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    
    // Try absolute path
    $abs_target = $upload_dir . $filename;
    
    // Try relative path
    $rel_target = $web_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $abs_target)) {
        echo "File uploaded successfully to: " . $abs_target . "<br>";
        
        // Also copy to web path
        if (copy($abs_target, $rel_target)) {
            echo "File also copied to web path: " . $rel_target . "<br>";
        }
        
        echo "File URL: <a href='/uploads/products/" . $filename . "'>View File</a><br>";
    } else {
        echo "Failed to upload file. Error: " . $file['error'] . "<br>";
        echo "Check permissions on: " . $upload_dir . "<br>";
    }
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_file">
    <button type="submit">Upload</button>
</form>
