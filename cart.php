<?php
require_once 'includes/config.php';
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
            <div class="cart-items" id="cart-items">
                <!-- Cart items will be dynamically added here -->
                <div class="cart-item">
                    <img src="images/watch3.jpg" alt="Smart Watch">
                    <div class="cart-item-details">
                        <h3>Smart Watch</h3>
                        <p>Keep track of time and health.</p>
                    </div>
                    <div class="cart-item-price">Ksh 4,500</div>
                    <div class="cart-item-actions">
                        <button onclick="updateQuantity(this, -1)">-</button>
                        <span class="quantity">1</span>
                        <button onclick="updateQuantity(this, 1)">+</button>
                        <button class="remove-btn" onclick="removeItem(this)">Remove</button>
                    </div>
                </div>
                <div class="cart-item">
                    <img src="images/head4.jpg" alt="Bluetooth Headphones">
                    <div class="cart-item-details">
                        <h3>Bluetooth Headphones</h3>
                        <p>Immersive sound experience.</p>
                    </div>
                    <div class="cart-item-price">Ksh 3,000</div>
                    <div class="cart-item-actions">
                        <button onclick="updateQuantity(this, -1)">-</button>
                        <span class="quantity">1</span>
                        <button onclick="updateQuantity(this, 1)">+</button>
                        <button class="remove-btn" onclick="removeItem(this)">Remove</button>
                    </div>
                </div>
                <div class="cart-item">
                    <img src="images/mouse2.webp" alt="Wireless Mouse">
                    <div class="cart-item-details">
                        <h3>Wireless Mouse</h3>
                        <p>Ergonomic and smooth control.</p>
                    </div>
                    <div class="cart-item-price">Ksh 1,200</div>
                    <div class="cart-item-actions">
                        <button onclick="updateQuantity(this, -1)">-</button>
                        <span class="quantity">1</span>
                        <button onclick="updateQuantity(this, 1)">+</button>
                        <button class="remove-btn" onclick="removeItem(this)">Remove</button>
                    </div>
                </div>
            </div>

            <div class="cart-summary">
                <h2>Total: KES <span id="cart-total">8,700</span></h2>
                <button class="checkout-btn" onclick="checkout()">Proceed to Checkout</button>
            </div>
        </section>
    </main>

    <?php include "footer.php"; ?>

    <script src="script.js" defer></script>
</body>
</html>
