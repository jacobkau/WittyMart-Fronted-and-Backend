<?php
require_once 'includes/config.php';

// ===== FETCH SMART PICKS (FEATURED / HOT DEALS) =====
$featured_products = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        INNER JOIN featured_products fp ON p.id = fp.product_id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' OR p.status IS NULL
        ORDER BY fp.display_order ASC, fp.created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $featured_products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Featured products error: ' . $e->getMessage());
    $featured_products = [];
}

// Fallback: if no featured, take the newest 8 products
if (empty($featured_products)) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' OR p.status IS NULL
            ORDER BY p.created_at DESC
            LIMIT 8
        ");
        $stmt->execute();
        $featured_products = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Fallback featured error: ' . $e->getMessage());
        $featured_products = [];
    }
}

// ===== GET ALL CATEGORIES =====
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get categories error: ' . $e->getMessage());
    $categories = [];
}

// ===== HELPER: Convert category name to slug (matches sidebar hrefs) =====
function categoryToSlug($name) {
    $slug = strtolower($name);
    $slug = preg_replace('/\s*&\s*/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// ===== GET PRODUCTS BY CATEGORY =====
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

$categoryProducts = [];
$categoriesWithProducts = [];

foreach ($categories as $category) {
    $products = getProductsByCategory($category['id'], 6);
    if (!empty($products)) {
        $categoryProducts[$category['id']] = $products;
        $categoriesWithProducts[] = $category;
    }
}

// ===== FETCH WISHLIST IDS =====
$wishlistIds = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $wishlistIds = array_column($stmt->fetchAll(), 'product_id');
    } catch (PDOException $e) {
        error_log('Get wishlist error: ' . $e->getMessage());
        $wishlistIds = [];
    }
}

$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// ===== HELPER: render a single product card (used everywhere) =====
function renderProductCard($product, $wishlistIds, $isLoggedIn) {
    $pid = $product['id'];
    $in_wishlist = in_array($pid, $wishlistIds);
    $stock_val = intval($product['stock'] ?? 0);
    $is_cloudinary = !empty($product['image_url']) && strpos($product['image_url'], 'cloudinary.com') !== false;
    ?>
    <div class="product" data-product-id="<?php echo $pid; ?>">
        <div class="product-image-container">
            <button class="wishlist-btn <?php echo $in_wishlist ? 'active' : ''; ?>"
                    data-product-id="<?php echo $pid; ?>"
                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                    title="<?php echo $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>"
                    aria-label="Toggle wishlist">
                <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
            </button>
            
            <a href="product.php?id=<?php echo $pid; ?>" class="product-link">
                <img src="<?php echo htmlspecialchars(getProductImage($product['image'] ?? null, $product['image_url'] ?? null)); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     onerror="this.src='uploads/products/no-image.png'">
            </a>
            
            <?php if ($is_cloudinary): ?>
                <span class="cloudinary-badge"><i class="fas fa-cloud"></i> Cloud</span>
            <?php endif; ?>
        </div>
        
        <a href="product.php?id=<?php echo $pid; ?>" class="product-link">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
        </a>
        <p><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)); ?>...</p>
        <span class="price">Ksh <?php echo number_format($product['price'], 0); ?></span>
        <span class="stock-badge <?php echo $stock_val > 0 ? 'in-stock' : 'out-of-stock'; ?>">
            <?php echo $stock_val > 0 ? 'In Stock' : 'Out of Stock'; ?>
        </span>
        
        <div class="product-actions">
            <button class="add-to-cart" 
                    data-product-id="<?php echo $pid; ?>"
                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                    <?php echo $stock_val <= 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-shopping-cart"></i> 
                <?php echo $stock_val > 0 ? 'Add to Cart' : 'Out of Stock'; ?>
            </button>
            
            <button class="add-to-wishlist-inline <?php echo $in_wishlist ? 'active' : ''; ?>"
                    data-product-id="<?php echo $pid; ?>"
                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                    title="<?php echo $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>"
                    aria-label="Toggle wishlist">
                <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
            </button>
        </div>
    </div>
    <?php
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
        html { scroll-behavior: smooth; }
        
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
        
        /* Top-left heart on the image */
        .wishlist-btn {
            position: absolute;
            top: 8px;
            left: 8px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 15px;
            transition: all 0.25s ease;
            z-index: 3;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        
        .wishlist-btn:hover {
            transform: scale(1.1);
            color: #e91e63;
            background: #fff;
        }
        
        .wishlist-btn.active {
            color: #e91e63;
            background: #fff;
        }
        
        .wishlist-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .product {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            padding: 15px;
            text-align: center;
            display: flex;
            flex-direction: column;
        }
        
        .product:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product .product-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product h3 {
            font-size: 16px;
            margin: 10px 0 5px;
            color: #333;
            transition: color 0.3s ease;
        }
        
        .product h3:hover {
            color: #05573c;
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
        
        /* ===== ACTION ROW ===== */
        .product-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
            align-items: stretch;
            width: 100%;
        }
        
        .product .add-to-cart {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 10px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 0;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .product .add-to-cart:hover:not(:disabled) { background: #03402c; }
        .product .add-to-cart:disabled { opacity: 0.7; cursor: not-allowed; }
        .product .add-to-cart.added { background: #28a745; }
        .product .add-to-cart.error { background: #dc3545; }
        
        /* ===== ICON-ONLY WISHLIST BUTTON (SQUARE) ===== */
        .product .add-to-wishlist-inline {
            background: #fff;
            color: #e91e63;
            border: 1.5px solid #e91e63;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.25s ease;
            width: 42px;
            min-width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .product .add-to-wishlist-inline:hover:not(:disabled) {
            background: #e91e63;
            color: #fff;
            transform: scale(1.05);
        }
        
        .product .add-to-wishlist-inline.active {
            background: #e91e63;
            color: #fff;
        }
        
        .product .add-to-wishlist-inline.active i {
            animation: heartPop 0.4s ease;
        }
        
        .product .add-to-wishlist-inline:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        @keyframes heartPop {
            0%   { transform: scale(1); }
            50%  { transform: scale(1.4); }
            100% { transform: scale(1); }
        }
        
        .product .stock-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
            margin-bottom: 8px;
            align-self: center;
        }
        
        .stock-badge.in-stock { background: #d4edda; color: #155724; }
        .stock-badge.out-of-stock { background: #f8d7da; color: #721c24; }
        
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
        
        .linkerbtn:hover { background: #03402c; }
        
        section {
            margin-bottom: 30px;
            scroll-margin-top: 140px;
        }
        
        section h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        section h2 i {
            margin-right: 10px;
        }
        
        #deals h2 {
            color: #d97706;
        }
        
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
        
        .no-categories-message {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .no-categories-message i {
            font-size: 60px;
            display: block;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .no-categories-message h3 {
            font-size: 24px;
            color: #555;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .product-image-container { height: 150px; }
            
            .product-actions {
                flex-direction: row;
                gap: 6px;
            }
            
            .product .add-to-cart {
                font-size: 12px;
                padding: 8px 8px;
            }
            
            .product .add-to-wishlist-inline {
                width: 38px;
                min-width: 38px;
                height: 38px;
                font-size: 14px;
            }
            
            section { scroll-margin-top: 180px; }
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
            
            <!-- ===== HOT DEALS / FEATURED SECTION ===== -->
            <?php if (!empty($featured_products)): ?>
                <section id="deals">
                    <h2>
                        <i class="fas fa-fire"></i> 
                        Hot Deals
                    </h2>
                    <div class="products-grid">
                        <?php foreach ($featured_products as $product): ?>
                            <?php renderProductCard($product, $wishlistIds, $isLoggedIn); ?>
                        <?php endforeach; ?>
                    </div>
                    <hr class="divider">
                </section>
            <?php endif; ?>
            
            <!-- ===== CATEGORY SECTIONS ===== -->
            <?php if (!empty($categoriesWithProducts)): ?>
                <?php foreach ($categoriesWithProducts as $category): ?>
                    <?php 
                    $products = $categoryProducts[$category['id']] ?? [];
                    $category_slug = categoryToSlug($category['name']);
                    ?>
                    <section id="<?php echo htmlspecialchars($category_slug); ?>">
                        <h2>
                            <i class="fas fa-tag" style="color:var(--primary-color, #05573c);"></i> 
                            <?php echo htmlspecialchars($category['name']); ?>
                        </h2>
                        
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
                                <?php renderProductCard($product, $wishlistIds, $isLoggedIn); ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($products) >= 6): ?>
                            <div class="linker">
                                <a href="category.php?slug=<?php echo $category_slug; ?>&id=<?php echo $category['id']; ?>" class="linkerbtn">
                                    See More <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="divider">
                    </section>
                <?php endforeach; ?>
            <?php elseif (empty($featured_products)): ?>
                <div class="no-categories-message">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Products Available</h3>
                    <p>Products will appear here soon. Please check back later.</p>
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

        // ============================================
        // TOAST
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
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (this.disabled) return;
                
                if (!isLoggedIn) {
                    showToast('Please login to add items to your cart', 'info');
                    setTimeout(() => window.location.href = 'home.php', 1500);
                    return;
                }
                
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                const originalText = this.innerHTML;
                const originalClass = this.className;
                
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                
                const formData = new FormData();
                formData.append('ajax_action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                
                fetch('cart.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.innerHTML = '<i class="fas fa-check"></i> Added!';
                        this.className = originalClass + ' added';
                        showToast(productName + ' added to cart!', 'success');
                        
                        if (data.cart_count !== undefined) {
                            const cartBadge = document.querySelector('.cart-badge-sm, .cart-badge');
                            if (cartBadge) cartBadge.textContent = data.cart_count;
                        }
                        
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.className = originalClass;
                            this.disabled = false;
                        }, 2000);
                    } else {
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
                .catch(() => {
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

        // ============================================
        // ADD TO WISHLIST (icon-only buttons)
        // ============================================
        function toggleWishlist(btn) {
            const productId = btn.dataset.productId;
            const productName = btn.dataset.productName;
            const wrapper = btn.closest('.product');
            
            if (!isLoggedIn) {
                showToast('Please login to use your wishlist', 'info');
                setTimeout(() => window.location.href = 'home.php', 1500);
                return;
            }
            
            const heartBtn = wrapper.querySelector('.wishlist-btn');
            const inlineBtn = wrapper.querySelector('.add-to-wishlist-inline');
            
            heartBtn.classList.add('loading');
            inlineBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('ajax_action', 'toggle_wishlist');
            formData.append('product_id', productId);
            
            fetch('wishlist.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const added = data.added;
                        
                        if (added) {
                            // Update top-left heart
                            heartBtn.classList.add('active');
                            heartBtn.querySelector('i').className = 'fas fa-heart';
                            heartBtn.title = 'Remove from wishlist';
                            
                            // Update inline icon-only button
                            inlineBtn.classList.add('active');
                            inlineBtn.querySelector('i').className = 'fas fa-heart';
                            inlineBtn.title = 'Remove from wishlist';
                            
                            showToast(productName + ' added to wishlist', 'success');
                        } else {
                            // Update top-left heart
                            heartBtn.classList.remove('active');
                            heartBtn.querySelector('i').className = 'far fa-heart';
                            heartBtn.title = 'Add to wishlist';
                            
                            // Update inline icon-only button
                            inlineBtn.classList.remove('active');
                            inlineBtn.querySelector('i').className = 'far fa-heart';
                            inlineBtn.title = 'Add to wishlist';
                            
                            showToast(productName + ' removed from wishlist', 'info');
                        }
                        
                        if (data.wishlist_count !== undefined) {
                            const badge = document.querySelector('.wishlist-badge, .wishlist-count');
                            if (badge) badge.textContent = data.wishlist_count;
                        }
                    } else {
                        showToast(data.message || 'Could not update wishlist', 'error');
                    }
                })
                .catch(() => {
                    showToast('An error occurred. Please try again.', 'error');
                })
                .finally(() => {
                    heartBtn.classList.remove('loading');
                    inlineBtn.disabled = false;
                });
        }
        
        document.querySelectorAll('.wishlist-btn, .add-to-wishlist-inline').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleWishlist(this);
            });
        });

        // ============================================
        // SMOOTH SCROLL TO HASH + CLOSE SIDEBAR
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            
            function scrollToHash(hash) {
                if (!hash || hash === '#') return;
                
                const target = document.querySelector(hash);
                if (!target) {
                    console.warn('Section not found:', hash);
                    return;
                }
                
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                target.style.transition = 'background-color 0.4s ease';
                const originalBg = target.style.backgroundColor;
                target.style.backgroundColor = 'rgba(5, 87, 60, 0.06)';
                setTimeout(() => {
                    target.style.backgroundColor = originalBg;
                }, 1200);
            }
            
            function closeSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                if (sidebar && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
                if (overlay && overlay.classList.contains('active')) {
                    overlay.classList.remove('active');
                }
                document.body.classList.remove('sidebar-open');
            }
            
            document.querySelectorAll('a[href*="breadcrumbs.php?#"], a[href^="#"]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (!href || href.indexOf('#') === -1) return;
                    
                    const hash = '#' + href.split('#')[1];
                    const currentPage = window.location.pathname.split('/').pop();
                    
                    if (currentPage === 'breadcrumbs.php' || href.startsWith('#')) {
                        e.preventDefault();
                        history.pushState(null, null, hash);
                        closeSidebar();
                        setTimeout(() => scrollToHash(hash), 200);
                    }
                });
            });
            
            if (window.location.hash) {
                setTimeout(() => scrollToHash(window.location.hash), 300);
            }
            
            window.addEventListener('hashchange', function() {
                scrollToHash(window.location.hash);
            });
        });
    </script>
</body>
</html>
