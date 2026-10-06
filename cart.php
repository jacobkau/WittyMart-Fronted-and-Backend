<?php
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// ============================================
// AJAX HANDLERS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_cart_count') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'count' => getCartCount()]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        switch ($_POST['ajax_action']) {

            case 'add_to_cart':
                $product_id = intval($_POST['product_id'] ?? 0);
                $quantity   = max(1, intval($_POST['quantity'] ?? 1));
                if (!$product_id) { $response = ['success'=>false,'message'=>'Invalid product']; break; }
                $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                if (!$product) { $response = ['success'=>false,'message'=>'Product not found']; break; }
                if ($product['stock'] <= 0) { $response = ['success'=>false,'message'=>'Out of stock']; break; }
                $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $stmt->execute([$existing['quantity'] + $quantity, $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $product_id, $quantity]);
                }
                $response = ['success'=>true, 'message'=>'Added to cart', 'cart_count'=>getCartCount()];
                break;

            case 'get_cart_count':
                $response = ['success'=>true, 'count'=>getCartCount()];
                break;

            case 'update_cart_quantity':
                $product_id = intval($_POST['product_id'] ?? 0);
                $quantity   = intval($_POST['quantity'] ?? 1);
                if (!$product_id) { $response = ['success'=>false,'message'=>'Invalid product']; break; }
                if ($quantity <= 0) {
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$user_id, $product_id]);
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$user_id, $product_id]);
                    if ($stmt->fetch()) {
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$quantity, $user_id, $product_id]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $product_id, $quantity]);
                    }
                }
                $response = ['success'=>true, 'cart_count'=>getCartCount()];
                break;

            case 'update_quantity':
                $cart_id  = intval($_POST['cart_id'] ?? 0);
                $quantity = intval($_POST['quantity'] ?? 1);
                if (!$cart_id) { $response = ['success'=>false,'message'=>'Invalid cart']; break; }
                if ($quantity <= 0) {
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                    $stmt->execute([$cart_id, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$quantity, $cart_id, $user_id]);
                }
                $response = ['success'=>true, 'cart_count'=>getCartCount()];
                break;

             case 'remove_item':
                $cart_id = intval($_POST['cart_id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $user_id]);
                // [PATCH:remove_item_log] Log the removal
                if (function_exists('logActivity')) {
                    logActivity('remove_from_cart', "Cart item {$cart_id}", $user_id, $_SESSION['user_name'] ?? null);
                }
                $response = ['success'=>true, 'cart_count'=>getCartCount()];
                break;

            case 'clear_cart':
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $response = ['success'=>true, 'cart_count'=>0];
                break;

            // ============================================
            // ADDRESS MANAGEMENT
            // ============================================
            case 'save_address':
                $addr_id        = intval($_POST['id'] ?? 0);
                $label          = sanitize($_POST['label'] ?? 'Home');
                $recipient_name = sanitize($_POST['recipient_name'] ?? '');
                $phone          = sanitize($_POST['phone'] ?? '');
                $county         = sanitize($_POST['county'] ?? '');
                $city           = sanitize($_POST['city'] ?? '');
                $address_line   = sanitize($_POST['address_line'] ?? '');
                $instructions   = sanitize($_POST['delivery_instructions'] ?? '');
                $is_default     = !empty($_POST['is_default']);

                if ($recipient_name === '' || $phone === '' || $county === '' || $address_line === '') {
                    $response = ['success'=>false, 'message'=>'Please fill in name, phone, county and address.'];
                    break;
                }

                if ($is_default) {
                    $pdo->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?")->execute([$user_id]);
                }

                if ($addr_id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE user_addresses 
                        SET label=?, recipient_name=?, phone=?, county=?, city=?, 
                            address_line=?, delivery_instructions=?, is_default=?, updated_at=NOW()
                        WHERE id=? AND user_id=?
                    ");
                    $stmt->execute([$label, $recipient_name, $phone, $county, $city,
                                    $address_line, $instructions, $is_default ? 1 : 0, $addr_id, $user_id]);
                    $saved_id = $addr_id;
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_addresses
                        (user_id, label, recipient_name, phone, county, city, address_line, delivery_instructions, is_default)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        RETURNING id
                    ");
                    $stmt->execute([$user_id, $label, $recipient_name, $phone, $county, $city,
                                    $address_line, $instructions, $is_default ? 1 : 0]);
                    $saved_id = $stmt->fetchColumn();
                }

                $check = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND is_default = TRUE");
                $check->execute([$user_id]);
                if ($check->fetchColumn() == 0) {
                    $pdo->prepare("UPDATE user_addresses SET is_default = TRUE WHERE id = ?")->execute([$saved_id]);
                }

                $response = ['success'=>true, 'message'=>'Address saved', 'id'=>$saved_id];
                break;

            case 'get_address':
                $addr_id = intval($_POST['id'] ?? 0);
                if (!$addr_id) { $response = ['success'=>false]; break; }
                $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$addr_id, $user_id]);
                $addr = $stmt->fetch(PDO::FETCH_ASSOC);
                $response = $addr ? ['success'=>true, 'address'=>$addr] : ['success'=>false];
                break;

            case 'delete_address':
                $addr_id = intval($_POST['id'] ?? 0);
                if (!$addr_id) { $response = ['success'=>false,'message'=>'Invalid address']; break; }
                $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$addr_id, $user_id]);
                $check = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND is_default = TRUE");
                $check->execute([$user_id]);
                if ($check->fetchColumn() == 0) {
                    $pdo->prepare("
                        UPDATE user_addresses SET is_default = TRUE 
                        WHERE id = (SELECT id FROM user_addresses WHERE user_id = ? ORDER BY created_at LIMIT 1)
                    ")->execute([$user_id]);
                }
                $response = ['success'=>true, 'message'=>'Address deleted'];
                break;

            case 'set_default_address':
                $addr_id = intval($_POST['id'] ?? 0);
                $pdo->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?")->execute([$user_id]);
                $pdo->prepare("UPDATE user_addresses SET is_default = TRUE WHERE id = ? AND user_id = ?")->execute([$addr_id, $user_id]);
                $response = ['success'=>true];
                break;

            case 'set_selected_address':
                $addr_id = $_POST['id'] ?? 0;
                $_SESSION['selected_address_id'] = ($addr_id === 'pickup') ? 'pickup' : intval($addr_id);
                $response = ['success'=>true];
                break;

            // ============================================
            // COUPON
            // ============================================
            case 'apply_coupon':
                $code = strtoupper(sanitize($_POST['code'] ?? ''));
                if (!$code) { $response = ['success'=>false,'message'=>'Enter a coupon code']; break; }

                $stmt = $pdo->prepare("
                    SELECT * FROM coupons 
                    WHERE code = ? AND status = 'active' 
                    AND (valid_until IS NULL OR valid_until > NOW())
                    AND (max_uses IS NULL OR uses < max_uses)
                ");
                $stmt->execute([$code]);
                $coupon = $stmt->fetch();

                if (!$coupon) { $response = ['success'=>false,'message'=>'Invalid or expired coupon']; break; }
                if ($total < $coupon['min_order']) {
                    $response = ['success'=>false,'message'=>'Minimum order Ksh '.number_format($coupon['min_order'])];
                    break;
                }

                $discount = $coupon['discount_type'] === 'percent'
                    ? $total * ($coupon['discount_value'] / 100)
                    : min($coupon['discount_value'], $total);

                $_SESSION['coupon'] = ['code' => $code, 'discount' => $discount, 'id' => $coupon['id']];
                $response = ['success'=>true, 'discount'=>$discount, 'code'=>$code];
                break;

            case 'remove_coupon':
                unset($_SESSION['coupon']);
                $response = ['success'=>true];
                break;

            default:
                $response = ['success'=>false, 'message'=>'Unknown action'];
        }
    } catch (PDOException $e) {
        error_log('Cart AJAX error: ' . $e->getMessage());
        $response = ['success'=>false, 'message'=>'Database error: '.$e->getMessage()];
    }
    echo json_encode($response);
    exit();
}

// ============================================
// HELPERS
// ============================================
function getCartCount() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return intval($stmt->fetchColumn() ?? 0);
    } catch (PDOException $e) { return 0; }
}

function getCartProductImage($product) {
    if (!is_array($product)) return UPLOAD_URL . 'no-image.png';
    if (!empty($product['image_url'])) return $product['image_url'];
    if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])) return UPLOAD_URL . $product['image'];
    return UPLOAD_URL . 'no-image.png';
}

function countyTransportFee($county) {
    if ($county === '__PICKUP__') return 0; // Office pickup = free
    $nearby = ['Nairobi','Kiambu','Machakos','Kajiado',"Murang'a",'Nyeri','Kirinyaga','Embu','Nakuru'];
    $mid    = ['Mombasa','Kisumu','Uasin Gishu','Kakamega','Meru','Laikipia','Bungoma','Kisii','Nyamira','Kericho','Bomet','Narok'];
    if (in_array($county, $nearby, true)) return 100;
    if (in_array($county, $mid, true))    return 150;
    return 200;
}

// ============================================
// FETCH DATA
// ============================================
$cartItems = [];
$total = 0;
try {
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, c.product_id, c.quantity,
               p.name, p.price, p.image, p.image_url, p.description, p.stock
        FROM cart c
        INNER JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $cartItems = $stmt->fetchAll();
    foreach ($cartItems as $item) $total += $item['price'] * $item['quantity'];
} catch (PDOException $e) { error_log('Cart error: '.$e->getMessage()); }

// Coupon discount
$couponCode = '';
$discount = 0;
if (!empty($_SESSION['coupon'])) {
    $couponCode = $_SESSION['coupon']['code'];
    $discount   = floatval($_SESSION['coupon']['discount']);
}
$totalAfterDiscount = max(0, $total - $discount);

// Addresses
$userAddresses = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_addresses 
        WHERE user_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$user_id]);
    $userAddresses = $stmt->fetchAll();
} catch (PDOException $e) { error_log('Addresses load: '.$e->getMessage()); }

$selectedAddressId = $_SESSION['selected_address_id'] ?? 0;
$selectedAddress = null;
$isPickup = ($selectedAddressId === 'pickup');

if (!$isPickup && $selectedAddressId) {
    foreach ($userAddresses as $a) if ((int)$a['id'] === (int)$selectedAddressId) { $selectedAddress = $a; break; }
}
if (!$isPickup && !$selectedAddress && !empty($userAddresses)) {
    foreach ($userAddresses as $a) if ($a['is_default']) { $selectedAddress = $a; break; }
    if (!$selectedAddress) $selectedAddress = $userAddresses[0];
    $selectedAddressId = (int)$selectedAddress['id'];
    $_SESSION['selected_address_id'] = $selectedAddressId;
}

$transportFee = $isPickup ? 0 : ($selectedAddress ? countyTransportFee($selectedAddress['county']) : 0);
$grandTotal   = $totalAfterDiscount + $transportFee;

$kenya_counties = [
    'Baringo','Bomet','Bungoma','Busia','Elgeyo-Marakwet','Embu','Garissa',
    'Homa Bay','Isiolo','Kajiado','Kakamega','Kericho','Kiambu','Kilifi',
    'Kirinyaga','Kisii','Kisumu','Kitui','Kwale','Laikipia','Lamu','Machakos',
    'Makueni','Mandera','Marsabit','Meru','Migori','Mombasa',"Murang'a",
    'Nairobi','Nakuru','Nandi','Narok','Nyamira','Nyandarua','Nyeri','Samburu',
    'Siaya','Taita-Taveta','Tana River','Tharaka-Nithi','Trans Nzoia','Turkana',
    'Uasin Gishu','Vihiga','Wajir','West Pokot'
];

$page_title = 'Cart';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cart - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-items { display:flex; flex-direction:column; gap:15px; margin:20px 0; }
        .cart-item { display:flex; align-items:center; gap:20px; background:#fff; padding:15px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.08); position:relative; }
        .cart-item .image-container { position:relative; width:100px; height:100px; flex-shrink:0; border-radius:8px; overflow:hidden; background:#f5f5f5; cursor:pointer; }
        .cart-item .image-container img { width:100%; height:100%; object-fit:cover; transition:transform .3s; }
        .cart-item .image-container:hover img { transform:scale(1.06); }
        .cart-item .image-container .cloudinary-badge { position:absolute; top:4px; right:4px; background:rgba(52,72,197,.9); color:#fff; font-size:8px; padding:2px 6px; border-radius:8px; font-weight:600; }
        .cart-item .image-container .view-overlay { position:absolute; inset:0; background:rgba(5,87,60,.75); color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; opacity:0; transition:opacity .25s; }
        .cart-item .image-container:hover .view-overlay { opacity:1; }
        .cart-item-details { flex:1; min-width:0; }
        .cart-item-details h3 { margin:0 0 5px; font-size:16px; color:#333; }
        .cart-item-details h3 a { color:inherit; text-decoration:none; }
        .cart-item-details h3 a:hover { color:#05573c; }
        .cart-item-details p { margin:0; font-size:13px; color:#666; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .cart-item-details .view-details-link { display:inline-block; margin-top:6px; font-size:12px; color:#05573c; font-weight:600; text-decoration:none; }
        .cart-item-price { font-size:18px; font-weight:700; color:#05573c; min-width:100px; text-align:center; }
        .cart-item-actions { display:flex; align-items:center; gap:8px; }
        .cart-item-actions button { background:#f0f0f0; border:none; padding:5px 12px; border-radius:4px; cursor:pointer; font-size:16px; font-weight:600; color:#333; }
        .cart-item-actions button:hover:not(.remove-btn) { background:#05573c; color:#fff; }
        .cart-item-actions .quantity { min-width:30px; text-align:center; font-weight:600; font-size:16px; }
        .cart-item-actions .remove-btn { background:#dc3545; color:#fff; padding:5px 12px; font-size:12px; }
        .cart-item-actions .remove-btn:hover { background:#c82333; }

        .delivery-panel { background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:24px; margin-top:20px; }
        .delivery-panel h2 { margin:0 0 16px; font-size:18px; color:#333; display:flex; align-items:center; gap:8px; }
        .delivery-panel h2 .add-addr-link { margin-left:auto; font-size:13px; font-weight:600; color:#05573c; cursor:pointer; text-decoration:none; }
        .address-list { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; margin-bottom:16px; }
        .address-card { border:2px solid #e0e0e0; border-radius:10px; padding:14px; background:#fafafa; cursor:pointer; transition:all .2s; position:relative; }
        .address-card:hover { border-color:#0a7a54; background:#fff; }
        .address-card.selected { border-color:#05573c; background:#f0faf5; }
        .address-card .addr-label { display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; background:#e8f5f0; color:#05573c; margin-bottom:6px; text-transform:uppercase; }
        .address-card.selected .addr-label { background:#05573c; color:#fff; }
        .address-card .addr-default-badge { display:inline-block; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; background:#ffc107; color:#333; margin-left:6px; }
        .address-card .addr-recipient { font-weight:700; color:#222; font-size:14px; }
        .address-card .addr-line { color:#555; font-size:13px; margin-top:4px; line-height:1.5; }
        .address-card .addr-phone { color:#888; font-size:12px; margin-top:4px; }
        .address-card .addr-actions { display:flex; gap:6px; margin-top:10px; flex-wrap:wrap; }
        .address-card .addr-actions button { background:#fff; border:1px solid #ddd; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; color:#555; }
        .address-card .addr-actions button:hover { border-color:#05573c; color:#05573c; }
        .address-card .addr-actions .btn-set-default { color:#05573c; border-color:#05573c; }
        .address-card .addr-actions .btn-delete { color:#dc3545; border-color:#dc3545; }
        .address-card .addr-actions .btn-delete:hover { background:#dc3545; color:#fff; }
        .address-card.pickup-card { border-style:dashed; }
        .address-card.pickup-card.selected { border-style:solid; }
        .no-addresses { text-align:center; padding:30px 20px; background:#fafafa; border-radius:10px; color:#888; margin-bottom:16px; }
        .no-addresses i { font-size:40px; opacity:.3; display:block; margin-bottom:12px; }
        .no-addresses .btn-add-first { display:inline-block; margin-top:12px; padding:10px 22px; background:#05573c; color:#fff; border-radius:6px; border:none; cursor:pointer; font-weight:600; text-decoration:none; }
        .delivery-fee-note { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#f0faf5; border-radius:8px; border-left:4px solid #05573c; font-size:14px; color:#333; }
        .delivery-fee-note strong { color:#05573c; font-size:16px; }

        .cart-summary { background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:24px; margin-top:20px; }
        .summary-rows { margin-bottom:18px; }
        .summary-row { display:flex; justify-content:space-between; padding:8px 0; font-size:15px; color:#555; border-bottom:1px solid #f0f0f0; }
        .summary-row:last-child { border-bottom:none; }
        .summary-row.total-row { font-size:20px; font-weight:700; color:#05573c; padding-top:14px; margin-top:6px; border-top:2px solid #05573c; border-bottom:none; }
        .summary-row.discount-row { color:#28a745; font-weight:600; }

        .coupon-box { display:flex; gap:8px; margin-bottom:16px; }
        .coupon-box input { flex:1; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; text-transform:uppercase; }
        .coupon-box input:focus { outline:none; border-color:#05573c; }
        .coupon-box button { padding:10px 20px; background:#05573c; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
        .coupon-box button:hover { background:#03402c; }
        .coupon-box button.remove { background:#dc3545; }
        .coupon-applied { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:#d4edda; color:#155724; border-radius:8px; font-size:14px; margin-bottom:16px; }
        .coupon-applied .code { font-weight:700; letter-spacing:1px; }
        .coupon-applied .x { background:none; border:none; color:#155724; font-size:18px; cursor:pointer; padding:0 6px; }

        .summary-actions { display:flex; gap:12px; flex-wrap:wrap; justify-content:flex-end; }
        .checkout-btn { background:#05573c; color:#fff; border:none; padding:12px 30px; border-radius:6px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
        .checkout-btn:hover { background:#03402c; }
        .clear-cart-btn { background:#dc3545; color:#fff; border:none; padding:12px 20px; border-radius:6px; cursor:pointer; font-weight:600; }
        .clear-cart-btn:hover { background:#c82333; }

        .empty-cart { text-align:center; padding:60px 20px; }
        .empty-cart i { font-size:60px; color:#ccc; margin-bottom:20px; display:block; }
        .empty-cart p { font-size:18px; color:#888; }
        .empty-cart .btn-primary { display:inline-block; margin-top:15px; padding:10px 30px; background:#05573c; color:#fff; border-radius:6px; text-decoration:none; font-weight:600; }

        .addr-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99999; justify-content:center; align-items:center; padding:20px; }
        .addr-modal-overlay.active { display:flex; }
        .addr-modal { background:#fff; border-radius:12px; max-width:560px; width:100%; max-height:90vh; overflow-y:auto; padding:26px; position:relative; }
        .addr-modal h3 { margin:0 0 18px; font-size:20px; color:#222; }
        .addr-modal .close-x { position:absolute; top:14px; right:16px; background:none; border:none; font-size:22px; cursor:pointer; color:#888; }
        .addr-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .addr-form-group { margin-bottom:12px; }
        .addr-form-group label { display:block; font-weight:600; font-size:13px; color:#555; margin-bottom:6px; }
        .addr-form-group label .required { color:#dc3545; }
        .addr-form-group input, .addr-form-group select, .addr-form-group textarea { width:100%; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; font-family:inherit; }
        .addr-form-group textarea { resize:vertical; min-height:70px; }
        .addr-form-group input:focus, .addr-form-group select:focus, .addr-form-group textarea:focus { outline:none; border-color:#05573c; }
        .addr-form-group .checkbox-row { display:flex; align-items:center; gap:8px; font-size:14px; }
        .addr-form-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:16px; padding-top:16px; border-top:1px solid #f0f0f0; }
        .addr-form-actions .btn-cancel { background:#f0f0f0; color:#333; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600; }
        .addr-form-actions .btn-save { background:#05573c; color:#fff; border:none; padding:10px 24px; border-radius:6px; cursor:pointer; font-weight:600; }
        .addr-error { background:#f8d7da; color:#721c24; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:12px; }

        .qv-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99999; justify-content:center; align-items:center; padding:20px; }
        .qv-overlay.active { display:flex; }
        .qv-modal { background:#fff; border-radius:12px; max-width:900px; width:100%; max-height:90vh; overflow-y:auto; position:relative; display:grid; grid-template-columns:1fr 1fr; }
        .qv-close { position:absolute; top:12px; right:12px; background:rgba(0,0,0,.5); color:#fff; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:18px; z-index:3; }
        .qv-image-wrap { position:relative; background:#f5f5f5; min-height:340px; border-radius:12px 0 0 12px; overflow:hidden; }
        .qv-image-wrap img { width:100%; height:100%; object-fit:cover; }
        .qv-nav { position:absolute; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.9); color:#333; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:15px; z-index:2; }
        .qv-nav.prev { left:10px; } .qv-nav.next { right:10px; }
        .qv-counter { position:absolute; top:12px; left:12px; background:rgba(0,0,0,.7); color:#fff; font-size:11px; padding:4px 10px; border-radius:12px; font-weight:600; z-index:2; }
        .qv-thumbs { position:absolute; bottom:8px; left:8px; right:8px; display:flex; gap:6px; overflow-x:auto; padding:4px; z-index:2; }
        .qv-thumb { flex:0 0 48px; width:48px; height:48px; border-radius:6px; overflow:hidden; border:2px solid transparent; cursor:pointer; background:#fff; }
        .qv-thumb img { width:100%; height:100%; object-fit:cover; }
        .qv-thumb.active { border-color:#05573c; }
        .qv-info { padding:30px; display:flex; flex-direction:column; gap:12px; }
        .qv-info h2 { margin:0; font-size:22px; color:#222; }
        .qv-price { font-size:26px; font-weight:700; color:#05573c; }
        .qv-meta { display:flex; flex-wrap:wrap; gap:8px; }
        .qv-badge { background:#f0f0f0; color:#555; padding:4px 12px; border-radius:12px; font-size:12px; }
        .qv-badge.in-stock { background:#d4edda; color:#155724; }
        .qv-badge.out-of-stock { background:#f8d7da; color:#721c24; }
        .qv-description { background:#f8f9fa; padding:14px; border-radius:8px; font-size:14px; line-height:1.7; color:#444; max-height:220px; overflow-y:auto; }
        .qv-actions { display:flex; gap:10px; margin-top:auto; padding-top:12px; }
        .qv-btn-primary { flex:1; background:#05573c; color:#fff; border:none; padding:12px 20px; border-radius:6px; font-weight:600; cursor:pointer; text-decoration:none; text-align:center; }
        .qv-btn-secondary { background:#f0f0f0; color:#333; border:none; padding:12px 20px; border-radius:6px; font-weight:600; cursor:pointer; }

        @media (max-width:768px) {
            .cart-item { flex-wrap:wrap; gap:12px; }
            .cart-item .image-container { width:80px; height:80px; }
            .cart-item-price { min-width:auto; text-align:left; flex:1; }
            .cart-item-actions { width:100%; justify-content:flex-start; }
            .addr-form-row { grid-template-columns:1fr; }
            .summary-actions { justify-content:stretch; }
            .checkout-btn, .clear-cart-btn { width:100%; justify-content:center; }
            .qv-modal { grid-template-columns:1fr; }
            .qv-image-wrap { min-height:240px; border-radius:12px 12px 0 0; }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <main>
        <section class="cart">
            <h1>Your <span>Shopping Cart</span></h1>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-error" style="background:#f8d7da; color:#721c24; padding:15px 20px; border-radius:6px; margin-bottom:20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Your cart is empty</p>
                    <a href="breadcrumbs.php" class="btn-primary">Start Shopping</a>
                </div>
            <?php else: ?>
                <div class="cart-items" id="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item" data-cart-id="<?php echo $item['cart_id']; ?>">
                            <div class="image-container" onclick="openQuickView(<?php echo (int)$item['product_id']; ?>)" title="Click to view details">
                                <img src="<?php echo htmlspecialchars(getCartProductImage($item)); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" onerror="this.src='uploads/products/no-image.png'">
                                <span class="view-overlay"><i class="fas fa-eye"></i></span>
                                <?php if (!empty($item['image_url'])): ?><span class="cloudinary-badge"><i class="fas fa-cloud"></i></span><?php endif; ?>
                            </div>
                            <div class="cart-item-details">
                                <h3><a href="product.php?id=<?php echo (int)$item['product_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                                <p><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 90)); ?>…</p>
                                <a href="#" class="view-details-link" onclick="event.preventDefault(); openQuickView(<?php echo (int)$item['product_id']; ?>)">
                                    <i class="fas fa-eye"></i> Quick view
                                </a>
                            </div>
                            <div class="cart-item-price" data-price="<?php echo $item['price']; ?>">Ksh <?php echo number_format($item['price'], 0); ?></div>
                            <div class="cart-item-actions">
                                <button onclick="updateQuantity(this, <?php echo $item['cart_id']; ?>, -1)">-</button>
                                <span class="quantity" id="qty-<?php echo $item['cart_id']; ?>"><?php echo $item['quantity']; ?></span>
                                <button onclick="updateQuantity(this, <?php echo $item['cart_id']; ?>, 1)">+</button>
                                <button class="remove-btn" onclick="removeItem(<?php echo $item['cart_id']; ?>)">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- DELIVERY ADDRESSES -->
                <div class="delivery-panel">
                    <h2>
                        <i class="fas fa-map-marker-alt"></i> Delivery Address
                        <a class="add-addr-link" onclick="openAddressModal(0)"><i class="fas fa-plus"></i> Add New Address</a>
                    </h2>

                    <div class="address-list" id="addressList">
                        <!-- OFFICE PICKUP CARD (always first, transport = 0) -->
                        <div class="address-card pickup-card <?php echo $isPickup ? 'selected' : ''; ?>"
                             data-id="pickup"
                             data-county="__PICKUP__"
                             onclick="selectAddress('pickup', this)">
                            <div>
                                <span class="addr-label">Pickup</span>
                                <span class="addr-default-badge" style="background:#05573c;color:#fff;">FREE</span>
                            </div>
                            <div class="addr-recipient"><i class="fas fa-store"></i> WittyMart Office Pickup</div>
                            <div class="addr-line">
                                WittyMart Headquarters<br>
                                Nairobi CBD, Kenya
                            </div>
                            <div class="addr-phone"><i class="fas fa-phone"></i> +254 700 000 000</div>
                            <div class="addr-actions" onclick="event.stopPropagation();">
                                <span style="font-size:11px;color:#05573c;font-weight:700;"><i class="fas fa-check-circle"></i> No transport fee</span>
                            </div>
                        </div>

                        <?php foreach ($userAddresses as $addr): ?>
                            <?php $isSelected = ((int)$addr['id'] === (int)$selectedAddressId) && !$isPickup; ?>
                            <div class="address-card <?php echo $isSelected ? 'selected' : ''; ?>"
                                 data-id="<?php echo (int)$addr['id']; ?>"
                                 data-county="<?php echo htmlspecialchars($addr['county']); ?>"
                                 onclick="selectAddress(<?php echo (int)$addr['id']; ?>, this)">
                                <div>
                                    <span class="addr-label"><?php echo htmlspecialchars($addr['label']); ?></span>
                                    <?php if ($addr['is_default']): ?><span class="addr-default-badge">DEFAULT</span><?php endif; ?>
                                </div>
                                <div class="addr-recipient"><?php echo htmlspecialchars($addr['recipient_name']); ?></div>
                                <div class="addr-line">
                                    <?php echo htmlspecialchars($addr['address_line']); ?><br>
                                    <?php echo htmlspecialchars($addr['county']); ?><?php if (!empty($addr['city'])): ?>, <?php echo htmlspecialchars($addr['city']); ?><?php endif; ?>
                                </div>
                                <div class="addr-phone"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($addr['phone']); ?></div>
                                <div class="addr-actions" onclick="event.stopPropagation();">
                                    <button onclick="openAddressModal(<?php echo (int)$addr['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <?php if (!$addr['is_default']): ?>
                                        <button class="btn-set-default" onclick="setDefaultAddress(<?php echo (int)$addr['id']; ?>)"><i class="fas fa-star"></i> Set Default</button>
                                    <?php endif; ?>
                                    <button class="btn-delete" onclick="deleteAddress(<?php echo (int)$addr['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($isPickup): ?>
                        <div class="delivery-fee-note" style="border-left-color:#28a745; background:#d4edda;">
                            <span><i class="fas fa-store"></i> <strong>Office Pickup</strong> — collect at WittyMart HQ, Nairobi CBD</span>
                            <strong style="color:#28a745;">FREE</strong>
                        </div>
                    <?php elseif ($selectedAddress): ?>
                        <div class="delivery-fee-note">
                            <span><i class="fas fa-info-circle"></i> Transport to <strong><?php echo htmlspecialchars($selectedAddress['county']); ?></strong>:</span>
                            <strong>Ksh <span id="transportFee"><?php echo number_format($transportFee, 0); ?></span></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- COUPON -->
                <div class="cart-summary" style="margin-top:20px;">
                    <h2 style="margin:0 0 16px; font-size:16px; color:#333;"><i class="fas fa-tag"></i> Coupon Code</h2>
                    
                    <?php if ($couponCode): ?>
                        <div class="coupon-applied">
                            <span><i class="fas fa-check-circle"></i> Coupon <span class="code"><?php echo htmlspecialchars($couponCode); ?></span> applied (-Ksh <?php echo number_format($discount, 0); ?>)</span>
                            <button class="x" onclick="removeCoupon()" title="Remove">×</button>
                        </div>
                    <?php else: ?>
                        <div class="coupon-box">
                            <input type="text" id="couponInput" placeholder="Enter coupon code" maxlength="30">
                            <button onclick="applyCoupon()">Apply</button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SUMMARY -->
                <div class="cart-summary">
                    <div class="summary-rows">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>Ksh <span id="cart-total"><?php echo number_format($total, 0); ?></span></span>
                        </div>
                        <?php if ($discount > 0): ?>
                        <div class="summary-row discount-row">
                            <span><i class="fas fa-tag"></i> Discount (<?php echo htmlspecialchars($couponCode); ?>)</span>
                            <span>-Ksh <span id="cart-discount"><?php echo number_format($discount, 0); ?></span></span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-row">
                            <span>Transport fee</span>
                            <span>Ksh <span id="cart-transport"><?php echo number_format($transportFee, 0); ?></span></span>
                        </div>
                        <div class="summary-row total-row">
                            <span>Total</span>
                            <span>Ksh <span id="cart-grand-total"><?php echo number_format($grandTotal, 0); ?></span></span>
                        </div>
                    </div>

                    <div class="summary-actions">
                        <button class="checkout-btn" onclick="checkout()"><i class="fas fa-credit-card"></i> Proceed to Checkout</button>
                        <button class="clear-cart-btn" onclick="clearCart()"><i class="fas fa-trash"></i> Clear Cart</button>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- ADDRESS MODAL -->
    <div class="addr-modal-overlay" id="addrModal" onclick="if(event.target===this) closeAddressModal()">
        <div class="addr-modal">
            <button class="close-x" onclick="closeAddressModal()">&times;</button>
            <h3 id="addrModalTitle"><i class="fas fa-map-marker-alt"></i> Add Address</h3>
            <div class="addr-error" id="addrError" style="display:none;"></div>
            <form id="addrForm" onsubmit="return false;">
                <input type="hidden" id="addrId" value="0">
                <div class="addr-form-row">
                    <div class="addr-form-group">
                        <label>Label</label>
                        <select id="addrLabel">
                            <option value="Home">Home</option>
                            <option value="Work">Work</option>
                            <option value="Office">Office</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="addr-form-group">
                        <label>Recipient Name <span class="required">*</span></label>
                        <input type="text" id="addrRecipient" required>
                    </div>
                </div>
                <div class="addr-form-row">
                    <div class="addr-form-group">
                        <label>Phone <span class="required">*</span></label>
                        <input type="tel" id="addrPhone" required>
                    </div>
                    <div class="addr-form-group">
                        <label>County <span class="required">*</span></label>
                        <select id="addrCounty" required>
                            <option value="">— Select County —</option>
                            <?php foreach ($kenya_counties as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="addr-form-group">
                    <label>City / Town</label>
                    <input type="text" id="addrCity">
                </div>
                <div class="addr-form-group">
                    <label>Address Line <span class="required">*</span></label>
                    <textarea id="addrLine" required></textarea>
                </div>
                <div class="addr-form-group">
                    <label>Delivery Instructions</label>
                    <textarea id="addrInstructions"></textarea>
                </div>
                <div class="addr-form-group">
                    <label class="checkbox-row"><input type="checkbox" id="addrDefault"> Set as my default address</label>
                </div>
                <div class="addr-form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddressModal()">Cancel</button>
                    <button type="button" class="btn-save" id="addrSaveBtn" onclick="saveAddress()"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- QUICK VIEW -->
    <div class="qv-overlay" id="qvOverlay" onclick="if(event.target===this) closeQuickView()">
        <div class="qv-modal">
            <button class="qv-close" onclick="closeQuickView()">&times;</button>
            <div class="qv-image-wrap">
                <img src="" alt="" id="qvImage">
                <span class="qv-counter"><i class="fas fa-images"></i> <span id="qvCounter">1</span> / <span id="qvTotal">1</span></span>
                <button class="qv-nav prev" onclick="qvPrev()"><i class="fas fa-chevron-left"></i></button>
                <button class="qv-nav next" onclick="qvNext()"><i class="fas fa-chevron-right"></i></button>
                <div class="qv-thumbs" id="qvThumbs"></div>
            </div>
            <div class="qv-info">
                <h2 id="qvName">Product</h2>
                <div class="qv-price" id="qvPrice">Ksh 0</div>
                <div class="qv-meta" id="qvMeta"></div>
                <div class="qv-description" id="qvDescription"></div>
                <div class="qv-actions">
                    <a href="#" id="qvFullLink" class="qv-btn-primary"><i class="fas fa-external-link-alt"></i> View Full Details</a>
                    <button class="qv-btn-secondary" onclick="closeQuickView()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include "footer.php"; ?>

    <script>
        function computeTransportFee(county) {
            if (!county) return 0;
            if (county === '__PICKUP__') return 0; // Office pickup = free
            const nearby = ['Nairobi','Kiambu','Machakos','Kajiado',"Murang'a",'Nyeri','Kirinyaga','Embu','Nakuru'];
            const mid    = ['Mombasa','Kisumu','Uasin Gishu','Kakamega','Meru','Laikipia','Bungoma','Kisii','Nyamira','Kericho','Bomet','Narok'];
            if (nearby.includes(county)) return 100;
            if (mid.includes(county))    return 150;
            return 200;
        }
        let subtotal         = <?php echo (float)$total; ?>;
        let currentDiscount  = <?php echo (float)$discount; ?>;
        let currentTransport = <?php echo (float)$transportFee; ?>;

        function refreshTotals() {
            document.getElementById('cart-total').textContent = subtotal.toLocaleString();
            const afterDisc = Math.max(0, subtotal - currentDiscount);
            const tEl = document.getElementById('cart-transport');
            if (tEl) tEl.textContent = currentTransport.toLocaleString();
            document.getElementById('cart-grand-total').textContent = (afterDisc + currentTransport).toLocaleString();
            const dEl = document.getElementById('cart-discount');
            if (dEl) dEl.textContent = currentDiscount.toLocaleString();
        }

        // ============================================
        // CART OPERATIONS
        // ============================================
        function refreshCartCount() {
            fetch('cart.php?action=get_cart_count').then(r => r.json()).then(data => {
                if (!data.success) return;
                const c = data.count;
                const b = document.getElementById('cartBadge');
                if (b) { b.textContent = c || ''; b.classList.toggle('empty', c === 0); }
                const hb = document.getElementById('headerCartBadge');
                if (hb) { hb.textContent = c || ''; hb.classList.toggle('empty', c === 0); }
            }).catch(() => {});
        }

        function updateQuantity(button, cartId, change) {
            const span = document.getElementById('qty-' + cartId);
            const cur = parseInt(span.textContent);
            let nxt = cur + change;
            if (nxt < 1) nxt = 1;
            span.textContent = nxt;
            const fd = new FormData();
            fd.append('ajax_action', 'update_quantity');
            fd.append('cart_id', cartId);
            fd.append('quantity', nxt);
            fetch('cart.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { recalcSubtotal(); refreshCartCount(); }
                    else { span.textContent = cur; alert('Failed.'); }
                }).catch(() => { span.textContent = cur; });
        }

        function removeItem(cartId) {
            if (!confirm('Remove this item?')) return;
            const fd = new FormData();
            fd.append('ajax_action', 'remove_item');
            fd.append('cart_id', cartId);
            fetch('cart.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                if (data.success) {
                    const el = document.querySelector(`.cart-item[data-cart-id="${cartId}"]`);
                    if (el) {
                        el.style.opacity = '0';
                        el.style.transition = 'opacity 0.3s';
                        setTimeout(() => {
                            el.remove();
                            recalcSubtotal();
                            refreshCartCount();
                            if (!document.querySelector('.cart-item')) location.reload();
                        }, 300);
                    }
                }
            });
        }

        function clearCart() {
            if (!confirm('Clear entire cart?')) return;
            const fd = new FormData();
            fd.append('ajax_action', 'clear_cart');
            fetch('cart.php', { method: 'POST', body: fd }).then(() => { refreshCartCount(); location.reload(); });
        }

        function recalcSubtotal() {
            let sum = 0;
            document.querySelectorAll('.cart-item').forEach(item => {
                const price = parseFloat(item.querySelector('.cart-item-price').dataset.price || '0');
                const qty = parseInt(item.querySelector('.quantity').textContent);
                sum += price * qty;
            });
            subtotal = sum;
            refreshTotals();
        }

        // ============================================
        // COUPON
        // ============================================
        function applyCoupon() {
            const code = document.getElementById('couponInput').value.trim();
            if (!code) return;
            const fd = new FormData();
            fd.append('ajax_action', 'apply_coupon');
            fd.append('code', code);
            fetch('cart.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.success) { location.reload(); }
                else alert(res.message || 'Invalid coupon');
            });
        }

        function removeCoupon() {
            const fd = new FormData();
            fd.append('ajax_action', 'remove_coupon');
            fetch('cart.php', { method: 'POST', body: fd }).then(() => location.reload());
        }

        // ============================================
        // ADDRESS
        // ============================================
        function openAddressModal(id) {
            document.getElementById('addrError').style.display = 'none';
            document.getElementById('addrForm').reset();
            document.getElementById('addrId').value = '0';
            document.getElementById('addrModalTitle').innerHTML = id > 0 ? '<i class="fas fa-edit"></i> Edit Address' : '<i class="fas fa-map-marker-alt"></i> Add Address';
            if (id > 0) {
                const fd = new FormData();
                fd.append('ajax_action', 'get_address');
                fd.append('id', id);
                fetch('cart.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                    if (res.success && res.address) {
                        const a = res.address;
                        document.getElementById('addrId').value = a.id;
                        document.getElementById('addrLabel').value = a.label || 'Home';
                        document.getElementById('addrRecipient').value = a.recipient_name || '';
                        document.getElementById('addrPhone').value = a.phone || '';
                        document.getElementById('addrCounty').value = a.county || '';
                        document.getElementById('addrCity').value = a.city || '';
                        document.getElementById('addrLine').value = a.address_line || '';
                        document.getElementById('addrInstructions').value = a.delivery_instructions || '';
                        document.getElementById('addrDefault').checked = a.is_default == 1;
                    }
                });
            }
            document.getElementById('addrModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeAddressModal() {
            document.getElementById('addrModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        function saveAddress() {
            const id = document.getElementById('addrId').value;
            const recipient = document.getElementById('addrRecipient').value.trim();
            const phone = document.getElementById('addrPhone').value.trim();
            const county = document.getElementById('addrCounty').value;
            const line = document.getElementById('addrLine').value.trim();
            const errEl = document.getElementById('addrError');
            if (!recipient || !phone || !county || !line) {
                errEl.textContent = 'Please fill in name, phone, county, address.';
                errEl.style.display = 'block'; return;
            }
            const btn = document.getElementById('addrSaveBtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            const fd = new FormData();
            fd.append('ajax_action', 'save_address');
            fd.append('id', id);
            fd.append('label', document.getElementById('addrLabel').value);
            fd.append('recipient_name', recipient);
            fd.append('phone', phone);
            fd.append('county', county);
            fd.append('city', document.getElementById('addrCity').value);
            fd.append('address_line', line);
            fd.append('delivery_instructions', document.getElementById('addrInstructions').value);
            if (document.getElementById('addrDefault').checked) fd.append('is_default', '1');
            fetch('cart.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save';
                if (res.success) location.reload();
                else { errEl.textContent = res.message || 'Failed'; errEl.style.display = 'block'; }
            });
        }
        function deleteAddress(id) {
            if (!confirm('Delete this address?')) return;
            const fd = new FormData();
            fd.append('ajax_action', 'delete_address');
            fd.append('id', id);
            fetch('cart.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => { if (res.success) location.reload(); });
        }
        function setDefaultAddress(id) {
            const fd = new FormData();
            fd.append('ajax_action', 'set_default_address');
            fd.append('id', id);
            fetch('cart.php', { method: 'POST', body: fd }).then(() => location.reload());
        }
        function selectAddress(id, el) {
            document.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');

            const county = el.dataset.county || '';
            if (id === 'pickup' || county === '__PICKUP__') {
                currentTransport = 0;
            } else {
                currentTransport = computeTransportFee(county);
            }
            refreshTotals();
            const tf = document.getElementById('transportFee');
            if (tf) tf.textContent = currentTransport.toLocaleString();

            const fd = new FormData();
            fd.append('ajax_action', 'set_selected_address');
            fd.append('id', id);
            fetch('cart.php', { method: 'POST', body: fd });
        }

        // ============================================
        // CHECKOUT
        // ============================================
        function checkout() {
            if (!document.querySelector('.cart-item')) { alert('Your cart is empty!'); return; }
            if (!document.querySelector('.address-card.selected')) {
                alert('Please select or add a delivery address.'); return;
            }
            window.location.href = 'checkout.php';
        }

        // ============================================
        // QUICK VIEW
        // ============================================
        let qvGallery = [], qvIndex = 0;
        function openQuickView(productId) {
            document.getElementById('qvOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('qvName').textContent = 'Loading…';
            Promise.all([
                fetch('includes/ajax.php?action=get_product&id=' + productId).then(r => r.json()).catch(() => ({})),
                fetch('includes/ajax.php?action=get_product_images&id=' + productId).then(r => r.json()).catch(() => ({}))
            ]).then(([pRes, imgRes]) => {
                const p = pRes.product || {};
                let images = [];
                if (imgRes.success && imgRes.images && imgRes.images.length) images = imgRes.images.map(i => i.image_url);
                else if (p.image_url) images = [p.image_url];
                else if (p.image) images = ['uploads/products/' + p.image];
                else images = ['uploads/products/no-image.png'];
                qvGallery = images; qvIndex = 0;
                document.getElementById('qvName').textContent = p.name || 'Product';
                document.getElementById('qvPrice').textContent = 'Ksh ' + parseFloat(p.price || 0).toLocaleString();
                document.getElementById('qvDescription').textContent = p.description || 'No description.';
                document.getElementById('qvFullLink').href = 'product.php?id=' + productId;
                const stockVal = parseInt(p.stock || 0);
                let meta = '';
                if (p.category_name) meta += `<span class="qv-badge"><i class="fas fa-tag"></i> ${escapeHtml(p.category_name)}</span>`;
                meta += stockVal > 0
                    ? `<span class="qv-badge in-stock"><i class="fas fa-check-circle"></i> In Stock (${stockVal})</span>`
                    : `<span class="qv-badge out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>`;
                document.getElementById('qvMeta').innerHTML = meta;
                const thumbs = document.getElementById('qvThumbs');
                thumbs.innerHTML = images.length > 1 ? images.map((url, i) => `<div class="qv-thumb ${i===0?'active':''}" data-index="${i}" onclick="qvSet(${i})"><img src="${url}" onerror="this.src='uploads/products/no-image.png'"></div>`).join('') : '';
                document.getElementById('qvTotal').textContent = images.length;
                qvSet(0);
            });
        }
        function qvSet(idx) {
            if (!qvGallery.length) return;
            if (idx < 0) idx = qvGallery.length - 1;
            if (idx >= qvGallery.length) idx = 0;
            qvIndex = idx;
            const img = document.getElementById('qvImage');
            img.src = qvGallery[idx];
            document.getElementById('qvCounter').textContent = idx + 1;
            document.querySelectorAll('#qvThumbs .qv-thumb').forEach((t,i) => t.classList.toggle('active', i===idx));
        }
        function qvPrev() { qvSet(qvIndex - 1); }
        function qvNext() { qvSet(qvIndex + 1); }
        function closeQuickView() {
            document.getElementById('qvOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        function escapeHtml(s) {
            if (s == null) return '';
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        }
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('qvOverlay').classList.contains('active')) {
                if (e.key === 'Escape') closeQuickView();
                if (e.key === 'ArrowLeft') qvPrev();
                if (e.key === 'ArrowRight') qvNext();
            }
        });

        refreshTotals();
    </script>
</body>
</html>
