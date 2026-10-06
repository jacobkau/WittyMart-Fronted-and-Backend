<?php
// ============================================
// NEWSLETTER SUBSCRIBE ENDPOINT
// ============================================
require_once 'includes/config.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Accept both JSON body and form-encoded
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$email = trim($input['email'] ?? '');

if ($email === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter your email address.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Optional extras
$source = trim($input['source'] ?? 'footer_newsletter');
$page   = trim($input['page']   ?? '');
$ip     = $_SERVER['REMOTE_ADDR'] ?? null;
$ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

try {
    // ---------------------------------------------
    // 1. Check if email already exists
    // ---------------------------------------------
    $stmt = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Already subscribed — refresh status to active and update timestamp
        $upd = $pdo->prepare("
            UPDATE newsletter_subscribers
            SET status = 'active',
                subscribed_at = COALESCE(subscribed_at, NOW())
            WHERE id = ?
        ");
        $upd->execute([$existing['id']]);

        $savedId = $existing['id'];
        $isNew   = false;
    } else {
        // Insert new subscriber
        $ins = $pdo->prepare("
            INSERT INTO newsletter_subscribers (email, status, ip_address, user_agent, source, subscribed_at, created_at)
            VALUES (?, 'active', ?, ?, ?, NOW(), NOW())
            RETURNING id
        ");
        $ins->execute([$email, $ip, $ua, $source]);
        $savedId = $ins->fetchColumn();
        $isNew   = true;
    }

    // Log activity (if helper exists)
    if (function_exists('logActivity') && !empty($_SESSION['user_id'])) {
        logActivity('newsletter_subscribe', "Newsletter subscription: {$email}", $_SESSION['user_id'], $_SESSION['user_name'] ?? '');
    }

} catch (PDOException $e) {
    // Graceful fallback: if some columns don't exist, try a minimal insert
    error_log('Newsletter subscribe DB error: ' . $e->getMessage());

    // Try a minimal insert without optional columns
    try {
        $stmt = $pdo->prepare("SELECT id FROM newsletter_subscribers WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        if (!$stmt->fetchColumn()) {
            $pdo->prepare("INSERT INTO newsletter_subscribers (email, status, created_at) VALUES (?, 'active', NOW())")
                ->execute([$email]);
        }
        $isNew = true;
    } catch (PDOException $e2) {
        error_log('Newsletter subscribe fallback failed: ' . $e2->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not save subscription. Please try again.']);
        exit;
    }
}

// ---------------------------------------------
// 2. Forward to Formspree (server-side, best-effort)
// ---------------------------------------------
$formspreeId = getenv('FORMSPREE_FORM_ID') ?: '';
if ($formspreeId !== '') {
    try {
        $payload = [
            'email'   => $email,
            'source'  => $source,
            'page'    => $page,
            '_subject' => 'New WittyMart newsletter subscriber',
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
            error_log('Formspree forward cURL error: ' . $err);
        } elseif ($code < 200 || $code >= 300) {
            error_log("Formspree forward failed: HTTP {$code} — {$resp}");
        }
    } catch (Throwable $e) {
        error_log('Formspree forward exception: ' . $e->getMessage());
    }
}

// ---------------------------------------------
// 3. Respond to the browser
// ---------------------------------------------
echo json_encode([
    'success' => true,
    'message' => $isNew
        ? 'Thank you for subscribing!'
        : 'You are already subscribed. Thanks!',
    'id'      => $savedId ?? null,
]);
exit;
