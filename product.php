<?php
require_once 'includes/config.php';

$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    header('Location: shop.php');
    exit();
}

// ===== GET PRODUCT DETAILS =====
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
    
    // ===== GET RELATED PRODUCTS (same category) =====
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.category_id = ? AND p.id != ? AND (p.status = 'active' OR p.status IS NULL)
        ORDER BY p.created_at DESC
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $product_id]);
    $related_products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Product details error: ' . $e->getMessage());
    header('Location: shop.php');
    exit();
}

$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// ===== CHECK IF PRODUCT IS IN USER'S WISHLIST =====
$in_wishlist = false;
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $in_wishlist = (bool) $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Wishlist check error: ' . $e->getMessage());
    }
}

// ===== CHECK IF PRODUCT IS IN USER'S CART =====
$cart_quantity = 0;
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $row = $stmt->fetch();
        $cart_quantity = $row ? intval($row['quantity']) : 0;
    } catch (PDOException $e) {
        error_log('Cart check error: ' . $e->getMessage());
    }
}

$page_title = $product['name'];
$stock_val = intval($product['stock'] ?? 0);
$is_cloudinary = !empty($product['image_url']) && strpos($product['image_url'], 'cloudinary.com') !== false;
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
        
        .product-image {
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 8px;
            background: #f5f5f5;
        }
        
        .product-image .cloudinary-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(52, 72, 197, 0.9);
            color: #fff;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
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
        
        /* Wishlist button - matches homepage style */
        .product-info .wishlist-btn {
            background: #fff;
            border: 2px solid #e91e63;
            color: #e91e63;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
            justify-content: center;
        }
        
        .product-info .wishlist-btn:hover:not(:disabled) {
            background: #e91e63;
            color: #fff;
            transform: translateY(-1px);
        }
        
        .product-info .wishlist-btn.active {
            background: #e91e63;
            color: #fff;
        }
        
        .product-info .wishlist-btn.active i {
            animation: heartPop 0.4s ease;
        }
        
        .product-info .wishlist-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        @keyframes heartPop {
            0%   { transform: scale(1); }
            50%  { transform: scale(1.4); }
            100% { transform: scale(1); }
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
            width: 80px;
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
        
        /* Cart status hint */
        .cart-hint {
            display: inline-block;
            font-size: 13px;
            color: #05573c;
            background: #e8f5f0;
            padding: 4px 12px;
            border-radius: 12px;
            margin-left: 10px;
        }
        
        /* Related Products */
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
            background: #f5f5f5;
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
        
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { background: #28a745; }
        .toast.error   { background: #dc3545; }
        .toast.info    { background: #17a2b8; }
        
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
            
            .product-info .add-to-cart {
                max-width: 100%;
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
                
                <!-- ===== PRODUCT IMAGE ===== -->
                <div class="product-image">
                    <img src="<?php echo htmlspecialchars(getProductImage($product['image'] ?? null, $product['image_url'] ?? null)); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         onerror="this.src='uploads/products/no-image.png'">
                    <?php if ($is_cloudinary): ?>
                        <span class="cloudinary-badge">
                            <i class="fas fa-cloud"></i> Cloud
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- ===== PRODUCT INFO ===== -->
                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="product-meta">
                        <span class="category">
                            <i class="fas fa-tag"></i> 
                            <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                        </span>
                        <span class="stock <?php echo $stock_val > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                            <i class="fas <?php echo $stock_val > 0 ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo $stock_val > 0 ? 'In Stock (' . $stock_val . ')' : 'Out of Stock'; ?>
                        </span>
                        <?php if (!empty($product['sku'])): ?>
                            <span class="category">
                                <i class="fas fa-barcode"></i> SKU: <?php echo htmlspecialchars($product['sku']); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($product['supplier'])): ?>
                            <span class="category">
                                <i class="fas fa-truck"></i> <?php echo htmlspecialchars($product['supplier']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="price">Ksh <?php echo number_format($product['price'], 2); ?></div>
                    
                    <div class="description">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available for this product.')); ?>
                    </div>
                    
                    <!-- ===== QUANTITY SELECTOR ===== -->
                    <div class="quantity-selector">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" value="1" min="1" max="<?php echo max(1, $stock_val); ?>">
                        <?php if ($cart_quantity > 0): ?>
                            <span class="cart-hint">
                                <i class="fas fa-shopping-cart"></i>
                                <?php echo $cart_quantity; ?> already in cart
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ===== ACTION BUTTONS ===== -->
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <?php if ($stock_val > 0): ?>
                            <button class="add-to-cart" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        <?php else: ?>
                            <button class="add-to-cart" disabled>
                                <i class="fas fa-times-circle"></i> Out of Stock
                            </button>
                        <?php endif; ?>
                        
                        <button class="wishlist-btn <?php echo $in_wishlist ? 'active' : ''; ?>"
                                id="wishlistBtn"
                                data-product-id="<?php echo $product['id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                title="<?php echo $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                            <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                            <span class="wishlist-text"><?php echo $in_wishlist ? 'Saved' : 'Wishlist'; ?></span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- ===== RELATED PRODUCTS ===== -->
            <?php if (!empty($related_products)): ?>
                <div class="related-products">
                    <h2>Related Products</h2>
                    <div class="products-grid">
                        <?php foreach ($related_products as $related): ?>
                            <?php 
                            $related_img = getProductImage($related['image'] ?? null, $related['image_url'] ?? null);
                            $related_stock = intval($related['stock'] ?? 0);
                            ?>
                            <div class="product-card">
                                <a href="product.php?id=<?php echo $related['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($related_img); ?>" 
                                         alt="<?php echo htmlspecialchars($related['name']); ?>"
                                         onerror="this.src='uploads/products/no-image.png'">
                                    <h3><?php echo htmlspecialchars($related['name']); ?></h3>
                                    <div class="price">Ksh <?php echo number_format($related['price'], 2); ?></div>
                                    <span class="stock-badge <?php echo $related_stock > 0 ? 'in-stock' : 'out-of-stock'; ?>"
                                          style="display:inline-block; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600; margin-top:5px;
                                                 <?php echo $related_stock > 0 ? 'background:#d4edda;color:#155724;' : 'background:#f8d7da;color:#721c24;'; ?>">
                                        <?php echo $related_stock > 0 ? 'In Stock' : 'Out of Stock'; ?>
                                    </span>
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
        // ============================================
        // CONFIG
        // ============================================
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        const productId = <?php echo $product['id']; ?>;

        // ============================================
        // TOAST NOTIFICATION
        // ============================================
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            void toast.offsetWidth;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        // ============================================
        // ADD TO CART
        // ============================================
        document.querySelector('.add-to-cart')?.addEventListener('click', function() {
            if (this.disabled) return;
            
            const pid = this.dataset.productId;
            const productName = this.dataset.productName;
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            
            if (!isLoggedIn) {
                showToast('Please login to add items to your cart', 'info');
                setTimeout(() => window.location.href = 'home.php', 1500);
                return;
            }
            
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            const formData = new FormData();
            formData.append('ajax_action', 'add_to_cart');
            formData.append('product_id', pid);
            formData.append('quantity', quantity);
            
            fetch('cart.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.innerHTML = '<i class="fas fa-check"></i> Added!';
                        this.className = 'add-to-cart added';
                        showToast(productName + ' added to cart!', 'success');
                        
                        if (data.cart_count !== undefined) {
                            const cartBadge = document.querySelector('.cart-badge-sm, .cart-badge');
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
        // TOGGLE WISHLIST
        // ============================================
        const wishlistBtn = document.getElementById('wishlistBtn');
        if (wishlistBtn) {
            wishlistBtn.addEventListener('click', function() {
                if (!isLoggedIn) {
                    showToast('Please login to use your wishlist', 'info');
                    setTimeout(() => window.location.href = 'home.php', 1500);
                    return;
                }
                
                const btn = this;
                const icon = btn.querySelector('i');
                const label = btn.querySelector('.wishlist-text');
                const productName = btn.dataset.productName;
                
                btn.disabled = true;
                
                const formData = new FormData();
                formData.append('ajax_action', 'toggle_wishlist');
                formData.append('product_id', btn.dataset.productId);
                
                fetch('wishlist.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const added = data.added;
                            
                            if (added) {
                                btn.classList.add('active');
                                icon.className = 'fas fa-heart';
                                label.textContent = 'Saved';
                                btn.title = 'Remove from wishlist';
                                showToast(productName + ' added to wishlist', 'success');
                            } else {
                                btn.classList.remove('active');
                                icon.className = 'far fa-heart';
                                label.textContent = 'Wishlist';
                                btn.title = 'Add to wishlist';
                                showToast(productName + ' removed from wishlist', 'info');
                            }
                            
                            // Update header wishlist badge if present
                            if (data.wishlist_count !== undefined) {
                                const badge = document.querySelector('.wishlist-badge, .wishlist-count');
                                if (badge) badge.textContent = data.wishlist_count;
                            }
                        } else {
                            showToast(data.message || 'Could not update wishlist', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An error occurred. Please try again.', 'error');
                    })
                    .finally(() => {
                        btn.disabled = false;
                    });
            });
        }
    </script>
</body>
</html>
