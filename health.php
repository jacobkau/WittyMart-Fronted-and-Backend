<?php
// health.php - Simple health check for Render

header('Content-Type: application/json');

echo json_encode([
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE']
], JSON_PRETTY_PRINT);
