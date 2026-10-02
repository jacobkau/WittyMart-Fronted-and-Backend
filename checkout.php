<?php
// ============================================
// TEMPORARY DEBUG — REMOVE AFTER FIXING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


require_once 'includes/config.php';
require_once 'includes/cloudinary_helper.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header('Location: home.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user      = getCurrentUser();

$cartItems = [];
$total     = 0;
$error     = '';
$order_error = '';

// ============================================
// LOAD CART
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, c.product_id, c.quantity,
               p.name, p.price, p.image, p.image_url, p.stock
        FROM cart c
        INNER JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $cartItems = $stmt->fetchAll();
    foreach ($cartItems as $item) $total += $item['price'] * $item['quantity'];
} catch (PDOException $e) {
    error_log('Cart error: ' . $e->getMessage());
    $error = 'Could not load cart items.';
}

if (empty($cartItems)) {
    header('Location: cart.php');
    exit();
}

// ============================================
// LOAD ADDRESSES
// ============================================
$userAddresses = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_addresses
        WHERE user_id = ?
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$user_id]);
    $userAddresses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Addresses load: ' . $e->getMessage());
}

$selectedAddressId = intval($_SESSION['selected_address_id'] ?? 0);
$selectedAddress   = null;

foreach ($userAddresses as $a) {
    if ((int)$a['id'] === $selectedAddressId) { $selectedAddress = $a; break; }
}
if (!$selectedAddress && !empty($userAddresses)) {
    foreach ($userAddresses as $a) if ($a['is_default']) { $selectedAddress = $a; break; }
    if (!$selectedAddress) $selectedAddress = $userAddresses[0];
    $selectedAddressId = (int)$selectedAddress['id'];
    $_SESSION['selected_address_id'] = $selectedAddressId;
}

if (!$selectedAddress) {
    $_SESSION['flash_error'] = 'Please add a delivery address before checkout.';
    header('Location: cart.php');
    exit();
}

// ============================================
// COUPON
// ============================================
$couponCode = '';
$discount   = 0;
if (!empty($_SESSION['coupon'])) {
    $couponCode = $_SESSION['coupon']['code'];
    $discount   = floatval($_SESSION['coupon']['discount']);
}
$totalAfterDiscount = max(0, $total - $discount);

// ============================================
// TRANSPORT FEE
// ============================================
function countyTransportFee($county) {
    $nearby = ['Nairobi','Kiambu','Machakos','Kajiado',"Murang'a",'Nyeri','Kirinyaga','Embu','Nakuru'];
    $mid    = ['Mombasa','Kisumu','Uasin Gishu','Kakamega','Meru','Laikipia','Bungoma','Kisii','Nyamira','Kericho','Bomet','Narok'];
    if (in_array($county, $nearby, true)) return 100;
    if (in_array($county, $mid, true))    return 150;
    return 200;
}

$transportFee = countyTransportFee($selectedAddress['county']);
$grandTotal   = $totalAfterDiscount + $transportFee;

// ============================================
// HANDLE ORDER SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $payment_method  = sanitize($_POST['payment_method'] ?? '');
        $post_address_id = intval($_POST['address_id'] ?? 0);
        $mpesa_phone     = sanitize($_POST['mpesa_phone'] ?? '');

        $validAddress = null;
        foreach ($userAddresses as $a) if ((int)$a['id'] === $post_address_id) { $validAddress = $a; break; }

        if (!$validAddress) {
            $order_error = 'Please select a valid delivery address.';
        } elseif (empty($payment_method)) {
            $order_error = 'Please select a payment method.';
        } elseif (in_array($payment_method, ['mpesa', 'paybill']) && empty($mpesa_phone)) {
            $order_error = 'Please enter the M-Pesa phone number.';
        } else {
            $payment_status = in_array($payment_method, ['mpesa', 'paybill'])
                ? 'awaiting_payment'
                : 'pending';

            $pdo->beginTransaction();

            // Stock locks
            $stock_error = false;
            foreach ($cartItems as $item) {
                $lock = $pdo->prepare("SELECT stock, name FROM products WHERE id = ? FOR UPDATE");
                $lock->execute([$item['product_id']]);
                $row = $lock->fetch();
                if (!$row || $row['stock'] < $item['quantity']) {
                    $order_error = "Product '{$row['name']}' has insufficient stock.";
                    $stock_error = true;
                    break;
                }
            }

            if ($stock_error) {
                $pdo->rollBack();
            } else {
                $transportFee = countyTransportFee($validAddress['county']);
                $shipping_fee = $transportFee;
                $order_total  = $totalAfterDiscount + $transportFee;

                // Order number
                $order_number = null;
                for ($i = 0; $i < 5; $i++) {
                    $candidate = 'ORD-' . date('Ymd') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
                    $check = $pdo->prepare("SELECT 1 FROM orders WHERE order_number = ?");
                    $check->execute([$candidate]);
                    if (!$check->fetchColumn()) { $order_number = $candidate; break; }
                }
                if (!$order_number) throw new Exception('Order number generation failed.');

                $shipping_address = trim(
                    $validAddress['address_line'] . ', ' .
                    $validAddress['county'] .
                    (!empty($validAddress['city']) ? ', ' . $validAddress['city'] : '')
                );

                // Insert order
                $stmt = $pdo->prepare("
                    INSERT INTO orders
                    (user_id, order_number, total, shipping_fee, status,
                     payment_method, payment_status, shipping_address, shipping_city,
                     delivery_instructions, delivery_county, delivery_phone,
                     delivery_recipient, address_id, mpesa_phone, created_at)
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id, $order_number, $order_total, $shipping_fee,
                    $payment_method, $payment_status,
                    $shipping_address, $validAddress['city'] ?? '',
                    $validAddress['delivery_instructions'] ?? '',
                    $validAddress['county'], $validAddress['phone'],
                    $validAddress['recipient_name'], $validAddress['id'],
                    $mpesa_phone
                ]);
                $order_id = $pdo->lastInsertId();

                // Items
                $stmtItem  = $pdo->prepare("
                    INSERT INTO order_items
                    (order_id, product_id, product_name, quantity, price, total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

                foreach ($cartItems as $item) {
                    $item_total = $item['price'] * $item['quantity'];
                    $stmtItem->execute([
                        $order_id, $item['product_id'], $item['name'],
                        $item['quantity'], $item['price'], $item_total
                    ]);
                    $stmtStock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                    if ($stmtStock->rowCount() === 0) {
                        throw new Exception("Stock changed for '{$item['name']}'.");
                    }
                }

                // Coupon use
                if (!empty($_SESSION['coupon']['id'])) {
                    $pdo->prepare("UPDATE coupons SET uses = uses + 1 WHERE id = ?")
                        ->execute([$_SESSION['coupon']['id']]);
                }

                // Clear cart
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

                $pdo->commit();
                error_log("CHECKOUT DEBUG: Order #$order_number created, ID=$order_id");
                error_log("CHECKOUT DEBUG: Redirecting to order_confirmation.php");

                logActivity('order_placed', 'Order #' . $order_number, $user_id, $user_name);

                // ============================================
                // STASH STK DETAILS IN SESSION — DO NOT CALL MPESA HERE
                // The push will be triggered from order_confirmation.php
                // AFTER the response has been sent to the user.
                // ============================================
                $stk_needed = in_array($payment_method, ['mpesa', 'paybill']) && !empty($mpesa_phone);

                $_SESSION['order_success']  = true;
                $_SESSION['order_number']   = $order_number;
                $_SESSION['order_id']       = $order_id;
                $_SESSION['stk_needed']     = $stk_needed;
                $_SESSION['stk_phone']      = $mpesa_phone;
                $_SESSION['stk_amount']     = $order_total;
                $_SESSION['stk_reference']  = 'ORD' . $order_id;

                unset($_SESSION['selected_address_id'], $_SESSION['coupon'], $_SESSION['delivery']);

                header('Location: order_confirmation.php');
                exit();
            }
        }
   } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Checkout error: ' . $e->getMessage());
    $order_error = 'DEBUG: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
}
}

$page_title = 'Checkout';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .checkout-container { display: grid; grid-template-columns: 3fr 2fr; gap: 30px; margin: 20px 0; }
        .checkout-form, .order-summary { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .order-summary { position: sticky; top: 20px; align-self: start; }
        .checkout-form h2, .order-summary h2 { margin-top: 0; margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 8px; }
        .order-summary h2 { border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }

        .checkout-address {
            background: #f0faf5; border: 2px solid #05573c;
            border-radius: 10px; padding: 16px 18px;
            margin-bottom: 20px; position: relative;
        }
        .checkout-address .addr-label {
            display: inline-block; font-size: 11px; font-weight: 700;
            padding: 2px 8px; border-radius: 10px;
            background: #05573c; color: #fff;
            margin-bottom: 8px; text-transform: uppercase;
        }
        .checkout-address .addr-recipient { font-weight: 700; color: #222; font-size: 15px; margin-bottom: 4px; }
        .checkout-address .addr-line { color: #444; font-size: 14px; line-height: 1.6; }
        .checkout-address .addr-phone { color: #666; font-size: 13px; margin-top: 4px; }
        .checkout-address .addr-instructions {
            font-size: 13px; color: #666; font-style: italic;
            margin-top: 8px; padding-top: 8px; border-top: 1px dashed #cfe6dd;
        }
        .checkout-address .change-addr-link {
            position: absolute; top: 14px; right: 16px;
            font-size: 13px; font-weight: 600;
            color: #05573c; text-decoration: none;
        }

        .payment-methods {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px; margin-top: 8px;
        }
        .payment-methods label {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 10px;
            cursor: pointer; transition: all 0.2s; text-align: center;
        }
        .payment-methods label:hover { border-color: #05573c; }
        .payment-methods label.selected { border-color: #05573c; background: #f0faf5; }
        .payment-methods input[type=radio] { display: none; }
        .payment-methods i { font-size: 22px; }
        .payment-methods .pm-label { font-size: 13px; font-weight: 700; color: #333; }
        .payment-methods .pm-sub { font-size: 11px; color: #888; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; font-size: 14px; }
        .form-group input[type=text], .form-group input[type=tel] {
            width: 100%; padding: 12px 14px;
            border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 14px; transition: all 0.2s;
        }
        .form-group input:focus { outline: none; border-color: #05573c; box-shadow: 0 0 0 3px rgba(5,87,60,0.1); }

        .btn-place-order {
            width: 100%; padding: 15px;
            background: #05573c; color: #fff; border: none;
            border-radius: 8px; font-size: 17px; font-weight: 700;
            cursor: pointer; margin-top: 10px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: all 0.2s;
        }
        .btn-place-order:hover:not(:disabled) { background: #03402c; }
        .btn-place-order:disabled { opacity: 0.7; cursor: not-allowed; }

        .order-item { display: flex; gap: 15px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .order-item:last-child { border-bottom: none; }
        .order-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; background: #f5f5f5; }
        .order-item-details { flex: 1; min-width: 0; }
        .order-item-details h4 { margin: 0 0 3px; font-size: 14px; color: #333; }
        .order-item-details .item-price { font-size: 13px; color: #05573c; font-weight: 600; }
        .order-item-details .item-quantity { font-size: 12px; color: #888; }

        .order-totals { margin-top: 20px; padding-top: 15px; border-top: 2px solid #f0f0f0; }
        .order-totals .total-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 15px; color: #555; }
        .order-totals .total-row.discount { color: #28a745; font-weight: 600; }
        .order-totals .total-row.grand-total {
            font-size: 20px; font-weight: 700; color: #05573c;
            border-top: 2px solid #05573c;
            padding-top: 15px; margin-top: 5px;
        }

        .alert-error {
            padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;
            background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
        }

        .mpesa-info {
            background: #d1ecf1; color: #0c5460;
            padding: 12px 14px; border-radius: 8px;
            font-size: 13px; margin-top: 8px;
            display: flex; gap: 8px; align-items: flex-start;
        }

        @media (max-width: 992px) {
            .checkout-container { grid-template-columns: 1fr; }
            .order-summary { position: static; }
        }
        @media (max-width: 768px) {
            .checkout-form, .order-summary { padding: 20px; }
            .payment-methods { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <main>
        <section class="checkout">
            <h1>Checkout</h1>

            <?php if (!empty($order_error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($order_error); ?>
                </div>
            <?php endif; ?>

            <div class="checkout-container">
                <div class="checkout-form">
                    <h2><i class="fas fa-map-marker-alt"></i> Delivery Details</h2>

                    <div class="checkout-address">
                        <span class="addr-label"><?php echo htmlspecialchars($selectedAddress['label']); ?></span>
                        <a href="cart.php" class="change-addr-link"><i class="fas fa-exchange-alt"></i> Change</a>
                        <div class="addr-recipient"><?php echo htmlspecialchars($selectedAddress['recipient_name']); ?></div>
                        <div class="addr-line">
                            <?php echo htmlspecialchars($selectedAddress['address_line']); ?><br>
                            <?php echo htmlspecialchars($selectedAddress['county']); ?>
                            <?php if (!empty($selectedAddress['city'])): ?>, <?php echo htmlspecialchars($selectedAddress['city']); ?><?php endif; ?>
                        </div>
                        <div class="addr-phone"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($selectedAddress['phone']); ?></div>
                        <?php if (!empty($selectedAddress['delivery_instructions'])): ?>
                            <div class="addr-instructions"><i class="fas fa-comment-dots"></i> <?php echo htmlspecialchars($selectedAddress['delivery_instructions']); ?></div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" id="checkoutForm">
                        <input type="hidden" name="address_id" value="<?php echo (int)$selectedAddress['id']; ?>">

                        <div class="form-group">
                            <label>Payment Method <span style="color:#dc3545;">*</span></label>
                            <div class="payment-methods">
                                <label class="selected" onclick="selectPay(this)">
                                    <input type="radio" name="payment_method" value="pay_on_delivery" checked onchange="toggleMpesa()">
                                    <i class="fas fa-money-bill-wave" style="color:#28a745;"></i>
                                    <span class="pm-label">Pay on Delivery</span>
                                    <span class="pm-sub">Cash when delivered</span>
                                </label>
                                <label onclick="selectPay(this)">
                                    <input type="radio" name="payment_method" value="mpesa" onchange="toggleMpesa()">
                                    <i class="fas fa-mobile-alt" style="color:#25A349;"></i>
                                    <span class="pm-label">M-Pesa</span>
                                    <span class="pm-sub">STK push to phone</span>
                                </label>
                                <label onclick="selectPay(this)">
                                    <input type="radio" name="payment_method" value="paybill" onchange="toggleMpesa()">
                                    <i class="fas fa-receipt" style="color:#0056b3;"></i>
                                    <span class="pm-label">Paybill</span>
                                    <span class="pm-sub">Manual payment</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group" id="mpesaFields" style="display:none;">
                            <label>M-Pesa Phone Number <span style="color:#dc3545;">*</span></label>
                            <input type="tel" name="mpesa_phone" id="mpesaPhone" placeholder="07XX XXX XXX or +254 7XX XXX XXX">
                            <div class="mpesa-info">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    A payment prompt will be sent to this number. Enter your M-Pesa PIN to complete payment of
                                    <strong>Ksh <?php echo number_format($grandTotal, 0); ?></strong>.
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="place_order" class="btn-place-order" id="placeOrderBtn">
                            <i class="fas fa-check-circle"></i> Place Order — Ksh <?php echo number_format($grandTotal, 0); ?>
                        </button>
                    </form>
                </div>

                <div class="order-summary">
                    <h2><i class="fas fa-receipt"></i> Order Summary</h2>

                    <?php foreach ($cartItems as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo htmlspecialchars(getProductImage($item['image'] ?? null, $item['image_url'] ?? null)); ?>"
                                 alt="" onerror="this.src='uploads/products/no-image.png'">
                            <div class="order-item-details">
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <div class="item-price">Ksh <?php echo number_format($item['price'], 0); ?></div>
                                <div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                            </div>
                            <div style="font-weight:700; color:#05573c;">
                                Ksh <?php echo number_format($item['price'] * $item['quantity'], 0); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span>Ksh <?php echo number_format($total, 0); ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                            <div class="total-row discount">
                                <span><i class="fas fa-tag"></i> Discount (<?php echo htmlspecialchars($couponCode); ?>)</span>
                                <span>-Ksh <?php echo number_format($discount, 0); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="total-row">
                            <span>Transport (<?php echo htmlspecialchars($selectedAddress['county']); ?>)</span>
                            <span>Ksh <?php echo number_format($transportFee, 0); ?></span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total</span>
                            <span>Ksh <?php echo number_format($grandTotal, 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include "footer.php"; ?>

    <script>
        function selectPay(el) {
            document.querySelectorAll('.payment-methods label').forEach(l => l.classList.remove('selected'));
            el.classList.add('selected');
            const radio = el.querySelector('input[type=radio]');
            if (radio) {
                radio.checked = true;
                toggleMpesa();
            }
        }

        function toggleMpesa() {
            const m = document.querySelector('input[name="payment_method"]:checked').value;
            const fields = document.getElementById('mpesaFields');
            const phone = document.getElementById('mpesaPhone');
            if (m === 'mpesa' || m === 'paybill') {
                fields.style.display = 'block';
                if (phone && !phone.hasAttribute('required')) phone.setAttribute('required', 'required');
            } else {
                fields.style.display = 'none';
                if (phone) phone.removeAttribute('required');
            }
        }

        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('placeOrderBtn');
            const method = document.querySelector('input[name="payment_method"]:checked').value;
            const isMpesa = (method === 'mpesa' || method === 'paybill');

            btn.disabled = true;
            btn.innerHTML = isMpesa
                ? '<i class="fas fa-spinner fa-spin"></i> Sending M-Pesa prompt…'
                : '<i class="fas fa-spinner fa-spin"></i> Placing order…';
        });

        toggleMpesa();
    </script>
</body>
</html>
