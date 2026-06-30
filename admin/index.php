<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = "WittyMart - Smart Shopping for Witty Minds";
include_once __DIR__ .  '/../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">    
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <header>
        <div class="header-container">
            <div class="logo">
                <img src="images/logo.png" alt="WittyMart Logo">
                <h1>WittyMart Shop</h1>
            </div>
            <nav id="main-nav">
                <ul class="nav-links">
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="shop.php">Shop</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <?php if(isLoggedIn()): ?>
                        <li><a href="admin/dashboard.php">Dashboard</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="admin/login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <main>
        <section class="hero">
            <div class="hero-content">
                <h1>Welcome to WittyMart</h1>
                <p>Smart Shopping for Witty Minds!</p>
                <a href="shop.php" class="btn-primary">Shop Now</a>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="products-section">
            <h2>Featured Products</h2>
            <div class="products-grid">
                <?php
                $products = getFeaturedProducts(8);
                foreach($products as $product):
                ?>
                <div class="product">
                    <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
                    <h3><?php echo $product['name']; ?></h3>
                    <p><?php echo substr($product['description'], 0, 50); ?>...</p>
                    <span class="price">Ksh <?php echo number_format($product['price'], 2); ?></span>
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn-add">View Details</a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <p>&copy; 2026 WittyMart. All rights reserved.</p>
            <p>Built with 💖 by Witty Highbrow Technologies</p>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>
