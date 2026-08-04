<?php
// Include config first to start session and get database connection
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header('Location: login-register.php');
    exit();
}

// Get cart items from database
$cartItems = [];
$total = 0;

try {
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, c.product_id, c.quantity, 
               p.name, p.price, p.image, p.description 
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

// Handle AJAX requests for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        $action = $_POST['ajax_action'];
        
        switch ($action) {
            case 'update_quantity':
                $cart_id = intval($_POST['cart_id'] ?? 0);
                $quantity = intval($_POST['quantity'] ?? 1);
                
                if ($quantity <= 0) {
                    // Remove item if quantity is 0 or less
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                    $stmt->execute([$cart_id, $_SESSION['user_id']]);
                    $response = ['success' => true, 'message' => 'Item removed'];
                } else {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$quantity, $cart_id, $_SESSION['user_id']]);
                    $response = ['success' => true, 'message' => 'Quantity updated'];
                }
                break;
                
            case 'remove_item':
                $cart_id = intval($_POST['cart_id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $_SESSION['user_id']]);
                $response = ['success' => true, 'message' => 'Item removed'];
                break;
                
            case 'clear_cart':
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $response = ['success' => true, 'message' => 'Cart cleared'];
                break;
        }
    } catch (PDOException $e) {
        error_log('Cart AJAX error: ' . $e->getMessage());
        $response = ['success' => false, 'message' => 'Database error'];
    }
    
    echo json_encode($response);
    exit();
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
                    <i class="fas fa-shopping-cart" style="font-size: 60px; color: #ccc; margin-bottom: 20px; display: block;"></i>
                    <p style="font-size: 18px; color: #888;">Your cart is empty</p>
                    <a href="shop.php" class="btn-primary" style="display: inline-block; margin-top: 15px; padding: 10px 30px; background: #05573c; color: #fff; border-radius: 6px; text-decoration: none;">Start Shopping</a>
                </div>
            <?php else: ?>
                <div class="cart-items" id="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item" data-cart-id="<?php echo $item['cart_id']; ?>">
                            <img src="<?php echo htmlspecialchars(getProductImage($item['image'] ?? '')); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 onerror="this.src='uploads/products/no-image.png'">
                            <div class="cart-item-details">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p><?php echo htmlspecialchars($item['description'] ?? ''); ?></p>
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

                <div class="cart-summary">
                    <h2>Total: KES <span id="cart-total"><?php echo number_format($total, 0); ?></span></h2>
                    <button class="checkout-btn" onclick="checkout()">Proceed to Checkout</button>
                    <button class="clear-cart-btn" onclick="clearCart()" style="background: #dc3545; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-left: 10px;">Clear Cart</button>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include "footer.php"; ?>

    <script>
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
