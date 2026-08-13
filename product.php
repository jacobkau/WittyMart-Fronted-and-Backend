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

$isLoggedIn = isset($_SESSION['user_id']);
$page_title = $product['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .product-detail {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .product-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        
        .product-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .product-info h1 {
            font-size: 28px;
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .product-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .product-meta .category {
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            color: #666;
        }
        
        .product-meta .stock {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .product-meta .in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .product-meta .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        
        .product-info .price {
            font-size: 32px;
            font-weight: 700;
            color: #05573c;
            margin: 15px 0;
        }
        
        .product-info .description {
            color: #555;
            line-height: 1.8;
            margin: 20px 0;
        }
        
        .product-info .add-to-cart {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 300px;
        }
        
        .product-info .add-to-cart:hover:not(:disabled) {
            background: #03402c;
        }
        
        .product-info .add-to-cart:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .product-info .add-to-cart.added {
            background: #28a745;
        }
        
        .product-info .add-to-cart.error {
            background: #dc3545;
        }
        
        .product-info .wishlist-btn {
            background: none;
            border: 2px solid #ddd;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            margin-left: 10px;
        }
        
        .product-info .wishlist-btn:hover {
            border-color: #05573c;
            color: #05573c;
        }
        
        .product-info .wishlist-btn.active {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
        }
        
        .quantity-selector label {
            font-weight: 600;
            color: #555;
        }
        
        .quantity-selector input {
            width: 60px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            text-align: center;
            font-size: 16px;
        }
        
        .quantity-selector input:focus {
            outline: none;
            border-color: #05573c;
        }
        
        .related-products {
            margin-top: 40px;
        }
        
        .related-products h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .related-products .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .related-products .product-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            text-align: center;
            padding: 15px;
        }
        
        .related-products .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .related-products .product-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
        }
        
        .related-products .product-card h3 {
            font-size: 14px;
            margin: 10px 0 5px;
            color: #333;
        }
        
        .related-products .product-card .price {
            font-size: 16px;
            font-weight: 700;
            color: #05573c;
        }
        
        .related-products .product-card a {
            text-decoration: none;
            color: inherit;
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
            .product-detail-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px;
            }
            
            .product-image img {
                height: 250px;
            }
            
            .product-info h1 {
                font-size: 22px;
            }
            
            .product-info .price {
                font-size: 24px;
            }
            
            .related-products .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
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
                        <?php if (!empty($product['sku'])): ?>
                            <span class="category">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="price">Ksh <?php echo number_format($product['price'], 2); ?></div>
                    <div class="description"><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?></div>
                    
                    <div class="quantity-selector">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock'] ?? 10; ?>">
                    </div>
                    
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <?php if (($product['stock'] ?? 0) > 0): ?>
                            <button class="add-to-cart" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        <?php else: ?>
                            <button class="add-to-cart" disabled>Out of Stock</button>
                        <?php endif; ?>
                        
                        <button class="wishlist-btn" onclick="toggleWishlist(<?php echo $product['id']; ?>)">
                            <i class="fas fa-heart"></i> Wishlist
                        </button>
                    </div>
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
                                         alt="<?php echo htmlspecialchars($related['name']); ?>"
                                         onerror="this.src='uploads/products/no-image.png'">
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
    
    <script>
        // Pass PHP login status to JavaScript
        var isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        
        // ============================================
        // TOAST NOTIFICATION
        // ============================================
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            void toast.offsetWidth;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // ============================================
        // ADD TO CART
        // ============================================
        document.querySelector('.add-to-cart')?.addEventListener('click', function() {
            if (this.disabled) return;
            
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            
            if (!isLoggedIn) {
                showToast('Please login to add items to your cart', 'info');
                setTimeout(() => {
                    window.location.href = 'home.php';
                }, 1500);
                return;
            }
            
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            const formData = new FormData();
            formData.append('ajax_action', 'add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.innerHTML = '<i class="fas fa-check"></i> Added!';
                    this.className = 'add-to-cart added';
                    showToast(productName + ' added to cart!', 'success');
                    
                    if (data.cart_count !== undefined) {
                        const cartBadge = document.querySelector('.cart-badge');
                        if (cartBadge) cartBadge.textContent = data.cart_count;
                    }
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.className = 'add-to-cart';
                        this.disabled = false;
                    }, 2000);
                } else {
                    this.innerHTML = '<i class="fas fa-exclamation-circle"></i> Failed!';
                    this.className = 'add-to-cart error';
                    showToast(data.message || 'Failed to add to cart', 'error');
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.className = 'add-to-cart';
                        this.disabled = false;
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error!';
                this.className = 'add-to-cart error';
                showToast('An error occurred. Please try again.', 'error');
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.className = 'add-to-cart';
                    this.disabled = false;
                }, 2000);
            });
        });

        // ============================================
        // WISHLIST
        // ============================================
        function toggleWishlist(productId) {
            if (!isLoggedIn) {
                showToast('Please login to add to wishlist', 'info');
                setTimeout(() => {
                    window.location.href = 'home.php';
                }, 1500);
                return;
            }
            
            const btn = document.querySelector('.wishlist-btn');
            const icon = btn.querySelector('i');
            
            fetch('wishlist.php?action=toggle&product_id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.added) {
                            btn.classList.add('active');
                            icon.style.color = '#dc3545';
                            showToast('Added to wishlist!', 'success');
                        } else {
                            btn.classList.remove('active');
                            icon.style.color = '';
                            showToast('Removed from wishlist', 'info');
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
