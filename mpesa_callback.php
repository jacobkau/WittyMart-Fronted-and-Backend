<?php
/**
 * M-Pesa STK Push Callback Handler
 * Receives payment result from Safaricom and updates the order.
 */

require_once 'includes/config.php';

// Log raw callback for debugging
$rawPayload = file_get_contents('php://input');
error_log('M-Pesa Callback: ' . $rawPayload);

$payload = json_decode($rawPayload, true);

// Always respond 200 OK so Safaricom stops retrying
header('Content-Type: application/json');
http_response_code(200);

if (!$payload || !isset($payload['Body']['stkCallback'])) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit();
}

$callback = $payload['Body']['stkCallback'];
$checkoutRequestId = $callback['CheckoutRequestID'] ?? '';
$resultCode = $callback['ResultCode'] ?? -1;
$resultDesc = $callback['ResultDesc'] ?? '';

try {
    // Find the order by CheckoutRequestID
    $stmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE payment_reference = ?");
    $stmt->execute([$checkoutRequestId]);
    $order = $stmt->fetch();

    if (!$order) {
        error_log('M-Pesa Callback: No order found for CheckoutRequestID ' . $checkoutRequestId);
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        exit();
    }

    if ($resultCode == 0) {
        // Payment successful — extract receipt
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];
        $receipt = '';
        $paidAmount = 0;
        $phone = '';

        foreach ($metadata as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') $receipt = $item['Value'];
            if ($item['Name'] === 'Amount')           $paidAmount = $item['Value'];
            if ($item['Name'] === 'PhoneNumber')      $phone = $item['Value'];
        }

        // Update order
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid',
                payment_reference = ?,
                status = 'processing',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$receipt, $order['id']]);

        error_log('M-Pesa Callback: Order ' . $order['order_number'] . ' paid. Receipt: ' . $receipt);
    } else {
        // Payment failed — mark it
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'failed',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$order['id']]);

        error_log('M-Pesa Callback: Order ' . $order['order_number'] . ' failed. Code: ' . $resultCode . ' — ' . $resultDesc);
    }
} catch (PDOException $e) {
    error_log('M-Pesa Callback DB error: ' . $e->getMessage());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
exit();
