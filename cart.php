<?php
// Include config first to start session and get database connection
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header('Location: home.php');
    exit();
}

// Get cart items from database
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
    $stmt->execute([$_SESSION['user_id']]);
    $cartItems = $stmt->fetchAll();
    
    // Calculate total
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }
} catch (PDOException $e) {
    error_log('Cart error: ' . $e->getMessage());
    $cartItems = [];
}

// Handle GET requests for cart count
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_cart_count') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => getCartCount()
    ]);
    exit();
}

// Handle AJAX requests for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        $action = $_POST['ajax_action'];
        
        switch ($action) {
            // ===== ADD TO CART =====
            case 'add_to_cart':
                $product_id = intval($_POST['product_id'] ?? 0);
                $quantity = intval($_POST['quantity'] ?? 1);
                
                if (!$product_id) {
                    $response = ['success' => false, 'message' => 'Invalid product ID'];
                    break;
                }
                
                // Check if product exists
                $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    $response = ['success' => false, 'message' => 'Product not found'];
                    break;
                }
                
                // Check stock
                if (isset($product['stock']) && $product['stock'] <= 0) {
                    $response = ['success' => false, 'message' => 'Product is out of stock'];
                    break;
                }
                
                // Check if product already in cart
                $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$_SESSION['user_id'], $product_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update quantity
                    $new_quantity = $existing['quantity'] + $quantity;
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_quantity, $existing['id']]);
                    $response = [
                        'success' => true, 
                        'message' => 'Item quantity updated in cart',
                        'cart_count' => getCartCount()
                    ];
                } else {
                    // Insert new item
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
                    $response = [
                        'success' => true, 
                        'message' => 'Item added to cart successfully',
                        'cart_count' => getCartCount()
                    ];
                }
                break;
                
            // ===== GET CART COUNT =====
            case 'get_cart_count':
                $response = [
                    'success' => true,
                    'count' => getCartCount()
                ];
                break;

            // ===== UPDATE CART QUANTITY BY PRODUCT ID =====
            case 'update_cart_quantity':
                $product_id = intval($_POST['product_id'] ?? 0);
                $quantity = intval($_POST['quantity'] ?? 1);
                
                if (!$product_id) {
                    $response = ['success' => false, 'message' => 'Invalid product ID'];
                    break;
                }
                
                if ($quantity <= 0) {
                    // Remove item if quantity is 0 or less
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $product_id]);
                    $response = [
                        'success' => true, 
                        'message' => 'Item removed',
                        'cart_count' => getCartCount()
                    ];
                } else {
                    // Check if product exists in cart
                    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $product_id]);
                    if ($stmt->fetch()) {
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$quantity, $_SESSION['user_id'], $product_id]);
                        $response = [
                            'success' => true, 
                            'message' => 'Quantity updated',
                            'cart_count' => getCartCount()
                        ];
                    } else {
                        // Insert new item if not exists (shouldn't happen, but just in case)
                        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
                        $response = [
                            'success' => true, 
                            'message' => 'Item added to cart',
                            'cart_count' => getCartCount()
                        ];
                    }
                }
                break;

            // ===== REMOVE FROM CART BY PRODUCT ID =====
            case 'remove_from_cart':
                $product_id = intval($_POST['product_id'] ?? 0);
                
                if (!$product_id) {
                    $response = ['success' => false, 'message' => 'Invalid product ID'];
                    break;
                }
                
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$_SESSION['user_id'], $product_id]);
                $response = [
                    'success' => true, 
                    'message' => 'Item removed from cart',
                    'cart_count' => getCartCount()
                ];
                break;
                
            // ===== UPDATE QUANTITY (by cart_id) =====
            case 'update_quantity':
                $cart_id = intval($_POST['cart_id'] ?? 0);
                $quantity = intval($_POST['quantity'] ?? 1);
                
                if (!$cart_id) {
                    $response = ['success' => false, 'message' => 'Invalid cart ID'];
                    break;
                }
                
                if ($quantity <= 0) {
                    // Remove item if quantity is 0 or less
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                    $stmt->execute([$cart_id, $_SESSION['user_id']]);
                    $response = [
                        'success' => true, 
                        'message' => 'Item removed',
                        'cart_count' => getCartCount()
                    ];
                } else {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$quantity, $cart_id, $_SESSION['user_id']]);
                    $response = [
                        'success' => true, 
                        'message' => 'Quantity updated',
                        'cart_count' => getCartCount()
                    ];
                }
                break;
                
            // ===== REMOVE ITEM (by cart_id) =====
            case 'remove_item':
                $cart_id = intval($_POST['cart_id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $_SESSION['user_id']]);
                $response = [
                    'success' => true, 
                    'message' => 'Item removed',
                    'cart_count' => getCartCount()
                ];
                break;
                
            // ===== CLEAR CART =====
            case 'clear_cart':
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $response = [
                    'success' => true, 
                    'message' => 'Cart cleared',
                    'cart_count' => 0
                ];
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Invalid action'];
        }
    } catch (PDOException $e) {
        error_log('Cart AJAX error: ' . $e->getMessage());
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

// Helper function to get cart count
function getCartCount() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        return intval($result['total'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

// Helper function to get product image (with Cloudinary support)
function getCartProductImage($product) {
    if (!is_array($product)) {
        return UPLOAD_URL . 'no-image.png';
    }
    
    // Check for Cloudinary URL first
    if (!empty($product['image_url'])) {
        return $product['image_url'];
    }
    
    // Fallback to local image
    if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])) {
        return UPLOAD_URL . $product['image'];
    }
    
    // Default no-image placeholder
    return UPLOAD_URL . 'no-image.png';
}
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
        /* Cart Styles */
        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 20px 0;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: 20px;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .cart-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .cart-item .image-container {
            position: relative;
            width: 100px;
            height: 100px;
            flex-shrink: 0;
            border-radius: 8px;
            overflow: hidden;
            background: #f5f5f5;
        }
        
        .cart-item .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item .image-container .cloudinary-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(52, 72, 197, 0.9);
            color: white;
            font-size: 8px;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            z-index: 2;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-details h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #333;
        }
        
        .cart-item-details p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }
        
        .cart-item-price {
            font-size: 18px;
            font-weight: 700;
            color: #05573c;
            min-width: 100px;
            text-align: center;
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .cart-item-actions button {
            background: #f0f0f0;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #333;
        }
        
        .cart-item-actions button:hover:not(.remove-btn) {
            background: #05573c;
            color: #fff;
        }
        
        .cart-item-actions .quantity {
            min-width: 30px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
        }
        
        .cart-item-actions .remove-btn {
            background: #dc3545;
            color: #fff;
            padding: 5px 12px;
            font-size: 12px;
        }
        
        .cart-item-actions .remove-btn:hover {
            background: #c82333;
        }
        
        .cart-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .cart-summary h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .cart-summary .checkout-btn {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .cart-summary .checkout-btn:hover {
            background: #03402c;
        }
        
        .cart-summary .clear-cart-btn {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .cart-summary .clear-cart-btn:hover {
            background: #c82333;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-cart i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
            display: block;
        }
        
        .empty-cart p {
            font-size: 18px;
            color: #888;
        }
        
        .empty-cart .btn-primary {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 30px;
            background: #05573c;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .empty-cart .btn-primary:hover {
            background: #03402c;
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-wrap: wrap;
                gap: 12px;
            }
            
            .cart-item .image-container {
                width: 80px;
                height: 80px;
            }
            
            .cart-item-price {
                min-width: auto;
                text-align: left;
                flex: 1;
            }
            
            .cart-item-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .cart-summary {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .cart-summary h2 {
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .cart-item .image-container {
                width: 60px;
                height: 60px;
            }
            
            .cart-item-details h3 {
                font-size: 14px;
            }
            
            .cart-item-details p {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
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
                            <div class="image-container">
                                <img src="<?php echo htmlspecialchars(getCartProductImage($item)); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     onerror="this.src='uploads/products/no-image.png'">
                                <?php if (!empty($item['image_url'])): ?>
                                    <span class="cloudinary-badge">
                                        <i class="fas fa-cloud"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-details">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 80)); ?></p>
                            </div>
                            <div class="cart-item-price">Ksh <?php echo number_format($item['price'], 0); ?></div>
                            <div class="cart-item-actions">
                                <button onclick="updateQuantity(this, <?php echo $item['cart_id']; ?>, -1)" aria-label="Decrease quantity">-</button>
                                <span class="quantity" id="qty-<?php echo $item['cart_id']; ?>"><?php echo $item['quantity']; ?></span>
                                <button onclick="updateQuantity(this, <?php echo $item['cart_id']; ?>, 1)" aria-label="Increase quantity">+</button>
                                <button class="remove-btn" onclick="removeItem(<?php echo $item['cart_id']; ?>)">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h2>Total: KES <span id="cart-total"><?php echo number_format($total, 0); ?></span></h2>
                    <div>
                        <button class="checkout-btn" onclick="checkout()">Proceed to Checkout</button>
                        <button class="clear-cart-btn" onclick="clearCart()">Clear Cart</button>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include "footer.php"; ?>

    <script>
        // ============================================
        // REFRESH CART COUNT IN HEADER
        // ============================================
        function refreshCartCount() {
            fetch('cart.php?action=get_cart_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const count = data.count;
                        
                        // Update badge in nav
                        const badge = document.getElementById('cartBadge');
                        if (badge) {
                            if (count > 0) {
                                badge.textContent = count;
                                badge.classList.remove('empty');
                            } else {
                                badge.classList.add('empty');
                            }
                        }
                        
                        // Update header cart badge
                        const headerBadge = document.getElementById('headerCartBadge');
                        if (headerBadge) {
                            if (count > 0) {
                                headerBadge.textContent = count;
                                headerBadge.classList.remove('empty');
                            } else {
                                headerBadge.classList.add('empty');
                            }
                        }
                    }
                })
                .catch(error => console.error('Error refreshing cart count:', error));
        }

        // ============================================
        // CART FUNCTIONS
        // ============================================
        
        function updateQuantity(button, cartId, change) {
            const quantitySpan = document.getElementById('qty-' + cartId);
            let currentQty = parseInt(quantitySpan.textContent);
            let newQty = currentQty + change;
            
            if (newQty < 1) newQty = 1;
            
            // Update UI immediately
            quantitySpan.textContent = newQty;
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('ajax_action', 'update_quantity');
            formData.append('cart_id', cartId);
            formData.append('quantity', newQty);
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update total
                    updateTotal();
                    // Refresh cart count in header
                    refreshCartCount();
                } else {
                    // Revert on error
                    quantitySpan.textContent = currentQty;
                    alert('Failed to update quantity. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                quantitySpan.textContent = currentQty;
                alert('An error occurred. Please try again.');
            });
        }
        
        function removeItem(cartId) {
            if (!confirm('Are you sure you want to remove this item?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax_action', 'remove_item');
            formData.append('cart_id', cartId);
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the item from DOM
                    const cartItem = document.querySelector(`.cart-item[data-cart-id="${cartId}"]`);
                    if (cartItem) {
                        cartItem.style.transition = 'opacity 0.3s ease';
                        cartItem.style.opacity = '0';
                        setTimeout(() => {
                            cartItem.remove();
                            updateTotal();
                            refreshCartCount();
                            // Check if cart is empty
                            const remainingItems = document.querySelectorAll('.cart-item');
                            if (remainingItems.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                } else {
                    alert('Failed to remove item. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function clearCart() {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax_action', 'clear_cart');
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshCartCount();
                    location.reload();
                } else {
                    alert('Failed to clear cart. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function updateTotal() {
            const cartItems = document.querySelectorAll('.cart-item');
            let total = 0;
            
            cartItems.forEach(item => {
                const priceText = item.querySelector('.cart-item-price').textContent;
                const price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                const quantity = parseInt(item.querySelector('.quantity').textContent);
                total += price * quantity;
            });
            
            document.getElementById('cart-total').textContent = total.toLocaleString();
        }
        
        function checkout() {
            const cartItems = document.querySelectorAll('.cart-item');
            if (cartItems.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            alert('Proceeding to checkout...');
            // window.location.href = 'checkout.php';
        }
    </script>
</body>
</html>
