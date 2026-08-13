<?php
require_once 'includes/config.php';

$query = sanitize($_GET['q'] ?? '');
$category = intval($_GET['category'] ?? 0);
$min_price = floatval($_GET['min_price'] ?? 0);
$max_price = floatval($_GET['max_price'] ?? 0);

$products = [];
$search_terms = '';

if (!empty($query) || $category || $min_price || $max_price) {
    try {
        $sql = "
            SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE (p.status = 'active' OR p.status IS NULL)
        ";
        $params = [];
        
        if (!empty($query)) {
            $sql .= " AND (p.name ILIKE ? OR p.description ILIKE ?)";
            $search_term = "%{$query}%";
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if ($category) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category;
        }
        
        if ($min_price) {
            $sql .= " AND p.price >= ?";
            $params[] = $min_price;
        }
        
        if ($max_price) {
            $sql .= " AND p.price <= ?";
            $params[] = $max_price;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Search error: ' . $e->getMessage());
        $products = [];
    }
}

// Get categories for filter
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$page_title = 'Search Results';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .search-results-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .search-header {
            margin-bottom: 30px;
        }
        
        .search-header h1 {
            font-size: 28px;
            color: #333;
            margin: 0 0 5px 0;
        }
        
        .search-header p {
            color: #888;
            margin: 0;
        }
        
        .search-filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 30px;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .search-filters input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .search-filters input[type="text"]:focus {
            outline: none;
            border-color: #05573c;
        }
        
        .search-filters select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            background: #fff;
            min-width: 150px;
        }
        
        .search-filters select:focus {
            outline: none;
            border-color: #05573c;
        }
        
        .search-filters .btn-search {
            padding: 10px 25px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .search-filters .btn-search:hover {
            background: #03402c;
        }
        
        .search-filters .btn-clear {
            padding: 10px 20px;
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .search-filters .btn-clear:hover {
            background: #5a6268;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .no-results i {
            font-size: 60px;
            display: block;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .no-results h3 {
            font-size: 24px;
            color: #555;
            margin-bottom: 10px;
        }
        
        /* Product Grid Styles */
        .product-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-radius: 8px;
            background: #f5f5f5;
            margin-bottom: 10px;
            cursor: pointer;
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
            cursor: pointer;
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
            margin-top: 5px;
        }
        
        .product .add-to-cart:hover:not(:disabled) {
            background: #03402c;
        }
        
        .product .add-to-cart:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .product .add-to-cart.added {
            background: #28a745;
        }
        
        .product .add-to-cart.error {
            background: #dc3545;
        }
        
        .product .stock-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .stock-badge.in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-badge.out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 20px;
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
            
            .search-filters {
                flex-direction: column;
            }
            
            .search-filters input[type="text"],
            .search-filters select {
                width: 100%;
            }
            
            .search-header h1 {
                font-size: 22px;
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
        <div class="search-results-container">
            <div class="search-header">
                <h1>Search Results</h1>
                <?php if (!empty($query)): ?>
                    <p>Showing results for: <strong>"<?php echo htmlspecialchars($query); ?>"</strong></p>
                <?php endif; ?>
            </div>
            
            <!-- Search Filters -->
            <form class="search-filters" method="GET" action="search.php">
                <input type="text" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($query); ?>">
                <select name="category">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($query) || $category): ?>
                    <a href="search.php" class="btn-clear">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
            
            <!-- Results -->
            <?php if (!empty($products)): ?>
                <p style="color: #888; margin-bottom: 20px;">Found <?php echo count($products); ?> product(s)</p>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product">
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link">
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
                            </a>
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            </a>
                            <p><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 60)); ?>...</p>
                            <span class="price">Ksh <?php echo number_format($product['price'], 0); ?></span>
                            <span class="stock-badge <?php echo ($product['stock'] ?? 0) > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo ($product['stock'] ?? 0) > 0 ? 'In Stock' : 'Out of Stock'; ?>
                            </span>
                            <button class="add-to-cart" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    <?php echo ($product['stock'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i> 
                                <?php echo ($product['stock'] ?? 0) > 0 ? 'Add to Cart' : 'Out of Stock'; ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($query) || $category): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search terms or filters</p>
                    <a href="shop.php" style="display: inline-block; margin-top: 15px; padding: 10px 30px; background: #05573c; color: #fff; border-radius: 6px; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Browse All Products
                    </a>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>Search for products</h3>
                    <p>Enter a keyword above to find products</p>
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
        // ADD TO CART FUNCTION
        // ============================================
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent triggering the product link
                
                if (this.disabled) return;
                
                if (!isLoggedIn) {
                    showToast('Please login to add items to your cart', 'info');
                    setTimeout(function() {
                        window.location.href = 'home.php';
                    }, 1500);
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
                
                fetch('cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.innerHTML = '<i class="fas fa-check"></i> Added!';
                        this.className = originalClass + ' added';
                        showToast(productName + ' added to cart!', 'success');
                        
                        if (data.cart_count !== undefined) {
                            const cartBadge = document.querySelector('.cart-count, .cart-badge');
                            if (cartBadge) {
                                cartBadge.textContent = data.cart_count;
                            }
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
