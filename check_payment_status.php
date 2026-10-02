<?php
// ============================================
// PAYMENT STATUS POLLING ENDPOINT
// Returns current payment_status for a user's order.
// ============================================
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unknown', 'error' => 'Not logged in']);
    exit();
}

$order_id = intval($_GET['order_id'] ?? 0);

if (!$order_id) {
    echo json_encode(['status' => 'unknown', 'error' => 'Invalid order']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT payment_status, status, payment_reference
        FROM orders
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['status' => 'unknown', 'error' => 'Order not found']);
        exit();
    }

    // Normalize statuses
    $ps = $order['payment_status'] ?? 'pending';

    if ($ps === 'paid') {
        echo json_encode(['status' => 'paid', 'receipt' => $order['payment_reference']]);
    } elseif ($ps === 'failed') {
        echo json_encode(['status' => 'failed']);
    } else {
        echo json_encode(['status' => 'pending']);
    }
} catch (PDOException $e) {
    error_log('Payment poll error: ' . $e->getMessage());
    echo json_encode(['status' => 'unknown', 'error' => 'Database error']);
}
