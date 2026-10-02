<?php
// ============================================
// ORDER CONFIRMATION WITH EMAILJS
// ============================================
require_once 'includes/config.php';

if (!isset($_SESSION['order_success']) || !isset($_SESSION['order_number'])) {
    header('Location: index.php');
    exit();
}

$order_number   = $_SESSION['order_number'];
$mpesa_pending  = !empty($_SESSION['mpesa_pending']);
$mpesa_message  = $_SESSION['mpesa_message'] ?? '';

// Clear flash keys (do NOT clear order_number yet; we need it below)
unset($_SESSION['order_success'], $_SESSION['mpesa_pending'], $_SESSION['mpesa_message']);

// ============================================
// FETCH ORDER + ITEMS FOR DISPLAY AND EMAIL
// ============================================
$order = null;
$items = [];

try {
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE order_number = ? AND user_id = ?
    ");
    $stmt->execute([$order_number, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if ($order) {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Order confirmation fetch: ' . $e->getMessage());
}

$user = getCurrentUser();

// Build items text for the email body
$itemsText = '';
foreach ($items as $it) {
    $itemsText .= $it['product_name'] . ' × ' . $it['quantity'] . ' — Ksh ' . number_format($it['total'], 0) . "\n";
}

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
        .oc-wrap i.big {
            font-size: 72px;
            color: #28a745;
            margin-bottom: 20px;
        }
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

        .oc-status-card.mpesa {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .oc-status-card.mpesa-warn {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .oc-status-card.cod {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .oc-status-card strong { display: block; margin-bottom: 4px; }
        .oc-status-card small { opacity: 0.85; display: block; margin-top: 6px; }

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
                        <span>Total Paid</span>
                        <span>Ksh <?php echo number_format($order['total'], 0); ?></span>
                    </div>
                </div>

                <?php if (in_array($order['payment_method'], ['mpesa', 'paybill'])): ?>
                    <?php if ($mpesa_pending): ?>
                        <div class="oc-status-card mpesa">
                            <i class="fas fa-mobile-alt"></i>
                            <div>
                                <strong>M-Pesa Payment Request Sent</strong>
                                Check your phone
                                <strong><?php echo htmlspecialchars($order['mpesa_phone'] ?? ''); ?></strong>
                                and enter your PIN to complete payment of
                                <strong>Ksh <?php echo number_format($order['total'], 0); ?></strong>.
                                <small>
                                    Didn't receive the prompt? Dial <strong>*334#</strong>
                                    or visit <a href="orders.php" style="color:#0c5460; font-weight:600;">My Orders</a> to retry.
                                </small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="oc-status-card mpesa-warn">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Payment Prompt Not Sent</strong>
                                We couldn't initiate the M-Pesa prompt automatically.
                                <?php if (!empty($mpesa_message)): ?>
                                    <br><small><?php echo htmlspecialchars($mpesa_message); ?></small>
                                <?php endif; ?>
                                <small>
                                    Please retry from <a href="orders.php" style="color:#856404; font-weight:600;">My Orders</a>
                                    or contact support.
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($order['payment_method'] === 'pay_on_delivery'): ?>
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
         EMAILJS CONFIRMATION
         ============================================ -->
    <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <script>
        (function() {
            if (typeof emailjs !== 'undefined') {
                emailjs.init("YOUR_PUBLIC_KEY"); // TODO: replace with your EmailJS public key
            }
        })();

        document.addEventListener('DOMContentLoaded', function() {
            const statusEl = document.getElementById('emailStatus');
            if (typeof emailjs === 'undefined' || !statusEl) return;

            // Build items list string for the email
            const itemsText = <?php echo json_encode($itemsText); ?>;

            const params = {
                to_name:      <?php echo json_encode($user['name'] ?? ''); ?>,
                to_email:     <?php echo json_encode($user['email'] ?? ''); ?>,
                order_number: <?php echo json_encode($order_number); ?>,
                order_total:  <?php echo json_encode($order ? number_format($order['total'], 0) : ''); ?>,
                order_items:  itemsText,
                delivery_to:  <?php echo json_encode($order['shipping_address'] ?? ''); ?>
            };

            if (!params.to_email) {
                statusEl.textContent = '';
                return;
            }

            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending confirmation email…';

            emailjs.send("YOUR_SERVICE_ID", "YOUR_TEMPLATE_ID", params)
                .then(function(res) {
                    statusEl.innerHTML =
                        '<i class="fas fa-check-circle ok"></i> Confirmation email sent to ' +
                        params.to_email;
                })
                .catch(function(err) {
                    console.error('Email send failed:', err);
                    statusEl.innerHTML =
                        '<i class="fas fa-exclamation-triangle err"></i> Could not send email confirmation.';
                });
        });
    </script>
</body>
</html>
