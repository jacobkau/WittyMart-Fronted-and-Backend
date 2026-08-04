<?php
require_once 'includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Picks - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
   <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <main>
        <section class="products-section">
            <h2>Our <span>Smart Picks</span></h2>
            
            <?php
            // Include configuration
           require_once __DIR__ . '/includes/config.php';
            
            // Get smart picks products (limit to 8)
            $smart_products = getSmartPicks(8);
            ?>
            
            <div class="products-grid">
                <?php if (!empty($smart_products)): ?>
                    <?php foreach ($smart_products as $product): ?>
                        <div class="product">
                            <img src="<?php echo getProductImage($product['image'] ?? null); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" />
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p><?php echo htmlspecialchars($product['description']); ?></p>
                            <span class="price">Ksh <?php echo number_format($product['price'], 0); ?></span>
                            <button class="add-to-cart" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                Add to Cart
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products-message">
                        <p>No products available at the moment. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include "footer.php"; ?>
    <script src="script.js"></script>
</body>
</html>
