<?php
include 'includes/config.php';
include 'includes/auth.php';
include 'includes/functions.php';

$page_title = "WittyMart - Smart Shopping for Witty Minds";
include 'header.php';
?>


<body>
  
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
    <?php include 'footer.php';?>
</body>
</html>
