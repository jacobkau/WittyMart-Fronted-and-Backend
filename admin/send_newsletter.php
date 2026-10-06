<?php
// ============================================
// SEND NEWSLETTER (server-side, via EmailJS)
// POST JSON: { recipients, subject, body, attachment_url }
// ============================================
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../includes/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$recipients     = $input['recipients']     ?? [];
$subject        = trim($input['subject']   ?? '');
$body           = trim($input['body']      ?? '');
$attachmentUrl  = trim($input['attachment_url'] ?? '');

if (!is_array($recipients) || count($recipients) === 0) {
    echo json_encode(['success' => false, 'message' => 'No recipients']);
    exit;
}
if ($subject === '' || $body === '') {
    echo json_encode(['success' => false, 'message' => 'Subject and body required']);
    exit;
}

// Limit batch size (defensive)
$recipients = array_slice($recipients, 0, 50);
$recipients = array_filter(array_map('trim', $recipients), function ($e) {
    return filter_var($e, FILTER_VALIDATE_EMAIL);
});

if (count($recipients) === 0) {
    echo json_encode(['success' => false, 'message' => 'No valid emails']);
    exit;
}

// Load EmailJS env
$publicKey  = getenv('EMAILJS_PUBLIC_KEY')  ?: '';
$privateKey = getenv('EMAILJS_PRIVATE_KEY') ?: '';
$serviceId  = getenv('EMAILJS_SERVICE_ID')  ?: '';
$templateId = getenv('EMAILJS_TEMPLATE_ID') ?: '';

// Optional: separate template for newsletters
$newsletterTemplate = getenv('EMAILJS_NEWSLETTER_TEMPLATE_ID') ?: $templateId;

if (!$publicKey || !$serviceId || !$newsletterTemplate) {
    error_log('send_newsletter: missing EmailJS env vars');
    echo json_encode(['success' => false, 'message' => 'Email service not configured']);
    exit;
}

// Build HTML email body (wrapper + content + optional image)
$safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES));

$attachmentHtml = '';
if ($attachmentUrl !== '' && filter_var($attachmentUrl, FILTER_VALIDATE_URL)) {
    $attachmentHtml = '<div style="text-align:center; margin: 0 0 20px;">'
                    . '<img src="' . htmlspecialchars($attachmentUrl, ENT_QUOTES) . '" alt="" style="max-width: 100%; height: auto; border-radius: 8px; display: block; margin: 0 auto;">'
                    . '</div>';
}

$siteUrl = 'https://wittymart.onrender.com';

$html = '<div style="font-family: -apple-system, \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 15px; color: #333; background: #f5f5f5; padding: 20px 10px;">'
      .   '<div style="max-width: 600px; margin: auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
      .     '<div style="background: linear-gradient(135deg, #05573c, #0a7a54); padding: 22px 26px; color: #fff;">'
      .       '<div style="font-size: 20px; font-weight: 800; letter-spacing: 0.3px;">WittyMart</div>'
      .       '<div style="font-size: 12px; opacity: 0.9; margin-top: 3px;">Newsletter Update</div>'
      .     '</div>'
      .     '<div style="padding: 30px 26px;">'
      .       '<h2 style="color: #05573c; margin: 0 0 16px; font-size: 22px;">' . htmlspecialchars($subject) . '</h2>'
      .       $attachmentHtml
      .       '<div style="font-size: 15px; line-height: 1.7; color: #333;">' . $safeBody . '</div>'
      .       '<div style="text-align: center; margin: 30px 0 0;">'
      .         '<a href="' . $siteUrl . '/shop.php" target="_blank" style="display: inline-block; padding: 12px 30px; background: #05573c; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px;">Shop Now</a>'
      .       '</div>'
      .     '</div>'
      .     '<div style="background: #fafafa; padding: 20px 26px; text-align: center; font-size: 12px; color: #999; line-height: 1.7; border-top: 1px solid #eee;">'
      .       '<strong style="color: #05573c; display: block; margin-bottom: 6px;">Thank you for being with WittyMart!</strong>'
      .       'You received this email because you subscribed to our newsletter.<br>'
      .       '<a href="' . $siteUrl . '" style="color: #05573c; text-decoration: none;">' . $siteUrl . '</a>'
      .     '</div>'
      .   '</div>'
      . '</div>';

// Send via EmailJS REST API, one at a time (EmailJS doesn't support bulk recipients)
$sent = 0;
$failed = 0;
$errors = [];

foreach ($recipients as $to) {
    $payload = [
        'service_id'      => $serviceId,
        'template_id'     => $newsletterTemplate,
        'user_id'         => $publicKey,
        'template_params' => [
            'to_email' => $to,
            'subject'  => $subject,
            'body'     => $html,
        ],
    ];
    if ($privateKey) {
        $payload['accessToken'] = $privateKey;
    }

    $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Origin: ' . $siteUrl,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $code < 200 || $code >= 300) {
        $failed++;
        $errors[] = $to . ': ' . ($err ?: ('HTTP ' . $code . ' ' . $resp));
    } else {
        $sent++;
    }

    // Tiny delay to avoid hammering EmailJS
    usleep(150000); // 150ms
}

// Log the send
try {
    // Best-effort — wrap in try/catch in case table doesn't exist yet
    $pdo->prepare("
        INSERT INTO newsletter_sends (subject, body, attachment_url, recipients_count, sent_count, failed_count, errors, sent_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        $subject,
        $body,
        $attachmentUrl ?: null,
        count($recipients),
        $sent,
        $failed,
        !empty($errors) ? implode("\n", $errors) : null,
        $_SESSION['user_id'] ?? null,
    ]);
} catch (PDOException $e) {
    error_log('Newsletter send log failed (table missing?): ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'sent'    => $sent,
    'failed'  => $failed,
    'errors'  => $errors,
]);
exit;
