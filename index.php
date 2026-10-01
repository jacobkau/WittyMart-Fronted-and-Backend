<?php
// ===== ENABLE ERROR REPORTING FOR DEBUGGING =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

// ===== CHECK IF PDO IS AVAILABLE =====
if (!isset($pdo)) {
    error_log('PDO not available in index.php');
    die('Database connection error. Please try again later.');
}

// ===== CHECK IF USER IS LOGGED IN =====
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';

// ===== FETCH SLIDER IMAGES =====
try {
    $stmt = $pdo->prepare("
        SELECT * FROM slider_images 
        WHERE status = 'active' 
        ORDER BY display_order ASC, created_at DESC
    ");
    $stmt->execute();
    $slider_images = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Slider images error: ' . $e->getMessage());
    $slider_images = [];
}

// ===== FETCH FEATURED PRODUCTS =====
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        INNER JOIN featured_products fp ON p.id = fp.product_id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' OR p.status IS NULL
        ORDER BY fp.display_order ASC, p.created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $featured_products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Featured products error: ' . $e->getMessage());
    $featured_products = [];
}

// If no featured products, fallback to regular products
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
        error_log('Fallback products error: ' . $e->getMessage());
        $featured_products = [];
    }
}

// ===== FETCH TESTIMONIALS =====
try {
    $stmt = $pdo->prepare("
        SELECT * FROM testimonials 
        WHERE status = 'active' 
        ORDER BY display_order ASC, created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $testimonials = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Testimonials error: ' . $e->getMessage());
    $testimonials = [];
}

// ===== FETCH CATEGORIES WITH PRODUCTS =====
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

// Get products for each category and filter out empty ones
$categoryProducts = [];
$categoriesWithProducts = [];

foreach ($categories as $category) {
    $products = getProductsByCategory($category['id'], 6);
    if (!empty($products)) {
        $categoryProducts[$category['id']] = $products;
        $categoriesWithProducts[] = $category;
    }
}

// ===== FETCH WISHLIST IDS FOR LOGGED-IN USER =====
$wishlistIds = [];
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $wishlistIds = array_column($stmt->fetchAll(), 'product_id');
    } catch (PDOException $e) {
        error_log('Get wishlist error: ' . $e->getMessage());
        $wishlistIds = [];
    }
}

// ===== FETCH CART QUANTITIES FOR LOGGED-IN USER =====
// Maps product_id => quantity in cart
$cartQuantities = [];
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        foreach ($stmt->fetchAll() as $row) {
            $cartQuantities[intval($row['product_id'])] = intval($row['quantity']);
        }
    } catch (PDOException $e) {
        error_log('Get cart quantities error: ' . $e->getMessage());
        $cartQuantities = [];
    }
}

// ===== HANDLE TESTIMONIAL SUBMISSION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'submit_testimonial') {
    header('Content-Type: application/json');
    
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Please login to submit a testimonial']);
        exit();
    }
    
    $content = sanitize($_POST['content'] ?? '');
    $rating = intval($_POST['rating'] ?? 5);
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Please write your testimonial']);
        exit();
    }
    
    if (strlen($content) < 10) {
        echo json_encode(['success' => false, 'message' => 'Testimonial must be at least 10 characters']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO testimonials (customer_name, content, rating, status, display_order, created_at) 
            VALUES (?, ?, ?, 'pending', 0, NOW())
        ");
        $stmt->execute([$userName, $content, $rating]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thank you for your testimonial! It will be reviewed and published soon.'
        ]);
    } catch (PDOException $e) {
        error_log('Testimonial submission error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
    exit();
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function getSliderImageUrl($image_path) {
    if (empty($image_path)) {
        return 'images/default-slide.jpg';
    }
    return $image_path;
}

function renderStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star" style="color: #ffc107;"></i>';
        } else {
            $html .= '<i class="far fa-star" style="color: #ddd;"></i>';
        }
    }
    return $html;
}

/**
 * Render a product card with wishlist heart + Add to Cart / In Cart + Plus button
 */
function renderHomeProductCard($product, $wishlistIds, $cartQuantities, $variant = 'featured') {
    $pid = $product['id'];
    $in_wishlist = in_array($pid, $wishlistIds);
    $cart_qty = intval($cartQuantities[$pid] ?? 0);
    $in_cart = $cart_qty > 0;
    $stock_val = intval($product['stock'] ?? 0);
    $is_cloudinary = !empty($product['image_url']) && strpos($product['image_url'], 'cloudinary.com') !== false;
    $cardClass = $variant === 'featured' ? 'product-card' : 'category-product';
    $imgWrapClass = $variant === 'featured' ? 'image-container' : 'product-image-container';
    ?>
    <div class="<?php echo $cardClass; ?>" data-product-id="<?php echo $pid; ?>">
        <div class="<?php echo $imgWrapClass; ?>">
            <!-- Wishlist heart (top-left) -->
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
        
        <?php if ($variant === 'featured'): ?>
            <span class="category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
        <?php endif; ?>
        
        <a href="product.php?id=<?php echo $pid; ?>" class="product-link">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
        </a>
        
        <?php if ($variant === 'category'): ?>
            <p><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)); ?>...</p>
        <?php endif; ?>
        
        <div class="price">Ksh <?php echo number_format($product['price'], $variant === 'featured' ? 2 : 0); ?></div>
        
        <span class="stock-badge <?php echo $stock_val > 0 ? 'in-stock' : 'out-of-stock'; ?>">
            <?php echo $stock_val > 0 ? 'In Stock' : 'Out of Stock'; ?>
        </span>
        
        <!-- ===== ACTION ROW ===== -->
        <div class="card-actions" data-product-id="<?php echo $pid; ?>">
            <?php if ($stock_val <= 0): ?>
                <button class="add-to-cart" disabled>
                    <i class="fas fa-times-circle"></i> Out of Stock
                </button>
            <?php elseif ($in_cart): ?>
                <!-- In-cart state: In Cart pill + Plus button -->
                <span class="in-cart-pill" title="Item is in your cart">
                    <i class="fas fa-check-circle"></i>
                    In Cart
                    <span class="qty-badge"><?php echo $cart_qty; ?></span>
                </span>
                
                <button type="button"
                        class="add-more-btn"
                        data-product-id="<?php echo $pid; ?>"
                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                        title="Add one more to cart">
                    <i class="fas fa-plus"></i>
                </button>
            <?php else: ?>
                <button class="add-to-cart" 
                        data-product-id="<?php echo $pid; ?>"
                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                    <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>
            <?php endif; ?>
            
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
    <title>WittyMart – Smart Shopping for Witty Minds</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Product Grid Styles */
        .product-grid,
        .category-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .product-card,
        .category-product {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
            padding: 15px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover,
        .category-product:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .product-card .image-container,
        .category-product .product-image-container {
            position: relative;
            width: 100%;
            height: 180px;
            overflow: hidden;
            border-radius: 8px;
            background: #f5f5f5;
            margin-bottom: 8px;
        }

        .product-card .image-container img,
        .category-product .product-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .image-container img,
        .category-product:hover .product-image-container img {
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

        .product-card .product-link,
        .category-product .product-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .product-card h3,
        .category-product h3 {
            font-size: 16px;
            margin: 10px 0 5px;
            color: #333;
            transition: color 0.3s ease;
        }

        .product-card h3:hover,
        .category-product h3:hover {
            color: #05573c;
        }

        .category-product p {
            font-size: 13px;
            color: #666;
            margin: 5px 0;
        }

        .product-card .price,
        .category-product .price {
            font-size: 18px;
            font-weight: 700;
            color: #05573c;
            margin: 5px 0;
            display: block;
        }

        .product-card .category {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stock-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin: 5px auto 8px;
            align-self: center;
        }

        .stock-badge.in-stock {
            background: #d4edda;
            color: #155724;
        }

        .stock-badge.out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        /* ===== ACTION ROW ===== */
        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
            align-items: stretch;
            width: 100%;
        }

        .card-actions .add-to-cart {
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

        .card-actions .add-to-cart:hover:not(:disabled) {
            background: #03402c;
        }

        .card-actions .add-to-cart:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .card-actions .add-to-cart.added {
            background: #28a745;
        }

        .card-actions .add-to-cart.error {
            background: #dc3545;
        }

        /* ===== IN-CART PILL + PLUS BUTTON ===== */
        .card-actions .in-cart-pill {
            background: #e8f5f0;
            color: #05573c;
            border: 1.5px solid #05573c;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-actions .in-cart-pill i {
            color: #05573c;
        }

        .card-actions .in-cart-pill .qty-badge {
            background: #05573c;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        .card-actions .add-more-btn {
            background: #05573c;
            color: #fff;
            border: none;
            width: 42px;
            min-width: 42px;
            height: 42px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s ease;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(5, 87, 60, 0.25);
        }

        .card-actions .add-more-btn:hover:not(:disabled) {
            background: #03402c;
            transform: scale(1.06);
        }

        .card-actions .add-more-btn:active:not(:disabled) {
            transform: scale(0.96);
        }

        .card-actions .add-more-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Icon-only wishlist button (square, pink border) */
        .card-actions .add-to-wishlist-inline {
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

        .card-actions .add-to-wishlist-inline:hover:not(:disabled) {
            background: #e91e63;
            color: #fff;
            transform: scale(1.05);
        }

        .card-actions .add-to-wishlist-inline.active {
            background: #e91e63;
            color: #fff;
        }

        .card-actions .add-to-wishlist-inline.active i {
            animation: heartPop 0.4s ease;
        }

        .card-actions .add-to-wishlist-inline:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        @keyframes heartPop {
            0%   { transform: scale(1); }
            50%  { transform: scale(1.4); }
            100% { transform: scale(1); }
        }

        /* Category Section Styles */
        .category-section {
            margin-bottom: 40px;
        }

        .category-section h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .category-section h2 i {
            margin-right: 10px;
            color: #05573c;
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

        /* Testimonial Styles */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .testimonial-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #05573c;
            transition: transform 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-3px);
        }

        .testimonial-card blockquote {
            margin: 0;
            font-style: italic;
            color: #555;
        }

        .testimonial-card blockquote p {
            font-size: 14px;
            line-height: 1.6;
        }

        .testimonial-card .customer-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }

        .testimonial-card .customer-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #05573c;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }

        .testimonial-card .customer-name {
            font-weight: 600;
            color: #333;
        }

        .testimonial-card .customer-stars {
            margin-top: 5px;
        }

        .testimonial-card .customer-stars i {
            font-size: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }

        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        /* Testimonial Form Styles */
        .testimonial-form-container {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            margin-top: 30px;
        }

        .testimonial-form-container h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }

        .testimonial-form-container .rating-select {
            margin-bottom: 15px;
        }

        .testimonial-form-container .rating-select label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .testimonial-form-container .star-rating {
            display: flex;
            gap: 8px;
            font-size: 32px;
            cursor: pointer;
            user-select: none;
        }

        .testimonial-form-container .star-rating i {
            color: #ddd;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .testimonial-form-container .star-rating i.active {
            color: #ffc107;
        }

        .testimonial-form-container .star-rating i:hover {
            color: #ffc107;
            transform: scale(1.15);
        }

        .testimonial-form-container .form-group {
            margin-bottom: 15px;
        }

        .testimonial-form-container .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .testimonial-form-container textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .testimonial-form-container textarea:focus {
            outline: none;
            border-color: #05573c;
        }

        .testimonial-form-container .btn-submit {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .testimonial-form-container .btn-submit:hover:not(:disabled) {
            background: #03402c;
        }

        .testimonial-form-container .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Hero Slider Styles */
        .hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
            align-items: stretch;
        }

        .about-shop {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .about-shop h1 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #333;
        }

        .about-shop h1 span {
            color: #05573c;
        }

        .about-shop p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .hero-slider {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: #000;
            min-height: 300px;
        }

        .slides {
            display: flex;
            transition: transform 0.5s ease-in-out;
            height: 100%;
        }

        .slide {
            min-width: 100%;
            position: relative;
            height: 100%;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            min-height: 300px;
            max-height: 400px;
        }

        .slide .caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: #fff;
        }

        .slide .caption h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .slide .caption p {
            font-size: 14px;
            opacity: 0.9;
        }

        .slide .caption .slider-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 20px;
            background: #05573c;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .slide .caption .slider-btn:hover {
            background: #03402c;
        }

        .slider-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.3);
            color: #fff;
            border: none;
            padding: 12px 18px;
            cursor: pointer;
            font-size: 24px;
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 10;
            backdrop-filter: blur(5px);
        }

        .slider-nav:hover {
            background: rgba(255,255,255,0.6);
            color: #333;
        }

        .slider-nav.prev {
            left: 15px;
        }

        .slider-nav.next {
            right: 15px;
        }

        .slider-dots {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }

        .slider-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            padding: 0;
        }

        .slider-dot.active {
            background: #fff;
            transform: scale(1.2);
        }

        /* About Shop Section */
        .about-shop-section {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 40px;
        }

        .about-shop-section h2 {
            margin-top: 0;
        }

        .about-shop-section ul {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .about-shop-section ul li {
            padding: 8px 0;
            color: #555;
        }

        .about-shop-section ul li::before {
            content: "✓ ";
            color: #05573c;
            font-weight: 700;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .about-shop {
                order: 2;
            }

            .hero-slider {
                order: 1;
                min-height: 250px;
            }

            .slide img {
                min-height: 250px;
                max-height: 300px;
            }
        }

        @media (max-width: 768px) {
            .product-grid,
            .category-products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }

            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .product-card .image-container,
            .category-product .product-image-container {
                height: 140px;
            }

            .card-actions {
                flex-direction: row;
                gap: 6px;
            }

            .card-actions .add-to-cart,
            .card-actions .in-cart-pill {
                font-size: 12px;
                padding: 8px 8px;
            }

            .card-actions .add-to-wishlist-inline,
            .card-actions .add-more-btn {
                width: 38px;
                min-width: 38px;
                height: 38px;
                font-size: 14px;
            }

            .about-shop h1 {
                font-size: 22px;
            }

            .slide .caption h2 {
                font-size: 18px;
            }

            .slide .caption p {
                font-size: 12px;
            }

            .slide .caption {
                padding: 20px;
            }

            .about-shop-section {
                padding: 20px;
            }

            .about-shop-section ul {
                grid-template-columns: 1fr;
            }
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

        .toast.success { background: #28a745; }
        .toast.error   { background: #dc3545; }
        .toast.info    { background: #17a2b8; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="about-shop">
                <h1>About <span>WittyMart</span> Shop</h1>
                <p>Welcome to WittyMart, your one-stop destination for smart shopping! At WittyMart, we believe in providing our customers with the best products at unbeatable prices. Our mission is to make shopping convenient, enjoyable, and rewarding for everyone.</p>
                <p>We offer a wide range of products across various categories, including electronics, fashion, home & living, beauty & health, sports & outdoors, and much more. Whether you're looking for the latest gadgets, trendy apparel, or everyday essentials, we've got you covered.</p>
            </div>
          
            <div class="hero-slider">
                <div class="slides" id="heroSlides">
                    <?php if (!empty($slider_images)): ?>
                        <?php foreach ($slider_images as $index => $slide): ?>
                            <div class="slide" data-index="<?php echo $index; ?>">
                                <img src="<?php echo htmlspecialchars(getSliderImageUrl($slide['image_path'])); ?>" 
                                     alt="<?php echo htmlspecialchars($slide['title']); ?>">
                                <div class="caption">
                                    <h2><?php echo htmlspecialchars($slide['title']); ?></h2>
                                    <p><?php echo htmlspecialchars($slide['subtitle'] ?? ''); ?></p>
                                    <?php if (!empty($slide['link']) && !empty($slide['button_text'])): ?>
                                        <a href="<?php echo htmlspecialchars($slide['link']); ?>" class="slider-btn">
                                            <?php echo htmlspecialchars($slide['button_text']); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="slide">
                            <img src="images/smart.jpg" alt="Deal 1">
                            <div class="caption">
                                <h2>Smartphone Pro X</h2>
                                <p>Grab the latest smartphone at 20% off!</p>
                            </div>
                        </div>
                        <div class="slide">
                            <img src="images/head1.jpeg" alt="Deal 2">
                            <div class="caption">
                                <h2>Noise Cancelling Headphones</h2>
                                <p>Experience sound like never before.</p>
                            </div>
                        </div>
                        <div class="slide">
                            <img src="images/watch5.jpg" alt="Deal 3">
                            <div class="caption">
                                <h2>Fitness Smartwatch</h2>
                                <p>Track your health goals in style.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($slider_images) > 1): ?>
                    <button class="slider-nav prev" onclick="prevSlide()">‹</button>
                    <button class="slider-nav next" onclick="nextSlide()">›</button>
                    <div class="slider-dots" id="sliderDots">
                        <?php foreach ($slider_images as $index => $slide): ?>
                            <button class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                                    onclick="goToSlide(<?php echo $index; ?>)"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Featured Products -->
        <section>
            <h2>Featured <span>Products</span></h2>
            
            <?php if (!empty($featured_products)): ?>
                <div class="product-grid">
                    <?php foreach ($featured_products as $product): ?>
                        <?php renderHomeProductCard($product, $wishlistIds, $cartQuantities, 'featured'); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Featured Products</h3>
                    <p>Featured products will appear here soon.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Categories Section -->
        <section>
            <h2>Shop by <span>Categories</span></h2>
            
            <?php if (!empty($categoriesWithProducts)): ?>
                <?php foreach ($categoriesWithProducts as $category): ?>
                    <?php 
                    $products = $categoryProducts[$category['id']] ?? [];
                    $category_slug = strtolower(str_replace(' ', '-', $category['name']));
                    ?>
                    <div class="category-section">
                        <h2>
                            <i class="fas fa-tag"></i> 
                            <?php echo htmlspecialchars($category['name']); ?>
                        </h2>
                        
                        <div class="category-products-grid">
                            <?php foreach ($products as $product): ?>
                                <?php renderHomeProductCard($product, $wishlistIds, $cartQuantities, 'category'); ?>
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
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-categories-message">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Categories Available</h3>
                    <p>No categories with products have been created yet. Please check back later.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- About Section -->
        <section class="about-shop-section">
            <h2>Why Choose <span>WittyMart</span>?</h2>
            <p>We offer a wide range of products across various categories, including electronics, fashion, home & living, beauty & health, sports & outdoors, and much more. Whether you're looking for the latest gadgets, trendy apparel, or everyday essentials, we've got you covered.</p>
            <ul>
                <li>High-quality products from trusted brands</li>
                <li>Exclusive deals and discounts</li>
                <li>Fast and reliable delivery</li>
                <li>Exceptional customer service</li>
                <li>Secure and hassle-free shopping experience</li>
            </ul>
            <p>Join thousands of satisfied customers who have made WittyMart their preferred shopping destination. Shop smart, shop WittyMart!</p>
        </section>

        <!-- Testimonials -->
        <section class="testimonials-slider">
            <h2>What Our <span>Customers Say</span></h2>
            
            <?php if (!empty($testimonials)): ?>
                <div class="testimonials-grid">
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="testimonial-card">
                            <blockquote>
                                <p>"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                            </blockquote>
                            <div class="customer-info">
                                <div class="customer-avatar">
                                    <?php 
                                    $name = $testimonial['customer_name'];
                                    $initials = '';
                                    $words = explode(' ', $name);
                                    foreach ($words as $word) {
                                        $initials .= strtoupper(substr($word, 0, 1));
                                    }
                                    echo substr($initials, 0, 2);
                                    ?>
                                </div>
                                <div>
                                    <div class="customer-name"><?php echo htmlspecialchars($testimonial['customer_name']); ?></div>
                                    <div class="customer-stars">
                                        <?php echo renderStars($testimonial['rating'] ?? 5); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comment-dots"></i>
                    <h3>No Testimonials Yet</h3>
                    <p>Customer testimonials will appear here soon.</p>
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
                <div class="testimonial-form-container">
                    <h3><i class="fas fa-pen"></i> Share Your Experience</h3>
                    <p style="color: #666; margin-bottom: 15px;">We'd love to hear about your experience with WittyMart!</p>
                    
                    <form id="testimonialForm">
                        <div class="rating-select">
                            <label>Your Rating</label>
                            <div class="star-rating" id="starRating">
                                <i class="fas fa-star" data-value="1" onclick="setRating(1)"></i>
                                <i class="fas fa-star" data-value="2" onclick="setRating(2)"></i>
                                <i class="fas fa-star" data-value="3" onclick="setRating(3)"></i>
                                <i class="fas fa-star" data-value="4" onclick="setRating(4)"></i>
                                <i class="fas fa-star" data-value="5" onclick="setRating(5)" style="color: #ffc107;"></i>
                            </div>
                            <input type="hidden" id="ratingValue" name="rating" value="5">
                            <span style="font-size: 14px; color: #888;">Selected: <span id="ratingDisplay">5</span> stars</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="testimonialContent">Your Testimonial</label>
                            <textarea id="testimonialContent" name="content" placeholder="Write your testimonial here..." required minlength="10"></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit" id="submitTestimonial">
                            <i class="fas fa-paper-plane"></i> Submit Testimonial
                        </button>
                    </form>
                    <div id="testimonialMessage" style="margin-top: 10px; display: none;"></div>
                </div>
            <?php else: ?>
                <div style="text-align: center; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <p style="margin: 0;">
                        <i class="fas fa-lock" style="color: #888;"></i> 
                        <a href="home.php" style="color: #05573c; font-weight: 600;">Login</a> to share your experience
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <?php include "footer.php"; ?>
    
<script>
    // ============================================
    // CONFIG
    // ============================================
    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

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
    // STAR RATING
    // ============================================
    function setRating(value) {
        document.getElementById('ratingValue').value = value;
        document.getElementById('ratingDisplay').textContent = value;
        
        const stars = document.querySelectorAll('#starRating i');
        stars.forEach(star => {
            const starValue = parseInt(star.dataset.value);
            if (starValue <= value) {
                star.style.color = '#ffc107';
                star.classList.add('active');
            } else {
                star.style.color = '#ddd';
                star.classList.remove('active');
            }
        });
    }

    // ============================================
    // HELPER: Update header cart badge
    // ============================================
    function updateCartBadge(count) {
        if (count === undefined) return;
        const badge = document.querySelector('.cart-badge-sm, .cart-badge');
        if (badge) badge.textContent = count;
    }

    // ============================================
    // HELPER: Swap Add to Cart → In Cart + Plus button
    // ============================================
    function switchCardToInCart(actionsRow, qty) {
        const productId = actionsRow.dataset.productId;
        const productName = actionsRow.querySelector('.add-to-cart')?.dataset.productName 
                          || actionsRow.querySelector('.add-more-btn')?.dataset.productName 
                          || '';
        
        // Replace the whole action row's content, keeping wishlist button at the end
        const wishlistBtn = actionsRow.querySelector('.add-to-wishlist-inline');
        
        // Remove the add-to-cart button
        const addBtn = actionsRow.querySelector('.add-to-cart');
        if (addBtn) addBtn.remove();
        
        // Build new HTML for the in-cart pill + plus button
        const html = `
            <span class="in-cart-pill" title="Item is in your cart">
                <i class="fas fa-check-circle"></i>
                In Cart
                <span class="qty-badge">${qty}</span>
            </span>
            <button type="button"
                    class="add-more-btn"
                    data-product-id="${productId}"
                    data-product-name="${productName.replace(/"/g, '&quot;')}"
                    title="Add one more to cart">
                <i class="fas fa-plus"></i>
            </button>
        `;
        
        // Insert before wishlist button
        if (wishlistBtn) {
            wishlistBtn.insertAdjacentHTML('beforebegin', html);
        } else {
            actionsRow.insertAdjacentHTML('afterbegin', html);
        }
        
        // Attach handler
        attachAddMoreHandler(actionsRow.querySelector('.add-more-btn'));
    }

    // ============================================
    // ADD TO CART (grid cards)
    // ============================================
    document.querySelectorAll('.card-actions .add-to-cart').forEach(button => {
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
            const actionsRow = this.closest('.card-actions');
            const originalText = this.innerHTML;
            
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
                        this.classList.add('added');
                        showToast(productName + ' added to cart!', 'success');
                        
                        updateCartBadge(data.cart_count);
                        
                        // Swap to in-cart state after brief pause
                        setTimeout(() => {
                            switchCardToInCart(actionsRow, 1);
                        }, 700);
                    } else {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        showToast(data.message || 'Failed to add to cart', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.innerHTML = originalText;
                    this.disabled = false;
                    showToast('An error occurred. Please try again.', 'error');
                });
        });
    });

    // ============================================
    // "+" BUTTON (add one more to cart)
    // ============================================
    function attachAddMoreHandler(btn) {
        if (!btn) return;
        
        btn.addEventListener('click', function(e) {
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
            const actionsRow = this.closest('.card-actions');
            const originalHTML = this.innerHTML;
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            const formData = new FormData();
            formData.append('ajax_action', 'add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            
            fetch('cart.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                    
                    if (data.success) {
                        // Update qty badge in the pill
                        const pill = actionsRow.querySelector('.in-cart-pill .qty-badge');
                        if (pill) {
                            const current = parseInt(pill.textContent) || 0;
                            pill.textContent = current + 1;
                        }
                        
                        updateCartBadge(data.cart_count);
                        showToast('1 more ' + productName + ' added to cart', 'success');
                    } else {
                        showToast(data.message || 'Could not add more', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                    showToast('An error occurred. Please try again.', 'error');
                });
        });
    }
    
    // Attach handler to any existing + buttons rendered server-side
    document.querySelectorAll('.card-actions .add-more-btn').forEach(attachAddMoreHandler);

    // ============================================
    // TESTIMONIAL SUBMISSION
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        const testimonialForm = document.getElementById('testimonialForm');
        if (testimonialForm) {
            testimonialForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const content = document.getElementById('testimonialContent');
                const submitBtn = document.getElementById('submitTestimonial');
                const messageDiv = document.getElementById('testimonialMessage');
                const rating = document.getElementById('ratingValue').value;
                
                if (content.value.trim().length < 10) {
                    messageDiv.style.display = 'block';
                    messageDiv.style.color = '#dc3545';
                    messageDiv.textContent = 'Please write at least 10 characters.';
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                
                const formData = new FormData();
                formData.append('ajax_action', 'submit_testimonial');
                formData.append('content', content.value.trim());
                formData.append('rating', rating);
                
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    messageDiv.style.display = 'block';
                    if (data.success) {
                        messageDiv.style.color = '#28a745';
                        messageDiv.textContent = data.message;
                        content.value = '';
                        setRating(5);
                        showToast(data.message, 'success');
                    } else {
                        messageDiv.style.color = '#dc3545';
                        messageDiv.textContent = data.message;
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.style.display = 'block';
                    messageDiv.style.color = '#dc3545';
                    messageDiv.textContent = 'An error occurred. Please try again.';
                    showToast('An error occurred. Please try again.', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Testimonial';
                });
            });
        }

        // ============================================
        // TOGGLE WISHLIST (heart icons)
        // ============================================
        function toggleWishlist(btn) {
            const productId = btn.dataset.productId;
            const productName = btn.dataset.productName;
            const wrapper = btn.closest('.product-card, .category-product');
            if (!wrapper) return;
            
            if (!isLoggedIn) {
                showToast('Please login to use your wishlist', 'info');
                setTimeout(() => window.location.href = 'home.php', 1500);
                return;
            }
            
            const heartBtn = wrapper.querySelector('.wishlist-btn');
            const inlineBtn = wrapper.querySelector('.add-to-wishlist-inline');
            
            if (heartBtn) heartBtn.classList.add('loading');
            if (inlineBtn) inlineBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('ajax_action', 'toggle_wishlist');
            formData.append('product_id', productId);
            
            fetch('wishlist.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const added = data.added;
                        
                        if (heartBtn) {
                            heartBtn.classList.toggle('active', added);
                            heartBtn.querySelector('i').className = added ? 'fas fa-heart' : 'far fa-heart';
                            heartBtn.title = added ? 'Remove from wishlist' : 'Add to wishlist';
                        }
                        
                        if (inlineBtn) {
                            inlineBtn.classList.toggle('active', added);
                            inlineBtn.querySelector('i').className = added ? 'fas fa-heart' : 'far fa-heart';
                            inlineBtn.title = added ? 'Remove from wishlist' : 'Add to wishlist';
                        }
                        
                        showToast(
                            added ? productName + ' added to wishlist' : productName + ' removed from wishlist',
                            added ? 'success' : 'info'
                        );
                        
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
                    if (heartBtn) heartBtn.classList.remove('loading');
                    if (inlineBtn) inlineBtn.disabled = false;
                });
        }
        
        document.querySelectorAll('.wishlist-btn, .add-to-wishlist-inline').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleWishlist(this);
            });
        });
    });

    // ============================================
    // HERO SLIDER
    // ============================================
    let currentSlide = 0;
    const slides = document.querySelectorAll('#heroSlides .slide');
    const totalSlides = slides.length;
    let autoSlideInterval;

    function showSlide(index) {
        if (index >= totalSlides) currentSlide = 0;
        if (index < 0) currentSlide = totalSlides - 1;
        
        const offset = -currentSlide * 100;
        const slider = document.getElementById('heroSlides');
        if (slider) {
            slider.style.transform = `translateX(${offset}%)`;
        }
        
        document.querySelectorAll('.slider-dot').forEach((dot, i) => {
            dot.classList.toggle('active', i === currentSlide);
        });
    }

    function nextSlide() {
        currentSlide++;
        showSlide(currentSlide);
        resetAutoSlide();
    }

    function prevSlide() {
        currentSlide--;
        showSlide(currentSlide);
        resetAutoSlide();
    }

    function goToSlide(index) {
        currentSlide = index;
        showSlide(currentSlide);
        resetAutoSlide();
    }

    function resetAutoSlide() {
        clearInterval(autoSlideInterval);
        if (totalSlides > 1) {
            autoSlideInterval = setInterval(nextSlide, 5000);
        }
    }

    if (totalSlides > 0) {
        showSlide(0);
        if (totalSlides > 1) {
            autoSlideInterval = setInterval(nextSlide, 5000);
        }
    }

    const sliderContainer = document.querySelector('.hero-slider');
    if (sliderContainer) {
        sliderContainer.addEventListener('mouseenter', () => {
            clearInterval(autoSlideInterval);
        });
        
        sliderContainer.addEventListener('mouseleave', () => {
            if (totalSlides > 1) {
                autoSlideInterval = setInterval(nextSlide, 5000);
            }
        });
    }

    let touchStartX = 0;
    let touchEndX = 0;

    const sliderElement = document.getElementById('heroSlides');
    if (sliderElement) {
        sliderElement.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        sliderElement.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            const diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) {
                    nextSlide();
                } else {
                    prevSlide();
                }
            }
        }, { passive: true });
    }
</script>
</body>
</html>
