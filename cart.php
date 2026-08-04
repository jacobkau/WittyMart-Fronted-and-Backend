<?php
// Include config first to start session and get database connection
require_once 'includes/config.php';

// Require login to view cart
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login-register.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Initialize variables
$cartItems = [];
$total = 0;

// Get cart items from database
try {
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, c.quantity, 
               p.id as product_id, p.name, p.description, p.price, p.image, p.stock
        FROM cart c
        INNER JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll();
    
    // Calculate total
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }
} catch (PDOException $e) {
    error_log('Cart error: ' . $e->getMessage());
    $cartItems = [];
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
        /* Cart specific styles */
        .cart-empty {
            text-align: center;
            padding: 60px 20px;
            background: #f9f9f9;
            border-radius: 12px;
        }
        
        .cart-empty i {
            font-size: 64px;
            color: #ddd;
            display: block;
            margin-bottom: 20px;
        }
        
        .cart-empty h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .cart-empty p {
            color: #888;
            margin-bottom: 20px;
        }
        
        .cart-empty .shop-now-btn {
            display: inline-block;
            padding: 12px 30px;
            background: #05573c;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .cart-empty .shop-now-btn:hover {
            background: #03402c;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr 150px 150px;
            gap: 20px;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .cart-item-details h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #333;
        }
        
        .cart-item-details p {
            margin: 0;
            font-size: 13px;
            color: #888;
        }
        
        .cart-item-price {
            font-size: 18px;
            font-weight: 700;
            color: #05573c;
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cart-item-actions button {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .cart-item-actions button:hover {
            background: #05573c;
            color: #fff;
            border-color: #05573c;
        }
        
        .cart-item-actions .quantity {
            font-weight: 600;
            font-size: 16px;
            min-width: 30px;
            text-align: center;
        }
        
        .cart-item-actions .remove-btn {
            background: none;
            border: none;
            color: #e74c3c;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: auto;
            height: auto;
        }
        
        .cart-item-actions .remove-btn:hover {
            background: #fee;
            color: #c0392b;
        }
        
        .cart-summary {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .cart-summary h2 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        
        .cart-summary h2 span {
            color: #05573c;
        }
        
        .checkout-btn {
            padding: 14px 40px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .checkout-btn:hover {
            background: #03402c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(5, 87, 60, 0.3);
        }
        
        .checkout-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .update-message {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 0;
            display: none;
        }
        
        .update-message.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .update-message.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 10px;
            }
            
            .cart-item img {
                margin: 0 auto;
            }
            
            .cart-item-actions {
                justify-content: center;
            }
            
            .cart-summary {
                flex-direction: column;
                text-align: center;
            }
        }
        
        /* Dark mode styles */
        body.dark-mode .cart-item {
            border-bottom-color: #2a2a3e;
        }
        
        body.dark-mode .cart-item-details h3 {
            color: #eee;
        }
        
        body.dark-mode .cart-item-actions button {
            background: #2a2a3e;
            border-color: #3a3a5e;
            color: #eee;
        }
        
        body.dark-mode .cart-item-actions button:hover {
            background: #05573c;
            border-color: #05573c;
        }
        
        body.dark-mode .cart-summary {
            background: #1a1a2e;
        }
        
        body.dark-mode .cart-summary h2 {
            color: #eee;
        }
        
        body.dark-mode .cart-empty {
            background: #1a1a2e;
        }
        
        body.dark-mode .cart-empty h2 {
            color: #eee;
        }
        
        body.dark-mode .cart-empty i {
            color: #3a3a5e;
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
            
            <!-- Update Message -->
            <div id="updateMessage" class="update-message"></div>
            
            <?php if (empty($cartItems)): ?>
                <!-- Empty Cart -->
                <div class="cart-empty">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Your Cart is Empty</h2>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="shop.php" class="shop-now-btn">
                        <i class="fas fa-shopping-bag"></i> Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <!-- Cart Items -->
                <div class="cart-items" id="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item" data-cart-id="<?php echo $item['cart_id']; ?>" data-product-id="<?php echo $item['product_id']; ?>">
                            <img src="<?php echo getProductImage($item['image'] ?? ''); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 onerror="this.src='uploads/products/no-image.png'">
                            <div class="cart-item-details">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 60)); ?></p>
                                <?php if ($item['stock'] < $item['quantity']): ?>
                                    <p style="color: #e74c3c; font-size: 12px; font-weight: 600;">
                                        <i class="fas fa-exclamation-triangle"></i> Only <?php echo $item['stock']; ?> in stock
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-price">Ksh <?php echo number_format($item['price'], 0); ?></div>
                            <div class="cart-item-actions">
                                <button onclick="updateQuantity(<?php echo $item['cart_id']; ?>, <?php echo $item['product_id']; ?>, -1, <?php echo $item['stock']; ?>)">-</button>
                                <span class="quantity" id="qty-<?php echo $item['cart_id']; ?>"><?php echo $item['quantity']; ?></span>
                                <button onclick="updateQuantity(<?php echo $item['cart_id']; ?>, <?php echo $item['product_id']; ?>, 1, <?php echo $item['stock']; ?>)">+</button>
                                <button class="remove-btn" onclick="removeItem(<?php echo $item['cart_id']; ?>, <?php echo $item['product_id']; ?>)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h2>Total: Ksh <span id="cart-total"><?php echo number_format($total, 0); ?></span></h2>
                    <button class="checkout-btn" onclick="checkout()" <?php echo empty($cartItems) ? 'disabled' : ''; ?>>
                        <i class="fas fa-credit-card"></i> Proceed to Checkout
                    </button>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include "footer.php"; ?>

    <script>
        // ============================================
        // UPDATE QUANTITY
        // ============================================
        function updateQuantity(cartId, productId, change, maxStock) {
            const quantitySpan = document.getElementById('qty-' + cartId);
            let currentQty = parseInt(quantitySpan.textContent);
            let newQty = currentQty + change;
            
            // Validate quantity
            if (newQty < 1) {
                showMessage('Quantity cannot be less than 1.', 'error');
                return;
            }
            
            if (newQty > maxStock) {
                showMessage('Only ' + maxStock + ' items in stock.', 'error');
                return;
            }
            
            // Show loading state
            quantitySpan.textContent = '...';
            
            // Send AJAX request
            fetch('includes/ajax.php?action=update_cart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cart_id: cartId,
                    product_id: productId,
                    quantity: newQty
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update quantity display
                    quantitySpan.textContent = newQty;
                    
                    // Update total
                    updateCartTotal();
                    
                    showMessage(data.message, 'success');
                } else {
                    // Revert quantity
                    quantitySpan.textContent = currentQty;
                    showMessage(data.message || 'Failed to update quantity.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                quantitySpan.textContent = currentQty;
                showMessage('An error occurred. Please try again.', 'error');
            });
        }

        // ============================================
        // REMOVE ITEM
        // ============================================
        function removeItem(cartId, productId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            const cartItem = document.querySelector('.cart-item[data-cart-id="' + cartId + '"]');
            if (cartItem) {
                cartItem.style.opacity = '0.5';
            }
            
            fetch('includes/ajax.php?action=remove_from_cart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cart_id: cartId,
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove item from DOM
                    if (cartItem) {
                        cartItem.remove();
                    }
                    
                    // Update total
                    updateCartTotal();
                    
                    showMessage(data.message, 'success');
                    
                    // Check if cart is empty
                    const remainingItems = document.querySelectorAll('.cart-item');
                    if (remainingItems.length === 0) {
                        location.reload(); // Reload to show empty cart message
                    }
                } else {
                    if (cartItem) {
                        cartItem.style.opacity = '1';
                    }
                    showMessage(data.message || 'Failed to remove item.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (cartItem) {
                    cartItem.style.opacity = '1';
                }
                showMessage('An error occurred. Please try again.', 'error');
            });
        }

        // ============================================
        // UPDATE CART TOTAL
        // ============================================
        function updateCartTotal() {
            const cartItems = document.querySelectorAll('.cart-item');
            let total = 0;
            
            cartItems.forEach(item => {
                const priceText = item.querySelector('.cart-item-price').textContent;
                const price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                const quantity = parseInt(item.querySelector('.quantity').textContent);
                total += price * quantity;
            });
            
            const totalElement = document.getElementById('cart-total');
            if (totalElement) {
                totalElement.textContent = total.toLocaleString();
            }
        }

        // ============================================
        // CHECKOUT
        // ============================================
        function checkout() {
            const total = document.getElementById('cart-total').textContent;
            if (confirm('Proceed to checkout? Total: Ksh ' + total)) {
                window.location.href = 'checkout.php';
            }
        }

        // ============================================
        // SHOW MESSAGE
        // ============================================
        function showMessage(text, type) {
            const messageDiv = document.getElementById('updateMessage');
            messageDiv.textContent = text;
            messageDiv.className = 'update-message ' + type;
            messageDiv.style.display = 'block';
            
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                    messageDiv.style.opacity = '1';
                }, 500);
            }, 3000);
        }
    </script>
</body>
</html>
