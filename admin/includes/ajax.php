<?php

require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

global $pdo;

switch ($action) {
    case 'get_product':
        if (!isAdmin()) {
            $response = ['success' => false, 'message' => 'Unauthorized'];
            break;
        }
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            $response = ['success' => false, 'message' => 'Invalid product ID'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $response = [
                    'success' => true,
                    'product' => $product
                ];
            } else {
                $response = ['success' => false, 'message' => 'Product not found'];
            }
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Action not found'];
}

echo json_encode($response);
