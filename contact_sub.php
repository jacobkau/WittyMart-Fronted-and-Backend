<?php
// ============================================
// CONTACT FORM ENDPOINT 
// ============================================
require_once 'includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$name    = trim($input['name']    ?? '');
$email   = trim($input['email']   ?? '');
$message = trim($input['message'] ?? '');
$subject = trim($input['subject'] ?? 'New contact message from WittyMart');

// Validation
if ($name === '' || $email === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}
if (strlen($message) < 5) {
    echo json_encode(['success' => false, 'message' => 'Please write a longer message.']);
    exit;
}

// ---- Save to DB (matches your exact schema) ----
$savedId = null;
try {
    $stmt = $pdo->prepare("
        INSERT INTO contact_us (name, email, message, status, created_at)
        VALUES (?, ?, ?, 'unread', NOW())
        RETURNING id
    ");
    $stmt->execute([$name, $email, $message]);
    $savedId = $stmt->fetchColumn();

    if (function_exists('logActivity')) {
        logActivity('contact_message', "Contact from {$email}",
            $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? '');
    }
} catch (PDOException $e) {
    error_log('Contact DB save error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not save your message. Please try again.']);
    exit;
}

// ---- Forward to Formspree (best-effort) ----
$formspreeId = getenv('CONTACT_FORMSPREE_ID') ?: (getenv('FORMSPREE_FORM_ID') ?: '');

if ($formspreeId !== '') {
    try {
        $payload = [
            'name'     => $name,
            'email'    => $email,
            'message'  => $message,
            '_subject' => $subject,
            'source'   => 'contact_form',
            'page'     => $input['page'] ?? '',
        ];

        $ch = curl_init('https://formspree.io/f/' . $formspreeId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log('Formspree contact forward cURL error: ' . $err);
        } elseif ($code < 200 || $code >= 300) {
            error_log("Formspree contact forward failed: HTTP {$code} — {$resp}");
        }
    } catch (Throwable $e) {
        error_log('Formspree contact forward exception: ' . $e->getMessage());
    }
}

// ---- Respond ----
echo json_encode([
    'success' => true,
    'message' => "Thank you, {$name}! Your message has been sent. We'll get back to you soon.",
    'id'      => $savedId,
]);
exit;
