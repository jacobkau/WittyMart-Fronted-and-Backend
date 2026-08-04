<?php
require_once 'includes/config.php';

// Get all categories
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
    $categories = [];
}

// Function to get products by category
function getProductsByCategory($category_id, $limit = 6) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.category_id = ? AND (p.status = 'active' OR p.status IS NULL)
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$category_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get products by category error: ' . $e->getMessage());
        return [];
    }
}

// Get products for each category
$categoryProducts = [];
foreach ($categories as $category) {
    $categoryProducts[$category['id']] = getProductsByCategory($category['id'], 6);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <main>
        <div class="products-section">
            
            <!-- Category Sections -->
            <?php foreach ($categories as $category): ?>
                <?php 
                $products = $categoryProducts[$category['id']] ?? [];
                $category_slug = strtolower(str_replace(' ', '-', $category['name']));
                ?>
                <section id="<?php echo $category_slug; ?>">
                    <h2>
                        <i class="fas fa-tag" style="color:var(--primary-color);"></i> 
                        <?php echo htmlspecialchars($category['name']); ?>
                    </h2>
                    
                    <?php if (!empty($products)): ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product">
                                    <img src="<?php echo htmlspecialchars(getProductImage($product['image'] ?? '')); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.src='uploads/products/no-image.png'">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)); ?>...</p>
                                    <span class="price">Ksh <?php echo number_format($product['price'], 0); ?></span>
                                    <button class="add-to-cart" 
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                        Add to Cart
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($products) >= 6): ?>
                            <div class="linker">
                                <a href="category.php?slug=<?php echo $category_slug; ?>&id=<?php echo $category['id']; ?>" class="linkerbtn">See more</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="text-align:center; color:#888; padding:20px 0;">
                            <i class="fas fa-box-open" style="font-size:24px; display:block; margin-bottom:10px;"></i>
                            No products in this category yet.
                        </p>
                    <?php endif; ?>
                    
                    <hr class="divider">
                </section>
            <?php endforeach; ?>
            
            <!-- Fallback if no categories exist -->
            <?php if (empty($categories)): ?>
                <section>
                    <h2><i class="fas fa-exclamation-circle" style="color:var(--primary-color);"></i> No Categories Found</h2>
                    <p style="text-align:center; color:#888; padding:20px 0;">
                        <i class="fas fa-folder-open" style="font-size:48px; display:block; margin-bottom:15px;"></i>
                        No categories have been created yet. Please check back later.
                    </p>
                </section>
            <?php endif; ?>
            
        </div>
    </main>
    
    <?php include "footer.php"; ?>

    <script>
        // Add to cart functionality with AJAX
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                const originalText = this.textContent;
                
                // Show loading state
                this.textContent = 'Adding...';
                this.disabled = true;
                
                // Send AJAX request to add to cart
                const formData = new FormData();
                formData.append('ajax_action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                
                fetch('cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        this.textContent = '✓ Added!';
                        this.style.background = '#28a745';
                        this.style.color = '#fff';
                        setTimeout(() => {
                            this.textContent = originalText;
                            this.style.background = '';
                            this.style.color = '';
                            this.disabled = false;
                        }, 2000);
                    } else {
                        // Show error
                        this.textContent = 'Failed!';
                        this.style.background = '#dc3545';
                        this.style.color = '#fff';
                        setTimeout(() => {
                            this.textContent = originalText;
                            this.style.background = '';
                            this.style.color = '';
                            this.disabled = false;
                        }, 2000);
                        alert(data.message || 'Failed to add to cart. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.textContent = 'Error!';
                    this.style.background = '#dc3545';
                    this.style.color = '#fff';
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.background = '';
                        this.style.color = '';
                        this.disabled = false;
                    }, 2000);
                    alert('An error occurred. Please try again.');
                });
            });
        });
    </script>
</body>
</html>
