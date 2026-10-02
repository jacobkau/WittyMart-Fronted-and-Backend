<?php
// ============================================
// M-PESA STK PUSH CALLBACK HANDLER
// ============================================
require_once 'includes/config.php';

$rawPayload = file_get_contents('php://input');
error_log('M-Pesa Callback: ' . $rawPayload);

$payload = json_decode($rawPayload, true);

header('Content-Type: application/json');
http_response_code(200);

if (!$payload || !isset($payload['Body']['stkCallback'])) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit();
}

$callback          = $payload['Body']['stkCallback'];
$checkoutRequestId = $callback['CheckoutRequestID'] ?? '';
$resultCode        = $callback['ResultCode'] ?? -1;
$resultDesc        = $callback['ResultDesc'] ?? '';

try {
    // Match order by new column, fall back to legacy payment_reference
    $stmt = $pdo->prepare("
        SELECT id, order_number FROM orders
        WHERE mpesa_checkout_id = ? OR payment_reference = ?
        LIMIT 1
    ");
    $stmt->execute([$checkoutRequestId, $checkoutRequestId]);
    $order = $stmt->fetch();

    if (!$order) {
        error_log('M-Pesa Callback: No order for CheckoutRequestID ' . $checkoutRequestId);
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        exit();
    }

    if ($resultCode == 0) {
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];
        $receipt = '';
        foreach ($metadata as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $receipt = $item['Value'];
            }
        }

        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid',
                payment_reference = ?,
                status = 'processing',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$receipt, $order['id']]);

        error_log("M-Pesa Callback: Order {$order['order_number']} paid. Receipt: {$receipt}");
    } else {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'failed',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$order['id']]);

        error_log("M-Pesa Callback: Order {$order['order_number']} failed. Code: {$resultCode} — {$resultDesc}");
    }
} catch (PDOException $e) {
    error_log('M-Pesa Callback DB error: ' . $e->getMessage());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
exit();
