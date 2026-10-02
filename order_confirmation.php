<?php
require_once 'includes/config.php';

if (!isset($_SESSION['order_success']) || !isset($_SESSION['order_number'])) {
    header('Location: index.php');
    exit();
}

$order_number = $_SESSION['order_number'];
unset($_SESSION['order_success'], $_SESSION['order_number']);

// Fetch order for email content
$order = null;
$items = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->execute([$order_number, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    if ($order) {
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll();
    }
} catch (PDOException $e) {}

$user = getCurrentUser();

$page_title = 'Order Confirmed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmed - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .oc-wrap { max-width:640px; margin:40px auto; padding:40px 30px; background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.08); text-align:center; }
        .oc-wrap i.big { font-size:72px; color:#28a745; margin-bottom:20px; }
        .oc-wrap h1 { color:#05573c; margin:0 0 10px; }
        .oc-num { font-size:20px; font-weight:700; color:#333; background:#f0faf5; padding:12px; border-radius:8px; margin:16px 0; }
        .oc-wrap p { color:#666; line-height:1.6; }
        .oc-summary { text-align:left; background:#f8f9fa; padding:20px; border-radius:10px; margin-top:24px; }
        .oc-summary h3 { margin:0 0 12px; font-size:15px; text-transform:uppercase; color:#888; letter-spacing:.5px; }
        .oc-line { display:flex; justify-content:space-between; padding:6px 0; font-size:14px; color:#555; }
        .oc-line.total { font-weight:700; color:#05573c; font-size:16px; border-top:2px solid #05573c; padding-top:10px; margin-top:8px; }
        .oc-actions { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-top:24px; }
        .oc-btn { padding:12px 26px; background:#05573c; color:#fff; border-radius:6px; text-decoration:none; font-weight:600; }
        .oc-btn.secondary { background:#6c757d; }
        .oc-btn:hover { opacity:.9; }
        .email-status { margin-top:16px; font-size:13px; color:#888; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <main>
        <div class="oc-wrap">
            <i class="fas fa-check-circle big"></i>
            <h1>Order Placed!</h1>
            <p>Thank you for shopping with WittyMart. We've received your order and will start processing it shortly.</p>
            <div class="oc-num">Order #<?php echo htmlspecialchars($order_number); ?></div>

            <?php if ($order): ?>
                <div class="oc-summary">
                    <h3>Summary</h3>
                    <?php foreach ($items as $it): ?>
                        <div class="oc-line">
                            <span><?php echo htmlspecialchars($it['product_name']); ?> × <?php echo $it['quantity']; ?></span>
                            <span>Ksh <?php echo number_format($it['total'], 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="oc-line"><span>Transport</span><span>Ksh <?php echo number_format($order['shipping_fee'] ?? 0, 0); ?></span></div>
                    <div class="oc-line total"><span>Total</span><span>Ksh <?php echo number_format($order['total'], 0); ?></span></div>
                </div>

                <?php if ($order['payment_method'] === 'mpesa' || $order['payment_method'] === 'paybill'): ?>
                    <div style="background:#fff3cd; color:#856404; padding:14px; border-radius:8px; margin-top:20px; text-align:left; font-size:14px;">
                        <strong><i class="fas fa-info-circle"></i> Payment pending</strong><br>
                        We'll send an M-Pesa prompt to <strong><?php echo htmlspecialchars($order['mpesa_phone'] ?? ''); ?></strong> shortly.
                    </div>
                <?php else: ?>
                    <div style="background:#d4edda; color:#155724; padding:14px; border-radius:8px; margin-top:20px; text-align:left; font-size:14px;">
                        <strong><i class="fas fa-money-bill-wave"></i> Pay on Delivery</strong><br>
                        Have Ksh <?php echo number_format($order['total'], 0); ?> ready when we deliver.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="oc-actions">
                <a href="orders.php" class="oc-btn"><i class="fas fa-box"></i> Track My Order</a>
                <a href="shop.php" class="oc-btn secondary"><i class="fas fa-store"></i> Continue Shopping</a>
            </div>

            <div class="email-status" id="emailStatus"></div>
        </div>
    </main>
    <?php include "footer.php"; ?>

    <!-- EmailJS -->
    <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <script>
        (function() {
            if (typeof emailjs !== 'undefined') {
                emailjs.init("YOUR_PUBLIC_KEY"); // <-- from EmailJS dashboard
            }
        })();

        document.addEventListener('DOMContentLoaded', function() {
            const statusEl = document.getElementById('emailStatus');
            if (typeof emailjs === 'undefined') { return; }

            const itemsHtml = <?php echo json_encode(
                implode('', array_map(function($it) {
                    return htmlspecialchars($it['product_name']) . ' × ' . $it['quantity'] . ' — Ksh ' . number_format($it['total'], 0) . "\n";
                }, $items))
            ); ?>;

            const params = {
                to_name:      <?php echo json_encode($user['name'] ?? ''); ?>,
                to_email:     <?php echo json_encode($user['email'] ?? ''); ?>,
                order_number: <?php echo json_encode($order_number); ?>,
                order_total:  <?php echo json_encode($order ? number_format($order['total'], 0) : ''); ?>,
                order_items:  itemsHtml
            };

            // Skip if we don't have an email
            if (!params.to_email) { statusEl.textContent = ''; return; }

            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending confirmation email…';

            emailjs.send("YOUR_SERVICE_ID", "YOUR_TEMPLATE_ID", params)
                .then(function(res) {
                    statusEl.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i> Confirmation email sent to ' + params.to_email;
                })
                .catch(function(err) {
                    console.error('Email send failed:', err);
                    statusEl.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#ffc107;"></i> Could not send email confirmation.';
                });
        });
    </script>
</body>
</html>
