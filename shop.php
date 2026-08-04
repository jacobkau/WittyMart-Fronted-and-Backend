<?php
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Shop - WittyMart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
   <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <main>
        <section class="products-section">
            <h2>Our <span>Smart Picks</span></h2>
            <div class="products-grid">
                <!-- Product 1 -->
                <div class="product">
                    <img src="images/watch3.jpg" alt="Smart Watch" />
                    <h3>Smart Watch</h3>
                    <p>Keep track of time and health.</p>
                    <span class="price">Ksh 4,500</span>
                    <button class="add-to-cart" data-product="Smart Watch">Add to Cart</button>
                </div>

                <!-- Product 2 -->
                <div class="product">
                    <img src="images/head4.jpg" alt="Bluetooth Headphones" />
                    <h3>Bluetooth Headphones</h3>
                    <p>Immersive sound experience.</p>
                    <span class="price">Ksh 3,000</span>
                    <button class="add-to-cart" data-product="Bluetooth Headphones">Add to Cart</button>
                </div>

                <!-- Product 3 -->
                <div class="product">
                    <img src="images/mouse2.webp" alt="Wireless Mouse" />
                    <h3>Wireless Mouse</h3>
                    <p>Ergonomic and smooth control.</p>
                    <span class="price">Ksh 1,200</span>
                    <button class="add-to-cart" data-product="Wireless Mouse">Add to Cart</button>
                </div>

                <!-- Product 4 -->
                <div class="product">
                    <img src="images/smart.jpg" alt="Smartphone" />
                    <h3>Smartphone Pro</h3>
                    <p>Latest smartphone with amazing features.</p>
                    <span class="price">Ksh 25,000</span>
                    <button class="add-to-cart" data-product="Smartphone Pro">Add to Cart</button>
                </div>

                <!-- Product 5 -->
                <div class="product">
                    <img src="images/watch5.jpg" alt="Fitness Band" />
                    <h3>Fitness Band</h3>
                    <p>Track your fitness goals.</p>
                    <span class="price">Ksh 2,800</span>
                    <button class="add-to-cart" data-product="Fitness Band">Add to Cart</button>
                </div>

                <!-- Product 6 -->
                <div class="product">
                    <img src="images/laptops.jpeg" alt="Laptop" />
                    <h3>Ultrabook Laptop</h3>
                    <p>Lightweight and powerful performance.</p>
                    <span class="price">Ksh 55,000</span>
                    <button class="add-to-cart" data-product="Ultrabook Laptop">Add to Cart</button>
                </div>
            </div>
        </section>
    </main>

     <?php include "footer.php"; ?>
<script src="script.js"></script>
</body>
</html>
