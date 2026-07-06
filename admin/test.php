<?php
// test_upload.php - Test file uploads

// Use the correct absolute path
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

echo "Upload directory: " . $upload_dir . "<br>";
echo "Is writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $target = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $web_path = 'uploads/products/' . $filename;
        echo "File uploaded successfully!<br>";
        echo "Full path: " . $target . "<br>";
        echo "Web path: " . $web_path . "<br>";
        echo "URL: https://wittymart.onrender.com/" . $web_path . "<br>";
        echo '<img src="https://wittymart.onrender.com/' . $web_path . '" style="max-width: 300px; max-height: 300px;">';
    } else {
        echo "Failed to upload file. Error: " . $file['error'] . "<br>";
    }
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_file">
    <button type="submit">Upload</button>
</form>
