<?php
// ============================================
// UPLOAD NEWSLETTER IMAGE (to Cloudinary)
// POST multipart: attachment=@file
// ============================================
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/cloudinary_helper.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['attachment'];

// Validate it's an image
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');

if (!in_array($mime, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP, or GIF allowed']);
    exit;
}

// Size limit: 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Image too large (max 5MB)']);
    exit;
}

if (!function_exists('uploadToCloudinary')) {
    echo json_encode(['success' => false, 'message' => 'Cloudinary helper missing']);
    exit;
}

$result = uploadToCloudinary($file['tmp_name'], 'newsletters');

if (!$result || empty($result['success']) || empty($result['url'])) {
    $msg = $result['error'] ?? 'Upload failed';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

echo json_encode([
    'success' => true,
    'url'     => $result['url'],
]);
exit;
