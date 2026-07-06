<?php
// test_upload.php - Test file uploads
$upload_dir = 'uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

echo "Upload directory: " . realpath($upload_dir) . "<br>";
echo "Is writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    $target = $upload_dir . time() . '_' . $file['name'];
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        echo "File uploaded successfully: " . $target . "<br>";
    } else {
        echo "Failed to upload file. Error: " . $file['error'] . "<br>";
    }
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_file">
    <button type="submit">Upload</button>
</form>
