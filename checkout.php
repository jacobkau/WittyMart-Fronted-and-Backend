<?php
// Include config first to start session and get database connection
require_once 'includes/config.php';
require_once 'includes/cloudinary_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header('Location: home.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user      = getCurrentUser();

// Init
$cartItems    = [];
$total        = 0;
$error        = '';
$order_error  = '';
$order_success = false;

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
    $cartItems = [];
    $error = 'Could not load cart items. Please try again.';
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

// Determine which address is selected
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

// Redirect to cart if no address at all
if (!$selectedAddress) {
    $_SESSION['flash_error'] = 'Please add a delivery address before checkout.';
    header('Location: cart.php');
    exit();
}

// ============================================
// TRANSPORT FEE (mirrors cart.php logic)
// ============================================
function countyTransportFee($county) {
    $nearby = ['Nairobi','Kiambu','Machakos','Kajiado',"Murang'a",'Nyeri','Kirinyaga','Embu','Nakuru'];
    $mid    = ['Mombasa','Kisumu','Uasin Gishu','Kakamega','Meru','Laikipia','Bungoma','Kisii','Nyamira','Kericho','Bomet','Narok'];
    if (in_array($county, $nearby, true)) return 100;
    if (in_array($county, $mid, true))    return 150;
    return 200;
}

$transportFee = countyTransportFee($selectedAddress['county']);
$vatRate      = 0.16;                    // 16% VAT (Kenya)
$vatAmount    = $total * $vatRate;
$grandTotal   = $total + $vatAmount + $transportFee;

// ============================================
// HANDLE ORDER SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $payment_method = sanitize($_POST['payment_method'] ?? '');
        $post_address_id = intval($_POST['address_id'] ?? 0);

        // Re-validate the address from POST belongs to this user
        $validAddress = null;
        foreach ($userAddresses as $a) {
            if ((int)$a['id'] === $post_address_id) { $validAddress = $a; break; }
        }

        if (!$validAddress) {
            $order_error = 'Please select a valid delivery address.';
        } elseif (empty($payment_method)) {
            $order_error = 'Please select a payment method.';
        } else {
            // ---------- Stock check with row locks ----------
            $pdo->beginTransaction();

            $stock_error = false;
            foreach ($cartItems as $item) {
                $lock = $pdo->prepare("SELECT stock, name FROM products WHERE id = ? FOR UPDATE");
                $lock->execute([$item['product_id']]);
                $row = $lock->fetch();
                if (!$row || $row['stock'] < $item['quantity']) {
                    $order_error = "Product '{$row['name']}' has insufficient stock. Available: " . ($row['stock'] ?? 0);
                    $stock_error = true;
                    break;
                }
            }

            if ($stock_error) {
                $pdo->rollBack();
            } else {
                // ---------- Regenerate transport + totals server-side ----------
                $transportFee = countyTransportFee($validAddress['county']);
                $order_total  = $total + $transportFee;
                $shipping_fee = $transportFee;

                // ---------- Generate unique order number (retry up to 5) ----------
                $order_number = null;
                for ($i = 0; $i < 5; $i++) {
                    $candidate = 'ORD-' . date('Ymd') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
                    $check = $pdo->prepare("SELECT 1 FROM orders WHERE order_number = ?");
                    $check->execute([$candidate]);
                    if (!$check->fetchColumn()) { $order_number = $candidate; break; }
                }
                if (!$order_number) {
                    throw new Exception('Could not generate a unique order number.');
                }

                $shipping_address = trim($validAddress['address_line'] . ', ' . $validAddress['county'] . (!empty($validAddress['city']) ? ', ' . $validAddress['city'] : ''));

                $stmt = $pdo->prepare("
                    INSERT INTO orders 
                    (user_id, order_number, total, shipping_fee, status,
                     payment_method, shipping_address, shipping_city,
                     delivery_instructions, delivery_county, delivery_phone,
                     delivery_recipient, address_id, created_at)
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $order_number,
                    $order_total,
                    $shipping_fee,
                    $payment_method,
                    $shipping_address,
                    $validAddress['city'] ?? '',
                    $validAddress['delivery_instructions'] ?? '',
                    $validAddress['county'],
                    $validAddress['phone'],
                    $validAddress['recipient_name'],
                    $validAddress['id']
                ]);
                $order_id = $pdo->lastInsertId();

                // ---------- Insert order items + decrement stock ----------
                $stmtItem = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

                foreach ($cartItems as $item) {
                    $item_total = $item['price'] * $item['quantity'];
                    $stmtItem->execute([
                        $order_id,
                        $item['product_id'],
                        $item['name'],
                        $item['quantity'],
                        $item['price'],
                        $item_total
                    ]);
                    $stmtStock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                    if ($stmtStock->rowCount() === 0) {
                        throw new Exception("Stock changed for '{$item['name']}', please try again.");
                    }
                }

                // ---------- Clear cart ----------
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

                $pdo->commit();

                logActivity(
                    'order_placed',
                    'Placed order #' . $order_number . ' with ' . count($cartItems) . ' items',
                    $user_id,
                    $user_name
                );

                // ---------- Cleanup session ----------
                unset($_SESSION['selected_address_id']);
                unset($_SESSION['delivery']);

                $_SESSION['order_success'] = true;
                $_SESSION['order_number']  = $order_number;
                header('Location: order_confirmation.php');
                exit();
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Checkout error: ' . $e->getMessage());
        $order_error = 'An error occurred while processing your order. Please try again.';
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
        .checkout-container {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 30px;
            margin: 20px 0;
        }
        .checkout-form, .order-summary {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .order-summary { position: sticky; top: 20px; align-self: start; }
        .checkout-form h2, .order-summary h2 {
            margin-top: 0; margin-bottom: 20px; color: #333;
            display: flex; align-items: center; gap: 8px;
        }
        .order-summary h2 {
            border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;
        }

        /* Address preview in checkout */
        .checkout-address {
            background: #f0faf5;
            border: 2px solid #05573c;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 20px;
            position: relative;
        }
        .checkout-address .addr-label {
            display: inline-block; font-size: 11px; font-weight: 700;
            padding: 2px 8px; border-radius: 10px;
            background: #05573c; color: #fff;
            margin-bottom: 8px; text-transform: uppercase;
        }
        .checkout-address .addr-recipient {
            font-weight: 700; color: #222; font-size: 15px; margin-bottom: 4px;
        }
        .checkout-address .addr-line {
            color: #444; font-size: 14px; line-height: 1.6;
        }
        .checkout-address .addr-phone {
            color: #666; font-size: 13px; margin-top: 4px;
        }
        .checkout-address .addr-instructions {
            font-size: 13px; color: #666; font-style: italic;
            margin-top: 8px; padding-top: 8px; border-top: 1px dashed #cfe6dd;
        }
        .checkout-address .change-addr-link {
            position: absolute; top: 14px; right: 16px;
            font-size: 13px; font-weight: 600;
            color: #05573c; text-decoration: none;
        }
        .checkout-address .change-addr-link:hover { text-decoration: underline; }

        /* Payment methods */
        .payment-methods {
            display: flex; gap: 15px; flex-wrap: wrap; margin-top: 8px;
        }
        .payment-methods label {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 18px; border: 2px solid #e0e0e0;
            border-radius: 6px; cursor: pointer;
            transition: all 0.3s ease; font-weight: 400;
        }
        .payment-methods label:hover { border-color: #05573c; }
        .payment-methods input[type="radio"] { width: auto; margin: 0; }
        .payment-methods label.selected { border-color: #05573c; background: #f0faf5; }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block; margin-bottom: 5px;
            font-weight: 600; color: #555;
        }

        .btn-place-order {
            width: 100%; padding: 14px;
            background: #05573c; color: #fff; border: none;
            border-radius: 6px; font-size: 18px; font-weight: 700;
            cursor: pointer; transition: all 0.3s ease; margin-top: 10px;
        }
        .btn-place-order:hover:not(:disabled) { background: #03402c; }
        .btn-place-order:disabled { opacity: 0.7; cursor: not-allowed; }

        /* Order items */
        .order-item {
            display: flex; gap: 15px; padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .order-item:last-child { border-bottom: none; }
        .order-item img {
            width: 60px; height: 60px; object-fit: cover;
            border-radius: 6px; background: #f5f5f5;
        }
        .order-item-details { flex: 1; }
        .order-item-details h4 {
            margin: 0 0 3px; font-size: 14px; color: #333;
        }
        .order-item-details .item-price {
            font-size: 13px; color: #05573c; font-weight: 600;
        }
        .order-item-details .item-quantity { font-size: 12px; color: #888; }

        .order-totals {
            margin-top: 20px; padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }
        .order-totals .total-row {
            display: flex; justify-content: space-between;
            padding: 8px 0; font-size: 15px; color: #555;
        }
        .order-totals .total-row.grand-total {
            font-size: 20px; font-weight: 700;
            color: #05573c;
            border-top: 2px solid #05573c;
            padding-top: 15px; margin-top: 5px;
        }

        .alert {
            padding: 15px 20px; border-radius: 6px; margin-bottom: 20px;
        }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media (max-width: 992px) {
            .checkout-container { grid-template-columns: 1fr; }
            .order-summary { position: static; }
        }
        @media (max-width: 768px) {
            .checkout-form, .order-summary { padding: 20px; }
            .payment-methods { flex-direction: column; }
            .checkout-address .change-addr-link { position: static; display: inline-block; margin-top: 6px; }
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
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($order_error); ?>
                </div>
            <?php endif; ?>

            <div class="checkout-container">
                <!-- Checkout Form -->
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
                            <div class="addr-instructions">
                                <i class="fas fa-comment-dots"></i>
                                <?php echo htmlspecialchars($selectedAddress['delivery_instructions']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" id="checkoutForm">
                        <input type="hidden" name="address_id" value="<?php echo (int)$selectedAddress['id']; ?>">

                        <div class="form-group">
                            <label>Payment Method <span style="color:#dc3545;">*</span></label>
                            <div class="payment-methods">
                                <label class="selected">
                                    <input type="radio" name="payment_method" value="mpesa" checked>
                                    <i class="fas fa-mobile-alt" style="color:#25A349;"></i> M-Pesa
                                </label>
                                <label>
                                    <input type="radio" name="payment_method" value="cash">
                                    <i class="fas fa-money-bill-wave" style="color:#28a745;"></i> Cash on Delivery
                                </label>
                                <label>
                                    <input type="radio" name="payment_method" value="card">
                                    <i class="fas fa-credit-card" style="color:#0056b3;"></i> Card Payment
                                </label>
                            </div>
                        </div>

                        <button type="submit" name="place_order" class="btn-place-order" id="placeOrderBtn">
                            <i class="fas fa-check-circle"></i> Place Order — Ksh <?php echo number_format($grandTotal, 0); ?>
                        </button>
                    </form>
                </div>

                <!-- Order Summary -->
                <div class="order-summary">
                    <h2><i class="fas fa-receipt"></i> Order Summary</h2>

                    <?php foreach ($cartItems as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo htmlspecialchars(getProductImage($item['image'] ?? null, $item['image_url'] ?? null)); ?>"
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 onerror="this.src='uploads/products/no-image.png'">
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
                        <div class="total-row">
                            <span>VAT (16%)</span>
                            <span>Ksh <?php echo number_format($vatAmount, 0); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Transport fee (<?php echo htmlspecialchars($selectedAddress['county']); ?>)</span>
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
        // Payment method selection styling
        document.querySelectorAll('.payment-methods label').forEach(label => {
            label.addEventListener('click', function() {
                document.querySelectorAll('.payment-methods label').forEach(l => l.classList.remove('selected'));
                this.classList.add('selected');
            });
            const radio = label.querySelector('input[type="radio"]');
            if (radio) {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.payment-methods label').forEach(l => l.classList.remove('selected'));
                    this.closest('label').classList.add('selected');
                });
            }
        });

        // Prevent double submission
        document.getElementById('checkoutForm').addEventListener('submit', function() {
            const btn = document.getElementById('placeOrderBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing order…';
        });
    </script>
</body>
</html>
