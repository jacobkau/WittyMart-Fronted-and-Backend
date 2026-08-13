<?php
require_once 'includes/config.php';

$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    header('Location: shop.php');
    exit();
}

// Get product details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND (p.status = 'active' OR p.status IS NULL)
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: shop.php');
        exit();
    }
    
    // Get related products (same category)
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE category_id = ? AND id != ? AND (status = 'active' OR status IS NULL)
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $product_id]);
    $related_products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Product details error: ' . $e->getMessage());
    header('Location: shop.php');
    exit();
}

$page_title = $product['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - WittyMart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>
    
    <main>
        <div class="product-detail">
            <div class="product-detail-grid">
                <div class="product-image">
                    <img src="<?php echo htmlspecialchars(getProductImageUrl($product)); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         onerror="this.src='uploads/products/no-image.png'">
                </div>
                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    <div class="product-meta">
                        <span class="category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                        <span class="stock <?php echo ($product['stock'] ?? 0) > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                            <?php echo ($product['stock'] ?? 0) > 0 ? 'In Stock' : 'Out of Stock'; ?>
                        </span>
                    </div>
                    <div class="price">Ksh <?php echo number_format($product['price'], 2); ?></div>
                    <div class="description"><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?></div>
                    <?php if (($product['stock'] ?? 0) > 0): ?>
                        <button class="add-to-cart" 
                                data-product-id="<?php echo $product['id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    <?php else: ?>
                        <button class="add-to-cart" disabled>Out of Stock</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($related_products)): ?>
                <div class="related-products">
                    <h2>Related Products</h2>
                    <div class="products-grid">
                        <?php foreach ($related_products as $related): ?>
                            <div class="product-card">
                                <a href="product.php?id=<?php echo $related['id']; ?>">
                                    <img src="<?php echo htmlspecialchars(getProductImageUrl($related)); ?>" 
                                         alt="<?php echo htmlspecialchars($related['name']); ?>">
                                    <h3><?php echo htmlspecialchars($related['name']); ?></h3>
                                    <div class="price">Ksh <?php echo number_format($related['price'], 2); ?></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>
