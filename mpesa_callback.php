<?php
// ============================================
// M-PESA STK PUSH CALLBACK HANDLER
// Receives the async result of the STK push from Safaricom.
// ============================================
require_once 'includes/config.php';

$rawPayload = file_get_contents('php://input');
error_log('=== MPESA CALLBACK HIT ===');
error_log('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
error_log('RAW: ' . $rawPayload);

header('Content-Type: application/json');

$payload = json_decode($rawPayload, true);

// Always return 200 for malformed payloads so Safaricom doesn't retry endlessly
if (!$payload || !isset($payload['Body']['stkCallback'])) {
    error_log('M-Pesa Callback: malformed payload');
    http_response_code(200);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit();
}

$callback          = $payload['Body']['stkCallback'];
$checkoutRequestId = $callback['CheckoutRequestID'] ?? '';
$resultCode        = $callback['ResultCode'] ?? -1;
$resultDesc        = $callback['ResultDesc'] ?? '';

error_log("M-Pesa Callback: CRI={$checkoutRequestId} ResultCode={$resultCode} Desc={$resultDesc}");

if ($checkoutRequestId === '') {
    error_log('M-Pesa Callback: missing CheckoutRequestID');
    http_response_code(200);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit();
}

try {
    // Match order by mpesa_checkout_id (primary), fall back to payment_reference (legacy)
    $stmt = $pdo->prepare("
        SELECT id, order_number, payment_status
        FROM orders
        WHERE mpesa_checkout_id = ? OR payment_reference = ?
        LIMIT 1
    ");
    $stmt->execute([$checkoutRequestId, $checkoutRequestId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("M-Pesa Callback: NO ORDER for CheckoutRequestID {$checkoutRequestId}");

        // Dump recent orders to help diagnose (remove in production if noisy)
        try {
            $recent = $pdo->query("
                SELECT id, order_number, mpesa_checkout_id, payment_status
                FROM orders
                ORDER BY id DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            error_log('M-Pesa Callback: recent orders = ' . json_encode($recent));
        } catch (PDOException $e) {
            // ignore
        }

        http_response_code(200);
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        exit();
    }

    error_log("M-Pesa Callback: matched order id={$order['id']} number={$order['order_number']}");

    // Already processed? Don't overwrite a paid order if Safaricom retries
    if ($order['payment_status'] === 'paid') {
        error_log("M-Pesa Callback: order {$order['order_number']} already paid — skipping");
        http_response_code(200);
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        exit();
    }

    if ((int)$resultCode === 0) {
        // ---- SUCCESS ----
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];
        $receipt  = '';
        $amount   = null;
        $phone    = null;

        foreach ($metadata as $item) {
            $name = $item['Name'] ?? '';
            switch ($name) {
                case 'MpesaReceiptNumber': $receipt = $item['Value'] ?? ''; break;
                case 'Amount':             $amount  = $item['Value'] ?? null; break;
                case 'PhoneNumber':        $phone   = $item['Value'] ?? null; break;
            }
        }

        if ($receipt === '') {
            // Rare but possible — still mark paid using CRI as fallback receipt
            $receipt = $checkoutRequestId;
            error_log("M-Pesa Callback: no receipt number in metadata for {$checkoutRequestId}, using CRI");
        }

        $upd = $pdo->prepare("
            UPDATE orders
            SET payment_status    = 'paid',
                payment_reference = ?,
                mpesa_receipt     = ?,
                paid_at           = NOW(),
                status            = 'processing',
                updated_at        = NOW()
            WHERE id = ?
        ");
        $upd->execute([$receipt, $receipt, $order['id']]);

        error_log("M-Pesa Callback: PAID. rowsAffected={$upd->rowCount()} receipt={$receipt} for order {$order['order_number']}");

    } else {
        // ---- FAILED / CANCELLED ----
        $upd = $pdo->prepare("
            UPDATE orders
            SET payment_status          = 'failed',
                payment_failure_reason  = ?,
                updated_at              = NOW()
            WHERE id = ?
        ");
        $upd->execute([substr($resultDesc, 0, 255), $order['id']]);

        error_log("M-Pesa Callback: FAILED. rowsAffected={$upd->rowCount()} Code={$resultCode} Desc={$resultDesc} for order {$order['order_number']}");
    }

    http_response_code(200);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit();

} catch (PDOException $e) {
    error_log('M-Pesa Callback DB ERROR: ' . $e->getMessage());
    error_log('M-Pesa Callback SQL state: ' . $e->getCode());

    // Return 500 so Safaricom retries the callback
    http_response_code(500);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Database error']);
    exit();
}
