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
    <style>
        /* Product image container for consistent sizing */
        .product-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-radius: 8px;
            background: #f5f5f5;
        }
        
        .product-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product:hover .product-image-container img {
            transform: scale(1.05);
        }
        
        .cloudinary-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(52, 72, 197, 0.9);
            color: white;
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            z-index: 2;
        }
        
        .product {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            padding: 15px;
            text-align: center;
        }
        
        .product:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product h3 {
            font-size: 16px;
            margin: 10px 0 5px;
            color: #333;
        }
        
        .product p {
            font-size: 13px;
            color: #666;
            margin: 5px 0;
        }
        
        .product .price {
            font-size: 18px;
            font-weight: 700;
            color: #05573c;
            display: block;
            margin: 8px 0;
        }
        
        .product .add-to-cart {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 200px;
        }
        
        .product .add-to-cart:hover:not(:disabled) {
            background: #03402c;
        }
        
        .product .add-to-cart:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 20px;
        }
        
        .divider {
            margin: 40px 0;
            border: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, #ddd, transparent);
        }
        
        .linker {
            text-align: center;
            margin-top: 10px;
        }
        
        .linkerbtn {
            display: inline-block;
            padding: 10px 30px;
            background: #05573c;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .linkerbtn:hover {
            background: #03402c;
        }
        
        section {
            margin-bottom: 30px;
        }
        
        section h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        section h2 i {
            margin-right: 10px;
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            z-index: 9999;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast.success {
            background: #28a745;
        }
        
        .toast.error {
            background: #dc3545;
        }
        
        .toast.info {
            background: #17a2b8;
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .product-image-container {
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

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
                        <i class="fas fa-tag" style="color:var(--primary-color, #05573c);"></i> 
                        <?php echo htmlspecialchars($category['name']); ?>
                    </h2>
                    
                    <?php if (!empty($products)): ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product">
                                    <div class="product-image-container">
                                        <img src="<?php echo htmlspecialchars(getProductImageUrl($product)); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             onerror="this.src='uploads/products/no-image.png'">
                                        <?php if (!empty($product['image_url'])): ?>
                                            <span class="cloudinary-badge">
                                                <i class="fas fa-cloud"></i> Cloud
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)); ?>...</p>
                                    <span class="price">Ksh <?php echo number_format($product['price'], 0); ?></span>
                                    <button class="add-to-cart" 
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($products) >= 6): ?>
                            <div class="linker">
                                <a href="category.php?slug=<?php echo $category_slug; ?>&id=<?php echo $category['id']; ?>" class="linkerbtn">
                                    See More <i class="fas fa-arrow-right"></i>
                                </a>
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
                    <h2><i class="fas fa-exclamation-circle" style="color:var(--primary-color, #05573c);"></i> No Categories Found</h2>
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
        // ============================================
        // TOAST NOTIFICATION
        // ============================================
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            
            // Trigger reflow
            void toast.offsetWidth;
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // ============================================
        // ADD TO CART FUNCTIONALITY
        // ============================================
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function() {
                // Prevent double click
                if (this.disabled) {
                    return;
                }
                
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                const originalText = this.innerHTML;
                const originalClass = this.className;
                
                // Show loading state
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                
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
                        // Success state
                        this.innerHTML = '<i class="fas fa-check"></i> Added!';
                        this.className = originalClass + ' added';
                        showToast(productName + ' added to cart!', 'success');
                        
                        // Update cart count if available
                        if (data.cart_count !== undefined) {
                            const cartBadge = document.querySelector('.cart-badge');
                            if (cartBadge) {
                                cartBadge.textContent = data.cart_count;
                            }
                        }
                        
                        // Reset after 2 seconds
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.className = originalClass;
                            this.disabled = false;
                        }, 2000);
                    } else {
                        // Error state
                        this.innerHTML = '<i class="fas fa-exclamation-circle"></i> Failed!';
                        this.className = originalClass + ' error';
                        showToast(data.message || 'Failed to add to cart', 'error');
                        
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.className = originalClass;
                            this.disabled = false;
                        }, 2000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error!';
                    this.className = originalClass + ' error';
                    showToast('An error occurred. Please try again.', 'error');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.className = originalClass;
                        this.disabled = false;
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>
