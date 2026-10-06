<?php
// ============================================
// ORDER CONFIRMATION WITH DEFERRED STK PUSH + EMAILJS
// ============================================
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// ============================================
// SITE URL (needed for email image + link URLs)
// ============================================
$siteUrl = 'https://wittymart.onrender.com/'; // TODO: replace with your real domain

// ============================================
// RESOLVE ORDER NUMBER
// ============================================
$order_number = $_SESSION['order_number'] ?? '';

if (!$order_number && !empty($_GET['order'])) {
    $order_number = trim($_GET['order']);
}

if (!$order_number) {
    try {
        $stmt = $pdo->prepare("
            SELECT order_number FROM orders 
            WHERE user_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $order_number = $stmt->fetchColumn() ?: '';
    } catch (PDOException $e) {
        error_log('Order confirmation fallback: ' . $e->getMessage());
    }
}

if (!$order_number) {
    header('Location: index.php');
    exit();
}

// ============================================
// CAPTURE STK DETAILS BEFORE CLEARING SESSION
// ============================================
$stk_needed    = !empty($_SESSION['stk_needed']);
$stk_phone     = $_SESSION['stk_phone']     ?? '';
$stk_amount    = $_SESSION['stk_amount']    ?? 0;
$stk_reference = $_SESSION['stk_reference'] ?? '';
$stk_order_id  = $_SESSION['order_id']      ?? 0;

unset(
    $_SESSION['order_success'],
    $_SESSION['order_number'],
    $_SESSION['order_id'],
    $_SESSION['stk_needed'],
    $_SESSION['stk_phone'],
    $_SESSION['stk_amount'],
    $_SESSION['stk_reference']
);

// ============================================
// FETCH ORDER + ITEMS
// ============================================
$order = null;
$items = [];

try {
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE order_number = ? AND user_id = ?
    ");
    $stmt->execute([$order_number, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.image, p.image_url
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$stk_order_id) {
            $stk_order_id  = (int)$order['id'];
            $stk_phone     = $order['mpesa_phone'] ?? '';
            $stk_amount    = (float)$order['total'];
            $stk_reference = 'ORD' . $order['id'];
            $stk_needed    = !empty($order['mpesa_phone'])
                             && in_array($order['payment_method'], ['mpesa','paybill'], true)
                             && $order['payment_status'] === 'awaiting_payment';
        }
    }
} catch (PDOException $e) {
    error_log('Order confirmation fetch: ' . $e->getMessage());
}

$user = getCurrentUser();

$itemsText = '';
foreach ($items as $it) {
    $itemsText .= $it['product_name'] . ' × ' . $it['quantity'] . ' — Ksh ' . number_format($it['total'], 0) . "\n";
}

// ============================================
// BUILD EMAIL — ITEMS HTML WITH CLOUDINARY IMAGES
// ============================================
$itemsHtml = '';
foreach ($items as $it) {
    // -------- Resolve image URL (Cloudinary preferred) --------
    $img = '';

    // 1) Cloudinary / absolute URL — optionally optimize
    if (!empty($it['image_url']) && preg_match('#^https?://#i', $it['image_url'])) {
        // Inject 128x128 transform right after /upload/ if not already present
        if (strpos($it['image_url'], '/upload/') !== false
            && strpos($it['image_url'], 'w_128') === false) {
            $img = preg_replace(
                '#/upload/#',
                '/upload/w_128,h_128,c_fill,q_auto,f_auto/',
                $it['image_url'],
                1
            );
        } else {
            $img = $it['image_url'];
        }
    }
    // 2) Local file fallback (browser only — some clients block these)
    elseif (!empty($it['image'])) {
        $img = rtrim($siteUrl, '/') . '/uploads/products/' . ltrim($it['image'], '/');
    }
    // 3) Placeholder
    else {
        $img = rtrim($siteUrl, '/') . '/uploads/products/no-image.png';
    }

    $itemsHtml .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 4px;">'
              .    '<tr style="vertical-align: top;">'
              .      '<td style="padding: 16px 8px 8px 4px; width: 76px;">'
              .        '<img src="' . htmlspecialchars($img, ENT_QUOTES) . '" alt="' . htmlspecialchars($it['product_name'], ENT_QUOTES) . '" width="64" height="64" style="height: 64px; width: 64px; object-fit: cover; border-radius: 8px; background: #f5f5f5; display: block; border: 0; outline: none; text-decoration: none;">'
              .      '</td>'
              .      '<td style="padding: 16px 8px 8px 8px; width: 100%;">'
              .        '<div style="font-weight: 600; color: #222;">' . htmlspecialchars($it['product_name']) . '</div>'
              .        '<div style="font-size: 13px; color: #888; padding-top: 4px;">QTY: ' . (int)$it['quantity'] . ' &nbsp;·&nbsp; Ksh ' . number_format((float)$it['price'], 0) . ' each</div>'
              .      '</td>'
              .      '<td style="padding: 16px 4px 8px 0; white-space: nowrap; text-align: right; font-weight: 700; color: #05573c;">'
              .        'Ksh ' . number_format((float)$it['total'], 0)
              .      '</td>'
              .    '</tr>'
              .  '</table>'
              .  '<div style="border-bottom: 1px solid #f0f0f0;"></div>';
}

$discount  = (float)($order['coupon_discount'] ?? 0);
$couponRow = '';
if ($discount > 0) {
    $code = $order['coupon_code'] ?? '';
    $couponRow = '<tr>'
               .   '<td style="width: 60%;"></td>'
               .   '<td style="color: #16a34a; font-weight: 600;">Coupon ' . htmlspecialchars($code, ENT_QUOTES) . '</td>'
               .   '<td style="padding: 8px; white-space: nowrap; color: #16a34a; font-weight: 600;">-Ksh ' . number_format($discount, 0) . '</td>'
               . '</tr>';
}

$subtotal = 0;
foreach ($items as $it) $subtotal += (float)$it['total'];

$orderDate = $order ? date('d M Y, H:i', strtotime($order['created_at'])) : '';

$paymentTitle = 'Order Confirmed';
$paymentMessage = 'Your order has been received and is being processed.';
if ($order) {
    $pm = $order['payment_method'] ?? '';
    $ps = $order['payment_status'] ?? '';
    $totalFmt = number_format((float)$order['total'], 0);

    if (in_array($pm, ['mpesa', 'paybill'])) {
        if ($ps === 'paid') {
            $paymentTitle   = 'Payment Confirmed';
            $paymentMessage = "We have received your payment of Ksh {$totalFmt}. Your order is now being processed.";
        } elseif ($ps === 'failed') {
            $paymentTitle   = 'Payment Not Completed';
            $paymentMessage = 'Your M-Pesa payment was cancelled or failed. Retry from My Orders.';
        } else {
            $paymentTitle   = 'Awaiting Payment';
            $paymentMessage = "Check your phone and enter your M-Pesa PIN to complete payment of Ksh {$totalFmt}.";
        }
    } elseif ($pm === 'pay_on_delivery' || $pm === 'cash') {
        $paymentTitle   = 'Pay on Delivery';
        $paymentMessage = "Have Ksh {$totalFmt} ready in cash when we deliver.";
    }
}

$orderUrl = $siteUrl . '/order_confirmation.php?order=' . urlencode($order_number);
$shopUrl  = $siteUrl . '/shop.php';

$page_title = 'Order Confirmed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .oc-wrap {
            max-width: 680px;
            margin: 40px auto;
            padding: 40px 30px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .oc-wrap i.big { font-size: 72px; color: #28a745; margin-bottom: 20px; }
        .oc-wrap h1 { color: #05573c; margin: 0 0 10px; font-size: 26px; }
        .oc-wrap p { color: #666; line-height: 1.6; font-size: 15px; }

        .oc-num {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            background: #f0faf5;
            border: 2px dashed #05573c;
            padding: 14px;
            border-radius: 10px;
            margin: 20px 0;
            letter-spacing: 1px;
            font-family: monospace;
        }

        .oc-summary {
            text-align: left;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 24px;
        }
        .oc-summary h3 {
            margin: 0 0 12px;
            font-size: 13px;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 0.8px;
        }
        .oc-line {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #555;
        }
        .oc-line.total {
            font-weight: 700;
            color: #05573c;
            font-size: 16px;
            border-top: 2px solid #05573c;
            padding-top: 12px;
            margin-top: 10px;
        }

        .oc-status-card {
            padding: 16px 18px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: left;
            font-size: 14px;
            line-height: 1.6;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .oc-status-card i { font-size: 20px; flex-shrink: 0; margin-top: 2px; }
        .oc-status-card strong { display: block; margin-bottom: 4px; }
        .oc-status-card small { opacity: 0.85; display: block; margin-top: 6px; }

        .oc-status-card.mpesa { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        .oc-status-card.mpesa-warn { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .oc-status-card.cod { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .oc-status-card.failed { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }

        .receipt-btn-row {
            display: flex;
            justify-content: center;
            margin-top: 14px;
        }
        .btn-receipt {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 12px 26px;
            background: #fff;
            color: #05573c;
            border: 2px solid #05573c;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.18s ease;
            box-shadow: 0 2px 6px rgba(5,87,60,0.08);
        }
        .btn-receipt:hover {
            background: #05573c;
            color: #fff;
            box-shadow: 0 4px 12px rgba(5,87,60,0.25);
            transform: translateY(-1px);
        }
        .btn-receipt i { font-size: 15px; }

        .receipt-hint {
            font-size: 12px;
            color: #888;
            margin-top: 8px;
            text-align: center;
        }

        .oc-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 28px;
        }
        .oc-btn {
            padding: 12px 26px;
            background: #05573c;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .oc-btn:hover { background: #03402c; }
        .oc-btn.secondary { background: #6c757d; }
        .oc-btn.secondary:hover { background: #5a6268; }

        .email-status {
            margin-top: 20px;
            font-size: 13px;
            color: #888;
            min-height: 20px;
        }
        .email-status .ok { color: #28a745; }
        .email-status .err { color: #ffc107; }

        .pay-status {
            margin-top: 16px;
            font-size: 13px;
            color: #0c5460;
            background: #e8f4f8;
            padding: 12px 14px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pay-status.paid { background: #d4edda; color: #155724; }
        .pay-status.failed { background: #f8d7da; color: #721c24; }
        .pay-status.timeout { background: #fff3cd; color: #856404; }

        .pay-status .pay-actions {
            display: flex;
            gap: 8px;
            margin-left: 8px;
            flex-wrap: wrap;
        }
        .pay-status .pay-actions a,
        .pay-status .pay-actions button {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        .pay-status .btn-retry { background: #05573c; color: #fff; }
        .pay-status .btn-retry:hover { background: #03402c; }
        .pay-status .btn-orders { background: rgba(0,0,0,0.08); color: inherit; }
        .pay-status .btn-orders:hover { background: rgba(0,0,0,0.15); }

        .pay-countdown {
            font-size: 12px;
            opacity: 0.8;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <main>
        <div class="oc-wrap">
            <i class="fas fa-check-circle big"></i>
            <h1>Order Placed Successfully!</h1>
            <p>Thank you for shopping with WittyMart. We've received your order and will start processing it right away.</p>

            <div class="oc-num">Order #<?php echo htmlspecialchars($order_number); ?></div>

            <?php if ($order): ?>
                <div class="oc-summary">
                    <h3>Order Summary</h3>
                    <?php foreach ($items as $it): ?>
                        <div class="oc-line">
                            <span><?php echo htmlspecialchars($it['product_name']); ?> × <?php echo $it['quantity']; ?></span>
                            <span>Ksh <?php echo number_format($it['total'], 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="oc-line">
                        <span>Transport</span>
                        <span>Ksh <?php echo number_format($order['shipping_fee'] ?? 0, 0); ?></span>
                    </div>
                    <div class="oc-line total">
                        <span>Total</span>
                        <span>Ksh <?php echo number_format($order['total'], 0); ?></span>
                    </div>
                </div>

                <?php if (in_array($order['payment_method'], ['mpesa', 'paybill'])): ?>
                    <?php if ($order['payment_status'] === 'paid'): ?>
                        <div class="oc-status-card cod" id="mpesaCard">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Payment Confirmed</strong>
                                We've received your payment of
                                <strong>Ksh <?php echo number_format($order['total'], 0); ?></strong>.
                                Your order is now being processed.
                                <?php if (!empty($order['mpesa_receipt'])): ?>
                                    <small>Receipt: <?php echo htmlspecialchars($order['mpesa_receipt']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="receipt-btn-row">
                            <a class="btn-receipt"
                               href="receipt.php?order=<?php echo urlencode($order['order_number']); ?>"
                               target="_blank"
                               rel="noopener">
                                <i class="fas fa-file-invoice"></i> Download Receipt
                            </a>
                        </div>
                        <div class="receipt-hint">
                            Opens in a new tab &mdash; use “Save as PDF” in the print dialog.
                        </div>

                    <?php elseif ($order['payment_status'] === 'failed'): ?>
                        <div class="oc-status-card failed" id="mpesaCard">
                            <i class="fas fa-times-circle"></i>
                            <div>
                                <strong>Payment Not Completed</strong>
                                Your M-Pesa payment was cancelled or failed.
                                <?php if (!empty($order['payment_failure_reason'])): ?>
                                    <small><?php echo htmlspecialchars($order['payment_failure_reason']); ?></small>
                                <?php endif; ?>
                                Your order is still saved — retry from
                                <a href="orders.php" style="color:#721c24; font-weight:600; text-decoration:underline;">My Orders</a>.
                            </div>
                        </div>

                        <div class="pay-status failed" id="payStatus">
                            <i class="fas fa-times-circle"></i>
                            <span>Payment was cancelled or timed out.</span>
                            <div class="pay-actions">
                                <a href="orders.php" class="btn-retry"><i class="fas fa-redo"></i> Retry Payment</a>
                                <a href="orders.php" class="btn-orders">My Orders</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="oc-status-card mpesa" id="mpesaCard">
                            <i class="fas fa-mobile-alt"></i>
                            <div>
                                <strong>Payment Request Sent</strong>
                                Check your phone <strong><?php echo htmlspecialchars($order['mpesa_phone'] ?? ''); ?></strong>
                                and enter your M-Pesa PIN to complete payment of
                                <strong>Ksh <?php echo number_format($order['total'], 0); ?></strong>.
                                <small>
                                    Didn't receive the prompt? Wait for the counter below or
                                    <a href="orders.php" style="color:#0c5460; font-weight:600;">retry from My Orders</a>.
                                </small>
                            </div>
                        </div>

                        <div class="pay-status" id="payStatus">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span id="payStatusText">Waiting for payment confirmation…</span>
                            <span class="pay-countdown" id="payCountdown"></span>
                        </div>
                    <?php endif; ?>

                <?php elseif ($order['payment_method'] === 'pay_on_delivery' || $order['payment_method'] === 'cash'): ?>
                    <div class="oc-status-card cod">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>
                            <strong>Pay on Delivery</strong>
                            Have <strong>Ksh <?php echo number_format($order['total'], 0); ?></strong>
                            ready in cash when we deliver to
                            <strong><?php echo htmlspecialchars($order['delivery_county'] ?? ''); ?></strong>.
                            <small>Our rider will call <?php echo htmlspecialchars($order['delivery_phone'] ?? ''); ?> before arriving.</small>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="padding:20px; background:#fff3cd; color:#856404; border-radius:10px; text-align:left;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Order not found</strong>
                    <p style="margin:6px 0 0;">We couldn't find your order details, but your order was likely placed. Check <a href="orders.php" style="color:#856404; font-weight:600;">My Orders</a> for confirmation.</p>
                </div>
            <?php endif; ?>

            <div class="oc-actions">
                <a href="track_order.php?order=<?php echo urlencode($order_number); ?>" class="oc-btn">
                    <i class="fas fa-map-marker-alt"></i> Track Order
                </a>
                <a href="shop.php" class="oc-btn secondary">
                    <i class="fas fa-store"></i> Continue Shopping
                </a>
            </div>

            <div class="email-status" id="emailStatus"></div>
        </div>
    </main>
    <?php include "footer.php"; ?>

    <!-- ============================================
         EMAILJS + PAYMENT STATUS POLLING
         ============================================ -->
    <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <script>
        (function() {
            if (typeof emailjs !== 'undefined') {
                emailjs.init("YOUR_EMAILJS_PUBLIC_KEY"); // TODO: replace
            }
        })();

        document.addEventListener('DOMContentLoaded', function() {
            const statusEl = document.getElementById('emailStatus');

            // ============================================
            // EMAIL CONFIRMATION
            // ============================================
            if (typeof emailjs !== 'undefined' && statusEl) {
                const params = {
                    to_name:      <?php echo json_encode($user['name'] ?? 'Customer'); ?>,
                    to_email:     <?php echo json_encode($user['email'] ?? ''); ?>,
                    order_number: <?php echo json_encode($order_number); ?>,
                    order_date:   <?php echo json_encode($orderDate); ?>,

                    items_html:   <?php echo json_encode($itemsHtml); ?>,
                    subtotal:     <?php echo json_encode(number_format($subtotal, 0)); ?>,
                    coupon_row:   <?php echo json_encode($couponRow); ?>,
                    shipping_fee: <?php echo json_encode(number_format((float)($order['shipping_fee'] ?? 0), 0)); ?>,
                    order_total:  <?php echo json_encode($order ? number_format($order['total'], 0) : ''); ?>,

                    delivery_recipient: <?php echo json_encode($order['delivery_recipient'] ?? ($user['name'] ?? '')); ?>,
                    delivery_address:   <?php echo json_encode($order['shipping_address'] ?? ''); ?>,
                    delivery_phone:     <?php echo json_encode($order['delivery_phone'] ?? ''); ?>,

                    payment_title:   <?php echo json_encode($paymentTitle); ?>,
                    payment_message: <?php echo json_encode($paymentMessage); ?>,

                    order_url: <?php echo json_encode($orderUrl); ?>,
                    shop_url:  <?php echo json_encode($shopUrl); ?>
                };

                if (params.to_email) {
                    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending confirmation email…';

                    emailjs.send("YOUR_SERVICE_ID", "YOUR_TEMPLATE_ID", params)
                        .then(function() {
                            statusEl.innerHTML = '<i class="fas fa-check-circle ok"></i> Confirmation email sent to ' + params.to_email;
                        })
                        .catch(function(err) {
                            console.error('Email send failed:', err);
                            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle err"></i> Could not send email confirmation.';
                        });
                }
            }

            // ============================================
            // POLL PAYMENT STATUS (for M-Pesa orders still pending)
            // ============================================
            <?php if ($order && in_array($order['payment_method'], ['mpesa','paybill']) && $order['payment_status'] === 'awaiting_payment'): ?>
            const payStatusEl   = document.getElementById('payStatus');
            const payCountdown  = document.getElementById('payCountdown');
            const orderId       = <?php echo (int)$order['id']; ?>;

            let pollCount = 0;
            const maxPolls = 40;
            const startTime = Date.now();
            const maxDurationMs = maxPolls * 5000;

            function setStatus(state, html) {
                if (!payStatusEl) return;
                payStatusEl.className = 'pay-status ' + state;
                payStatusEl.innerHTML = html;
            }

            function showCountdown() {
                if (!payCountdown) return;
                const elapsed = Date.now() - startTime;
                const remaining = Math.max(0, Math.floor((maxDurationMs - elapsed) / 1000));
                if (remaining <= 0) {
                    payCountdown.textContent = '';
                } else {
                    const mins = Math.floor(remaining / 60);
                    const secs = remaining % 60;
                    payCountdown.textContent = '(' + mins + ':' + (secs < 10 ? '0' : '') + secs + ')';
                }
            }

            function showFailed(reason) {
                const msg = reason || 'Payment was cancelled or timed out.';
                setStatus('failed',
                    '<i class="fas fa-times-circle"></i>' +
                    '<span>' + msg + '</span>' +
                    '<div class="pay-actions">' +
                        '<a href="orders.php" class="btn-retry"><i class="fas fa-redo"></i> Retry Payment</a>' +
                        '<a href="orders.php" class="btn-orders">My Orders</a>' +
                    '</div>'
                );

                const mpesaCard = document.getElementById('mpesaCard');
                if (mpesaCard) {
                    mpesaCard.className = 'oc-status-card failed';
                    mpesaCard.innerHTML = '<i class="fas fa-times-circle"></i><div><strong>Payment Not Completed</strong>Your M-Pesa payment was cancelled or timed out. Your order is still saved — retry from <a href="orders.php" style="color:#721c24; font-weight:600; text-decoration:underline;">My Orders</a>.</div>';
                }
            }

            function showTimeout() {
                setStatus('timeout',
                    '<i class="fas fa-clock"></i>' +
                    '<span>Still waiting for payment…</span>' +
                    '<div class="pay-actions">' +
                        '<a href="orders.php" class="btn-retry"><i class="fas fa-sync"></i> Check Status</a>' +
                        '<a href="orders.php" class="btn-orders">My Orders</a>' +
                    '</div>'
                );
            }

            function showPaid(receipt) {
                const receiptLine = receipt
                    ? '<small style="display:block; margin-top:4px; opacity:0.8;">Receipt: ' + receipt + '</small>'
                    : '';

                setStatus('paid',
                    '<i class="fas fa-check-circle"></i>' +
                    '<span>Payment confirmed! Your order is being processed.</span>' +
                    receiptLine +
                    '<div class="pay-actions" style="margin-top:8px;">' +
                        '<a href="receipt.php?order=' + encodeURIComponent(<?php echo json_encode($order['order_number']); ?>) + '" target="_blank" rel="noopener" class="btn-retry" style="background:#fff; color:#05573c; border:1.5px solid #05573c;">' +
                            '<i class="fas fa-file-invoice"></i> Download Receipt' +
                        '</a>' +
                    '</div>'
                );

                const mpesaCard = document.getElementById('mpesaCard');
                if (mpesaCard) {
                    mpesaCard.className = 'oc-status-card cod';
                    mpesaCard.innerHTML = '<i class="fas fa-check-circle"></i><div><strong>Payment Confirmed</strong>We have received your payment of <strong>Ksh ' + <?php echo json_encode(number_format($order['total'], 0)); ?> + '</strong>. Your order is now being processed.</div>';
                }

                if (!document.querySelector('.receipt-btn-row')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'receipt-btn-row';
                    wrapper.innerHTML = '<a class="btn-receipt" href="receipt.php?order=' + encodeURIComponent(<?php echo json_encode($order['order_number']); ?>) + '" target="_blank" rel="noopener"><i class="fas fa-file-invoice"></i> Download Receipt</a>';
                    if (mpesaCard && mpesaCard.parentNode) {
                        mpesaCard.parentNode.insertBefore(wrapper, mpesaCard.nextSibling);
                    }
                }
            }

            function pollPayment() {
                pollCount++;

                fetch('check_payment_status.php?order_id=' + orderId + '&_=' + Date.now(), {
                    credentials: 'same-origin',
                    cache: 'no-store'
                })
                .then(r => r.json())
                .then(data => {
                    if (!payStatusEl) return;

                    if (data.status === 'paid') {
                        showPaid(data.receipt || null);
                        return;
                    }
                    if (data.status === 'failed') {
                        showFailed();
                        return;
                    }

                    showCountdown();

                    if (pollCount >= maxPolls) {
                        showTimeout();
                        return;
                    }
                    setTimeout(pollPayment, 5000);
                })
                .catch(() => {
                    showCountdown();
                    if (pollCount >= maxPolls) {
                        showTimeout();
                        return;
                    }
                    setTimeout(pollPayment, 8000);
                });
            }

            if (payStatusEl) {
                showCountdown();
                setTimeout(pollPayment, 3000);
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
// ============================================
// DEFERRED STK PUSH TRIGGER
// Runs AFTER the page has been sent to the user.
// ============================================
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    flush();
}

if ($stk_needed && $stk_phone && $stk_order_id) {
    error_log("Deferred STK block START: order={$stk_order_id} phone={$stk_phone} amount={$stk_amount}");

    try {
        require_once 'includes/mpesa_service.php';

        if (!class_exists('MpesaService')) {
            error_log('Deferred STK: MpesaService class not found');
            throw new Exception('MpesaService class missing');
        }

        $mpesa      = new MpesaService();
        $normalized = MpesaService::normalizePhone($stk_phone);

        if (!$normalized) {
            error_log('Deferred STK: phone normalization failed for ' . $stk_phone);
            throw new Exception('Invalid phone');
        }

        $push = $mpesa->stkPush(
            $normalized,
            (int) ceil($stk_amount),
            'ORD' . $stk_order_id,
            'WittyMart Order'
        );

        if ($push && !empty($push['CheckoutRequestID'])) {
            try {
                $upd = $pdo->prepare("
                    UPDATE orders
                    SET mpesa_checkout_id = ?,
                        payment_reference = ?,
                        updated_at        = NOW()
                    WHERE id = ?
                ");
                $upd->execute([
                    $push['CheckoutRequestID'],
                    $push['CheckoutRequestID'],
                    $stk_order_id
                ]);

                error_log("STK Push sent: {$push['CheckoutRequestID']} for order #{$stk_order_id} (rowsAffected={$upd->rowCount()})");
            } catch (PDOException $e) {
                error_log("Deferred STK: failed to save CheckoutRequestID for order #{$stk_order_id}: " . $e->getMessage());
            }
        } else {
            error_log("STK Push FAILED for order #{$stk_order_id}. Response=" . json_encode($push));

            try {
                $pdo->prepare("
                    UPDATE orders
                    SET payment_status = 'failed',
                        payment_failure_reason = 'STK push request failed',
                        updated_at     = NOW()
                    WHERE id = ?
                ")->execute([$stk_order_id]);
            } catch (PDOException $e) {
                error_log("Deferred STK: failed to mark order #{$stk_order_id} as failed: " . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Deferred STK Push EXCEPTION: ' . $e->getMessage());
    }

    error_log("Deferred STK block END: order={$stk_order_id}");
} else {
    error_log("Deferred STK block skipped: stk_needed=" . var_export($stk_needed, true)
        . " phone={$stk_phone} order_id={$stk_order_id}");
}
