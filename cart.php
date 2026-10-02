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
                $response = ['success'=>true, 'cart_count'=>getCartCount()];
                break;

            case 'clear_cart':
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $response = ['success'=>true, 'cart_count'=>0];
                break;

            // ============================================
            // ADDRESS MANAGEMENT (DB-backed)
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
                    $pdo->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?")
                        ->execute([$user_id]);
                }

                if ($addr_id > 0) {
                    // Update existing
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

                // If no address is default yet, make this one default
                $check = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND is_default = TRUE");
                $check->execute([$user_id]);
                if ($check->fetchColumn() == 0) {
                    $pdo->prepare("UPDATE user_addresses SET is_default = TRUE WHERE id = ?")
                        ->execute([$saved_id]);
                }

                $response = ['success'=>true, 'message'=>'Address saved', 'id'=>$saved_id];
                break;

            case 'delete_address':
                $addr_id = intval($_POST['id'] ?? 0);
                if (!$addr_id) { $response = ['success'=>false,'message'=>'Invalid address']; break; }
                
                $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$addr_id, $user_id]);
                
                // If we deleted the default, promote another
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
                $_SESSION['selected_address_id'] = intval($_POST['id'] ?? 0);
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
} catch (PDOException $e) {
    error_log('Cart error: '.$e->getMessage());
}

// User's saved addresses
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

// Which address is selected?
$selectedAddressId = $_SESSION['selected_address_id'] ?? 0;
$selectedAddress = null;
if ($selectedAddressId) {
    foreach ($userAddresses as $a) if ((int)$a['id'] === (int)$selectedAddressId) { $selectedAddress = $a; break; }
}
if (!$selectedAddress && !empty($userAddresses)) {
    // Fall back to default or first
    foreach ($userAddresses as $a) if ($a['is_default']) { $selectedAddress = $a; break; }
    if (!$selectedAddress) $selectedAddress = $userAddresses[0];
    $selectedAddressId = (int)$selectedAddress['id'];
    $_SESSION['selected_address_id'] = $selectedAddressId;
}

// Transport fee based on selected address county
$transportFee = $selectedAddress ? countyTransportFee($selectedAddress['county']) : 0;

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
        .cart-item {
            display:flex; align-items:center; gap:20px; background:#fff;
            padding:15px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.08);
            transition:all 0.3s ease; position:relative;
        }
        .cart-item:hover { box-shadow:0 4px 15px rgba(0,0,0,0.12); }
        .cart-item .image-container {
            position:relative; width:100px; height:100px; flex-shrink:0;
            border-radius:8px; overflow:hidden; background:#f5f5f5; cursor:pointer;
        }
        .cart-item .image-container img { width:100%; height:100%; object-fit:cover; transition:transform 0.3s ease; }
        .cart-item .image-container:hover img { transform:scale(1.06); }
        .cart-item .image-container .cloudinary-badge {
            position:absolute; top:4px; right:4px; background:rgba(52,72,197,0.9);
            color:#fff; font-size:8px; padding:2px 6px; border-radius:8px; font-weight:600;
        }
        .cart-item .image-container .view-overlay {
            position:absolute; inset:0; background:rgba(5,87,60,0.75); color:#fff;
            display:flex; align-items:center; justify-content:center; font-size:22px;
            opacity:0; transition:opacity 0.25s ease;
        }
        .cart-item .image-container:hover .view-overlay { opacity:1; }
        .cart-item-details { flex:1; min-width:0; }
        .cart-item-details h3 { margin:0 0 5px; font-size:16px; color:#333; }
        .cart-item-details h3 a { text-decoration:none; color:inherit; }
        .cart-item-details h3 a:hover { color:#05573c; }
        .cart-item-details p {
            margin:0; font-size:13px; color:#666;
            overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
        }
        .cart-item-details .view-details-link {
            display:inline-block; margin-top:6px; font-size:12px;
            color:#05573c; font-weight:600; text-decoration:none;
        }
        .cart-item-price { font-size:18px; font-weight:700; color:#05573c; min-width:100px; text-align:center; }
        .cart-item-actions { display:flex; align-items:center; gap:8px; }
        .cart-item-actions button {
            background:#f0f0f0; border:none; padding:5px 12px;
            border-radius:4px; cursor:pointer; font-size:16px; font-weight:600;
            transition:all 0.3s ease; color:#333;
        }
        .cart-item-actions button:hover:not(.remove-btn) { background:#05573c; color:#fff; }
        .cart-item-actions .quantity { min-width:30px; text-align:center; font-weight:600; font-size:16px; }
        .cart-item-actions .remove-btn { background:#dc3545; color:#fff; padding:5px 12px; font-size:12px; }
        .cart-item-actions .remove-btn:hover { background:#c82333; }

        /* ============================================
           DELIVERY / ADDRESS PANEL
           ============================================ */
        .delivery-panel {
            background:#fff; border-radius:10px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
            padding:24px; margin-top:20px;
        }
        .delivery-panel h2 {
            margin:0 0 16px; font-size:18px; color:#333;
            display:flex; align-items:center; gap:8px;
        }
        .delivery-panel h2 .add-addr-link {
            margin-left:auto; font-size:13px; font-weight:600;
            color:#05573c; cursor:pointer; text-decoration:none;
        }
        .delivery-panel h2 .add-addr-link:hover { text-decoration:underline; }

        .address-list { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; margin-bottom:16px; }
        .address-card {
            border:2px solid #e0e0e0; border-radius:10px; padding:14px;
            background:#fafafa; cursor:pointer; transition:all 0.2s ease; position:relative;
        }
        .address-card:hover { border-color:#0a7a54; background:#fff; }
        .address-card.selected { border-color:#05573c; background:#f0faf5; }
        .address-card .addr-label {
            display:inline-block; font-size:11px; font-weight:700;
            padding:2px 8px; border-radius:10px; background:#e8f5f0; color:#05573c;
            margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;
        }
        .address-card.selected .addr-label { background:#05573c; color:#fff; }
        .address-card .addr-default-badge {
            display:inline-block; font-size:10px; font-weight:700;
            padding:2px 8px; border-radius:10px; background:#ffc107; color:#333;
            margin-left:6px;
        }
        .address-card .addr-recipient { font-weight:700; color:#222; font-size:14px; }
        .address-card .addr-line { color:#555; font-size:13px; margin-top:4px; line-height:1.5; }
        .address-card .addr-phone { color:#888; font-size:12px; margin-top:4px; }
        .address-card .addr-actions {
            display:flex; gap:6px; margin-top:10px; flex-wrap:wrap;
        }
        .address-card .addr-actions button {
            background:#fff; border:1px solid #ddd; padding:4px 10px;
            border-radius:6px; font-size:11px; font-weight:600; cursor:pointer;
            color:#555; transition:all 0.2s ease;
        }
        .address-card .addr-actions button:hover { border-color:#05573c; color:#05573c; }
        .address-card .addr-actions .btn-set-default { color:#05573c; border-color:#05573c; }
        .address-card .addr-actions .btn-delete { color:#dc3545; border-color:#dc3545; }
        .address-card .addr-actions .btn-delete:hover { background:#dc3545; color:#fff; }

        .no-addresses {
            text-align:center; padding:30px 20px;
            background:#fafafa; border-radius:10px; color:#888; margin-bottom:16px;
        }
        .no-addresses i { font-size:40px; opacity:0.3; display:block; margin-bottom:12px; }
        .no-addresses .btn-add-first {
            display:inline-block; margin-top:12px; padding:10px 22px;
            background:#05573c; color:#fff; border-radius:6px; border:none;
            cursor:pointer; font-weight:600; text-decoration:none;
        }
        .no-addresses .btn-add-first:hover { background:#03402c; }

        .delivery-fee-note {
            display:flex; justify-content:space-between; align-items:center;
            padding:12px 16px; background:#f0faf5; border-radius:8px;
            border-left:4px solid #05573c; font-size:14px; color:#333;
        }
        .delivery-fee-note strong { color:#05573c; font-size:16px; }

        /* ============================================
           SUMMARY
           ============================================ */
        .cart-summary {
            background:#fff; border-radius:10px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
            padding:24px; margin-top:20px;
        }
        .summary-rows { margin-bottom:18px; }
        .summary-row {
            display:flex; justify-content:space-between;
            padding:8px 0; font-size:15px; color:#555;
            border-bottom:1px solid #f0f0f0;
        }
        .summary-row:last-child { border-bottom:none; }
        .summary-row.total-row {
            font-size:20px; font-weight:700; color:#05573c;
            padding-top:14px; margin-top:6px;
            border-top:2px solid #05573c; border-bottom:none;
        }
        .summary-actions { display:flex; gap:12px; flex-wrap:wrap; justify-content:flex-end; }
        .checkout-btn {
            background:#05573c; color:#fff; border:none;
            padding:12px 30px; border-radius:6px; font-weight:600;
            cursor:pointer; transition:all 0.3s ease;
            display:inline-flex; align-items:center; gap:8px;
        }
        .checkout-btn:hover:not(:disabled) { background:#03402c; }
        .checkout-btn:disabled { opacity:0.6; cursor:not-allowed; }
        .clear-cart-btn {
            background:#dc3545; color:#fff; border:none;
            padding:12px 20px; border-radius:6px; cursor:pointer;
            font-weight:600; transition:all 0.3s ease;
        }
        .clear-cart-btn:hover { background:#c82333; }

        .empty-cart { text-align:center; padding:60px 20px; }
        .empty-cart i { font-size:60px; color:#ccc; margin-bottom:20px; display:block; }
        .empty-cart p { font-size:18px; color:#888; }
        .empty-cart .btn-primary {
            display:inline-block; margin-top:15px; padding:10px 30px;
            background:#05573c; color:#fff; border-radius:6px;
            text-decoration:none; font-weight:600;
        }

        /* ============================================
           ADDRESS MODAL
           ============================================ */
        .addr-modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.6); z-index:99999;
            justify-content:center; align-items:center; padding:20px;
        }
        .addr-modal-overlay.active { display:flex; }
        .addr-modal {
            background:#fff; border-radius:12px;
            max-width:560px; width:100%; max-height:90vh; overflow-y:auto;
            padding:26px; position:relative;
        }
        .addr-modal h3 {
            margin:0 0 18px; font-size:20px; color:#222;
            display:flex; align-items:center; gap:8px;
        }
        .addr-modal .close-x {
            position:absolute; top:14px; right:16px;
            background:none; border:none; font-size:22px;
            cursor:pointer; color:#888;
        }
        .addr-modal .close-x:hover { color:#333; }

        .addr-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
        .addr-form-group { margin-bottom:12px; }
        .addr-form-group label {
            display:block; font-weight:600; font-size:13px;
            color:#555; margin-bottom:6px;
        }
        .addr-form-group label .required { color:#dc3545; }
        .addr-form-group input,
        .addr-form-group select,
        .addr-form-group textarea {
            width:100%; padding:10px 14px;
            border:2px solid #e0e0e0; border-radius:8px;
            font-size:14px; color:#333; background:#fff;
            transition:all 0.2s ease; font-family:inherit;
        }
        .addr-form-group textarea { resize:vertical; min-height:70px; }
        .addr-form-group input:focus,
        .addr-form-group select:focus,
        .addr-form-group textarea:focus {
            outline:none; border-color:#05573c;
            box-shadow:0 0 0 3px rgba(5,87,60,0.1);
        }
        .addr-form-group .checkbox-row {
            display:flex; align-items:center; gap:8px;
            font-size:14px; color:#333;
        }
        .addr-form-group .checkbox-row input[type=checkbox] {
            width:auto; margin:0;
        }
        .addr-form-actions {
            display:flex; gap:10px; justify-content:flex-end;
            margin-top:16px; padding-top:16px;
            border-top:1px solid #f0f0f0;
        }
        .addr-form-actions .btn-cancel {
            background:#f0f0f0; color:#333; border:none;
            padding:10px 20px; border-radius:6px;
            cursor:pointer; font-weight:600;
        }
        .addr-form-actions .btn-save {
            background:#05573c; color:#fff; border:none;
            padding:10px 24px; border-radius:6px;
            cursor:pointer; font-weight:600;
            display:inline-flex; align-items:center; gap:6px;
        }
        .addr-form-actions .btn-save:hover { background:#03402c; }

        .addr-error {
            background:#f8d7da; color:#721c24;
            padding:10px 14px; border-radius:6px;
            font-size:13px; margin-bottom:12px;
        }

        @media (max-width:768px) {
            .cart-item { flex-wrap:wrap; gap:12px; }
            .cart-item .image-container { width:80px; height:80px; }
            .cart-item-price { min-width:auto; text-align:left; flex:1; }
            .cart-item-actions { width:100%; justify-content:flex-start; }
            .addr-form-row { grid-template-columns:1fr; }
            .summary-actions { justify-content:stretch; }
            .checkout-btn, .clear-cart-btn { width:100%; justify-content:center; }
        }

        @media (max-width:480px) {
            .cart-item .image-container { width:60px; height:60px; }
            .cart-item-details h3 { font-size:14px; }
            .cart-item-details p { font-size:12px; }
        }

        /* ============================================
           QUICK VIEW (unchanged)
           ============================================ */
        .qv-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.6); z-index:99999;
            justify-content:center; align-items:center; padding:20px;
        }
        .qv-overlay.active { display:flex; }
        .qv-modal {
            background:#fff; border-radius:12px; max-width:900px;
            width:100%; max-height:90vh; overflow-y:auto;
            position:relative; display:grid;
            grid-template-columns:1fr 1fr;
        }
        .qv-close {
            position:absolute; top:12px; right:12px;
            background:rgba(0,0,0,0.5); color:#fff; border:none;
            width:36px; height:36px; border-radius:50%;
            cursor:pointer; font-size:18px;
            display:flex; align-items:center; justify-content:center; z-index:3;
        }
        .qv-close:hover { background:rgba(0,0,0,0.8); }
        .qv-image-wrap {
            position:relative; background:#f5f5f5; min-height:340px;
            border-radius:12px 0 0 12px; overflow:hidden;
        }
        .qv-image-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
        .qv-nav {
            position:absolute; top:50%; transform:translateY(-50%);
            background:rgba(255,255,255,0.9); color:#333; border:none;
            width:36px; height:36px; border-radius:50%; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            font-size:15px; box-shadow:0 2px 8px rgba(0,0,0,0.15); z-index:2;
        }
        .qv-nav:hover { background:#05573c; color:#fff; }
        .qv-nav.prev { left:10px; }
        .qv-nav.next { right:10px; }
        .qv-counter {
            position:absolute; top:12px; left:12px;
            background:rgba(0,0,0,0.7); color:#fff;
            font-size:11px; padding:4px 10px;
            border-radius:12px; font-weight:600; z-index:2;
            display:flex; align-items:center; gap:5px;
        }
        .qv-thumbs {
            position:absolute; bottom:8px; left:8px; right:8px;
            display:flex; gap:6px; overflow-x:auto; padding:4px; z-index:2;
        }
        .qv-thumb {
            flex:0 0 48px; width:48px; height:48px;
            border-radius:6px; overflow:hidden;
            border:2px solid transparent; cursor:pointer; background:#fff;
        }
        .qv-thumb img { width:100%; height:100%; object-fit:cover; }
        .qv-thumb.active { border-color:#05573c; }
        .qv-info { padding:30px; display:flex; flex-direction:column; gap:12px; }
        .qv-info h2 { margin:0; font-size:22px; color:#222; }
        .qv-price { font-size:26px; font-weight:700; color:#05573c; }
        .qv-meta { display:flex; flex-wrap:wrap; gap:8px; }
        .qv-badge {
            background:#f0f0f0; color:#555;
            padding:4px 12px; border-radius:12px; font-size:12px;
            display:inline-flex; align-items:center; gap:5px;
        }
        .qv-badge.in-stock { background:#d4edda; color:#155724; }
        .qv-badge.out-of-stock { background:#f8d7da; color:#721c24; }
        .qv-description {
            background:#f8f9fa; padding:14px; border-radius:8px;
            font-size:14px; line-height:1.7; color:#444;
            max-height:220px; overflow-y:auto;
        }
        .qv-actions { display:flex; gap:10px; margin-top:auto; padding-top:12px; }
        .qv-btn-primary {
            flex:1; background:#05573c; color:#fff; border:none;
            padding:12px 20px; border-radius:6px; font-weight:600;
            cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
        }
        .qv-btn-primary:hover { background:#03402c; }
        .qv-btn-secondary {
            background:#f0f0f0; color:#333; border:none;
            padding:12px 20px; border-radius:6px; font-weight:600; cursor:pointer;
        }
        @media (max-width:768px) {
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
                                <img src="<?php echo htmlspecialchars(getCartProductImage($item)); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     onerror="this.src='uploads/products/no-image.png'">
                                <span class="view-overlay"><i class="fas fa-eye"></i></span>
                                <?php if (!empty($item['image_url'])): ?>
                                    <span class="cloudinary-badge"><i class="fas fa-cloud"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-details">
                                <h3><a href="product.php?id=<?php echo (int)$item['product_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                                <p><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 90)); ?>…</p>
                                <a href="#" class="view-details-link" onclick="event.preventDefault(); openQuickView(<?php echo (int)$item['product_id']; ?>)">
                                    <i class="fas fa-eye"></i> Quick view
                                </a>
                            </div>
                            <div class="cart-item-price">Ksh <?php echo number_format($item['price'], 0); ?></div>
                            <div class="cart-item-actions">
                                <button onclick="updateQuantity(this, <?php echo $item['cart_id']; ?>, -1)">-</button>
                                <span class="quantity" id="qty-<?php echo $item['cart_id']; ?>"><?php echo $item['quantity']; ?></span>
                                <button onclick="updateQuantity(this, <?php echo $item['cart_id']; ?>, 1)">+</button>
                                <button class="remove-btn" onclick="removeItem(<?php echo $item['cart_id']; ?>)">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ============================================
                     DELIVERY ADDRESSES (DB)
                     ============================================ -->
                <div class="delivery-panel">
                    <h2>
                        <i class="fas fa-map-marker-alt"></i> Delivery Address
                        <a class="add-addr-link" onclick="openAddressModal(0)">
                            <i class="fas fa-plus"></i> Add New Address
                        </a>
                    </h2>

                    <?php if (empty($userAddresses)): ?>
                        <div class="no-addresses">
                            <i class="fas fa-map-marked-alt"></i>
                            <h3 style="margin:0 0 6px; color:#555;">No saved addresses yet</h3>
                            <p style="margin:0;">Add a delivery address so we can send your order.</p>
                            <button class="btn-add-first" onclick="openAddressModal(0)">
                                <i class="fas fa-plus"></i> Add Your First Address
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="address-list" id="addressList">
                            <?php foreach ($userAddresses as $addr): ?>
                                <?php $isSelected = (int)$addr['id'] === (int)$selectedAddressId; ?>
                                <div class="address-card <?php echo $isSelected ? 'selected' : ''; ?>"
                                     data-id="<?php echo (int)$addr['id']; ?>"
                                     onclick="selectAddress(<?php echo (int)$addr['id']; ?>, this)">
                                    <div>
                                        <span class="addr-label"><?php echo htmlspecialchars($addr['label']); ?></span>
                                        <?php if ($addr['is_default']): ?>
                                            <span class="addr-default-badge">DEFAULT</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="addr-recipient"><?php echo htmlspecialchars($addr['recipient_name']); ?></div>
                                    <div class="addr-line">
                                        <?php echo htmlspecialchars($addr['address_line']); ?><br>
                                        <?php echo htmlspecialchars($addr['county']); ?><?php if (!empty($addr['city'])): ?>, <?php echo htmlspecialchars($addr['city']); ?><?php endif; ?>
                                    </div>
                                    <div class="addr-phone"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($addr['phone']); ?></div>
                                    
                                    <div class="addr-actions" onclick="event.stopPropagation();">
                                        <button onclick="openAddressModal(<?php echo (int)$addr['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php if (!$addr['is_default']): ?>
                                            <button class="btn-set-default" onclick="setDefaultAddress(<?php echo (int)$addr['id']; ?>)">
                                                <i class="fas fa-star"></i> Set Default
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-delete" onclick="deleteAddress(<?php echo (int)$addr['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($selectedAddress): ?>
                            <div class="delivery-fee-note">
                                <span><i class="fas fa-info-circle"></i> Transport to <strong><?php echo htmlspecialchars($selectedAddress['county']); ?></strong>:</span>
                                <strong>Ksh <span id="transportFee"><?php echo number_format($transportFee, 0); ?></span></strong>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ============================================
                     SUMMARY
                     ============================================ -->
                <div class="cart-summary">
                    <div class="summary-rows">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>Ksh <span id="cart-total"><?php echo number_format($total, 0); ?></span></span>
                        </div>
                        <div class="summary-row">
                            <span>Transport fee</span>
                            <span>Ksh <span id="cart-transport"><?php echo number_format($transportFee, 0); ?></span></span>
                        </div>
                        <div class="summary-row total-row">
                            <span>Total</span>
                            <span>Ksh <span id="cart-grand-total"><?php echo number_format($total + $transportFee, 0); ?></span></span>
                        </div>
                    </div>

                    <div class="summary-actions">
                        <button class="checkout-btn" onclick="checkout()">
                            <i class="fas fa-credit-card"></i> Proceed to Checkout
                        </button>
                        <button class="clear-cart-btn" onclick="clearCart()">
                            <i class="fas fa-trash"></i> Clear Cart
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- ============================================
         ADDRESS MODAL
         ============================================ -->
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
                        <input type="text" id="addrRecipient" placeholder="Who receives the order?" required>
                    </div>
                </div>

                <div class="addr-form-row">
                    <div class="addr-form-group">
                        <label>Phone <span class="required">*</span></label>
                        <input type="tel" id="addrPhone" placeholder="+254 ..." required>
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
                    <input type="text" id="addrCity" placeholder="e.g., Westlands">
                </div>

                <div class="addr-form-group">
                    <label>Address Line <span class="required">*</span></label>
                    <textarea id="addrLine" placeholder="Building, street, estate, landmark…" required></textarea>
                </div>

                <div class="addr-form-group">
                    <label>Delivery Instructions</label>
                    <textarea id="addrInstructions" placeholder="e.g., Call on arrival, gate code, etc."></textarea>
                </div>

                <div class="addr-form-group">
                    <label class="checkbox-row">
                        <input type="checkbox" id="addrDefault">
                        Set as my default address
                    </label>
                </div>

                <div class="addr-form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddressModal()">Cancel</button>
                    <button type="button" class="btn-save" id="addrSaveBtn" onclick="saveAddress()">
                        <i class="fas fa-save"></i> Save Address
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================
         QUICK VIEW
         ============================================ -->
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
        // ============================================
        // TRANSPORT FEES
        // ============================================
        function computeTransportFee(county) {
            if (!county) return 0;
            const nearby = ['Nairobi','Kiambu','Machakos','Kajiado',"Murang'a",'Nyeri','Kirinyaga','Embu','Nakuru'];
            const mid    = ['Mombasa','Kisumu','Uasin Gishu','Kakamega','Meru','Laikipia','Bungoma','Kisii','Nyamira','Kericho','Bomet','Narok'];
            if (nearby.includes(county)) return 100;
            if (mid.includes(county))    return 150;
            return 200;
        }

        let subtotal        = <?php echo (float)$total; ?>;
        let currentTransport = <?php echo (float)$transportFee; ?>;

        function refreshTotals() {
            document.getElementById('cart-total').textContent = subtotal.toLocaleString();
            const tEl = document.getElementById('cart-transport');
            if (tEl) tEl.textContent = currentTransport.toLocaleString();
            document.getElementById('cart-grand-total').textContent = (subtotal + currentTransport).toLocaleString();
        }

        // ============================================
        // CART OPERATIONS
        // ============================================
        function refreshCartCount() {
            fetch('cart.php?action=get_cart_count').then(r => r.json()).then(data => {
                if (!data.success) return;
                const count = data.count;
                const badge = document.getElementById('cartBadge');
                if (badge) { badge.textContent = count || ''; badge.classList.toggle('empty', count === 0); }
                const hb = document.getElementById('headerCartBadge');
                if (hb) { hb.textContent = count || ''; hb.classList.toggle('empty', count === 0); }
            }).catch(() => {});
        }

        function updateQuantity(button, cartId, change) {
            const span = document.getElementById('qty-' + cartId);
            const cur  = parseInt(span.textContent);
            let nxt    = cur + change;
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
                    else { span.textContent = cur; alert('Failed to update.'); }
                })
                .catch(() => { span.textContent = cur; });
        }

        function removeItem(cartId) {
            if (!confirm('Remove this item?')) return;
            const fd = new FormData();
            fd.append('ajax_action', 'remove_item');
            fd.append('cart_id', cartId);
            fetch('cart.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const el = document.querySelector(`.cart-item[data-cart-id="${cartId}"]`);
                        if (el) {
                            el.style.transition = 'opacity 0.3s';
                            el.style.opacity = '0';
                            setTimeout(() => {
                                el.remove();
                                recalcSubtotal();
                                refreshCartCount();
                                if (document.querySelectorAll('.cart-item').length === 0) location.reload();
                            }, 300);
                        }
                    }
                });
        }

        function clearCart() {
            if (!confirm('Clear entire cart?')) return;
            const fd = new FormData();
            fd.append('ajax_action', 'clear_cart');
            fetch('cart.php', { method: 'POST', body: fd }).then(() => {
                refreshCartCount();
                location.reload();
            });
        }

        function recalcSubtotal() {
            let sum = 0;
            document.querySelectorAll('.cart-item').forEach(item => {
                const price = parseFloat(item.querySelector('.cart-item-price').textContent.replace(/[^0-9.]/g, ''));
                const qty   = parseInt(item.querySelector('.quantity').textContent);
                sum += price * qty;
            });
            subtotal = sum;
            refreshTotals();
        }

        // ============================================
        // ADDRESS MANAGEMENT
        // ============================================
        function openAddressModal(id) {
            document.getElementById('addrError').style.display = 'none';
            document.getElementById('addrForm').reset();
            document.getElementById('addrId').value = '0';
            document.getElementById('addrModalTitle').innerHTML =
                id > 0 ? '<i class="fas fa-edit"></i> Edit Address' : '<i class="fas fa-map-marker-alt"></i> Add Address';

            if (id > 0) {
                // Fill from existing (stored in data attributes via PHP)
                fetch('cart.php', {
                    method: 'POST',
                    body: (function() {
                        const fd = new FormData();
                        fd.append('ajax_action', 'get_address');
                        fd.append('id', id);
                        return fd;
                    })()
                }).then(r => r.json()).then(res => {
                    if (res.success && res.address) {
                        const a = res.address;
                        document.getElementById('addrId').value          = a.id;
                        document.getElementById('addrLabel').value       = a.label || 'Home';
                        document.getElementById('addrRecipient').value   = a.recipient_name || '';
                        document.getElementById('addrPhone').value       = a.phone || '';
                        document.getElementById('addrCounty').value      = a.county || '';
                        document.getElementById('addrCity').value        = a.city || '';
                        document.getElementById('addrLine').value        = a.address_line || '';
                        document.getElementById('addrInstructions').value = a.delivery_instructions || '';
                        document.getElementById('addrDefault').checked   = a.is_default == 1 || a.is_default === true;
                    }
                }).catch(() => {});
            }

            document.getElementById('addrModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddressModal() {
            document.getElementById('addrModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function saveAddress() {
            const id        = document.getElementById('addrId').value;
            const label     = document.getElementById('addrLabel').value;
            const recipient = document.getElementById('addrRecipient').value.trim();
            const phone     = document.getElementById('addrPhone').value.trim();
            const county    = document.getElementById('addrCounty').value;
            const city      = document.getElementById('addrCity').value.trim();
            const line      = document.getElementById('addrLine').value.trim();
            const inst      = document.getElementById('addrInstructions').value.trim();
            const def       = document.getElementById('addrDefault').checked;

            const errEl = document.getElementById('addrError');
            if (!recipient || !phone || !county || !line) {
                errEl.textContent = 'Please fill in recipient name, phone, county and address.';
                errEl.style.display = 'block';
                return;
            }

            const btn = document.getElementById('addrSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

            const fd = new FormData();
            fd.append('ajax_action', 'save_address');
            fd.append('id', id);
            fd.append('label', label);
            fd.append('recipient_name', recipient);
            fd.append('phone', phone);
            fd.append('county', county);
            fd.append('city', city);
            fd.append('address_line', line);
            fd.append('delivery_instructions', inst);
            if (def) fd.append('is_default', '1');

            fetch('cart.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Save Address';
                    if (res.success) {
                        closeAddressModal();
                        location.reload();
                    } else {
                        errEl.textContent = res.message || 'Failed to save';
                        errEl.style.display = 'block';
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Save Address';
                    errEl.textContent = 'Network error';
                    errEl.style.display = 'block';
                });
        }

        function deleteAddress(id) {
            if (!confirm('Delete this address?')) return;
            const fd = new FormData();
            fd.append('ajax_action', 'delete_address');
            fd.append('id', id);
            fetch('cart.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) location.reload();
                    else alert(res.message || 'Failed');
                });
        }

        function setDefaultAddress(id) {
            const fd = new FormData();
            fd.append('ajax_action', 'set_default_address');
            fd.append('id', id);
            fetch('cart.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => { if (res.success) location.reload(); });
        }

        function selectAddress(id, el) {
            // Update UI
            document.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
            if (el) el.classList.add('selected');

            // Compute new transport fee from the selected card's county
            let county = '';
            if (el) {
                const line = el.querySelector('.addr-line').textContent;
                // Extract county from card (2nd line, first part)
                // Simpler: use data attribute
                const matchCounty = el.innerText.split('\n').map(s => s.trim()).filter(Boolean);
                // Better: look up via a data attribute we'll add in PHP
                county = el.dataset.county || '';
            }

            currentTransport = computeTransportFee(county);
            refreshTotals();

            // Save selection to session
            const fd = new FormData();
            fd.append('ajax_action', 'set_selected_address');
            fd.append('id', id);
            fetch('cart.php', { method: 'POST', body: fd });
        }

        // Update the transport fee pill display if present
        function updateTransportPill(county) {
            const el = document.getElementById('transportFee');
            if (el) el.textContent = computeTransportFee(county).toLocaleString();
        }

        // ============================================
        // CHECKOUT
        // ============================================
        function checkout() {
            if (document.querySelectorAll('.cart-item').length === 0) {
                alert('Your cart is empty!'); return;
            }
            const selected = document.querySelector('.address-card.selected');
            if (!selected) {
                alert('Please select or add a delivery address.');
                return;
            }
            window.location.href = 'checkout.php';
        }

        // ============================================
        // QUICK VIEW
        // ============================================
        let qvGallery = [], qvIndex = 0;

        function openQuickView(productId) {
            qvIndex = 0;
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
                else if (p.image)     images = ['uploads/products/' + p.image];
                else                  images = ['uploads/products/no-image.png'];

                qvGallery = images; qvIndex = 0;

                document.getElementById('qvName').textContent = p.name || 'Product';
                document.getElementById('qvPrice').textContent = 'Ksh ' + parseFloat(p.price || 0).toLocaleString();
                document.getElementById('qvDescription').textContent = p.description || 'No description.';
                document.getElementById('qvFullLink').href = 'product.php?id=' + productId;

                const stockVal = parseInt(p.stock || 0);
                let meta = '';
                if (p.category_name) meta += `<span class="qv-badge"><i class="fas fa-tag"></i> ${escapeHtml(p.category_name)}</span>`;
                if (p.sku)           meta += `<span class="qv-badge"><i class="fas fa-barcode"></i> ${escapeHtml(p.sku)}</span>`;
                meta += stockVal > 0
                    ? `<span class="qv-badge in-stock"><i class="fas fa-check-circle"></i> In Stock (${stockVal})</span>`
                    : `<span class="qv-badge out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>`;
                document.getElementById('qvMeta').innerHTML = meta;

                const thumbs = document.getElementById('qvThumbs');
                thumbs.innerHTML = images.length > 1
                    ? images.map((url, i) => `<div class="qv-thumb ${i === 0 ? 'active' : ''}" data-index="${i}" onclick="qvSet(${i})"><img src="${url}" onerror="this.src='uploads/products/no-image.png'"></div>`).join('')
                    : '';

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
            img.style.opacity = '0.5';
            setTimeout(() => {
                img.src = qvGallery[idx];
                img.onerror = () => { img.src = 'uploads/products/no-image.png'; };
                img.style.opacity = '1';
            }, 100);
            document.getElementById('qvCounter').textContent = idx + 1;
            document.querySelectorAll('#qvThumbs .qv-thumb').forEach((t, i) => t.classList.toggle('active', i === idx));
        }
        function qvPrev() { qvSet(qvIndex - 1); }
        function qvNext() { qvSet(qvIndex + 1); }
        function closeQuickView() {
            document.getElementById('qvOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function escapeHtml(s) {
            if (s == null) return '';
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
