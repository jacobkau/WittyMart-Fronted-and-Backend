<?php
require_once 'includes/config.php';

// Make sure Cloudinary helper is loaded for getProductImage()
$helper_paths = [
    __DIR__ . '/includes/cloudinary_helper.php',
    __DIR__ . '/../includes/cloudinary_helper.php',
];
foreach ($helper_paths as $p) {
    if (file_exists($p)) {
        require_once $p;
        break;
    }
}

// Fallback if the helper isn't available for some reason
if (!function_exists('getProductImage')) {
    function getProductImage($image_name = null, $image_url = null) {
        if (!empty($image_url)) return $image_url;
        if (!empty($image_name)) {
            if (filter_var($image_name, FILTER_VALIDATE_URL)) return $image_name;
            if (defined('UPLOAD_URL')) return UPLOAD_URL . $image_name;
            return 'uploads/products/' . $image_name;
        }
        return (defined('UPLOAD_URL') ? UPLOAD_URL : 'uploads/products/') . 'no-image.png';
    }
}

$query     = sanitize($_GET['q'] ?? '');
$category  = intval($_GET['category'] ?? 0);
$min_price = floatval($_GET['min_price'] ?? 0);
$max_price = floatval($_GET['max_price'] ?? 0);

// ===== PAGINATION =====
$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset   = ($page - 1) * $per_page;

// Sort options
$sort = $_GET['sort'] ?? 'newest';
$order_sql = 'ORDER BY p.created_at DESC';
switch ($sort) {
    case 'oldest':      $order_sql = 'ORDER BY p.created_at ASC'; break;
    case 'price_low':   $order_sql = 'ORDER BY p.price ASC'; break;
    case 'price_high':  $order_sql = 'ORDER BY p.price DESC'; break;
    case 'name':        $order_sql = 'ORDER BY p.name ASC'; break;
    case 'newest':
    default:            $order_sql = 'ORDER BY p.created_at DESC'; break;
}

$products = [];
$total_products = 0;
$has_search = (!empty($query) || $category || $min_price || $max_price);

if ($has_search) {
    try {
        // Build WHERE
        $where = ["(p.status = 'active' OR p.status IS NULL)"];
        $params = [];

        if (!empty($query)) {
            $where[] = "(p.name ILIKE ? OR p.description ILIKE ? OR p.sku ILIKE ?)";
            $search_term = "%{$query}%";
            array_push($params, $search_term, $search_term, $search_term);
        }
        if ($category) {
            $where[] = "p.category_id = ?";
            $params[] = $category;
        }
        if ($min_price) {
            $where[] = "p.price >= ?";
            $params[] = $min_price;
        }
        if ($max_price) {
            $where[] = "p.price <= ?";
            $params[] = $max_price;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);

        // Count total
        $count_sql = "SELECT COUNT(*) FROM products p $where_sql";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total_products = intval($stmt->fetchColumn());

        // Fetch page
        $sql = "
            SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            $where_sql
            $order_sql
            LIMIT $per_page OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Search error: ' . $e->getMessage());
        $products = [];
    }
}

$total_pages = max(1, ceil($total_products / $per_page));

// ===== Cart quantities for logged-in user =====
$cartQuantities = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        foreach ($stmt->fetchAll() as $row) {
            $cartQuantities[intval($row['product_id'])] = intval($row['quantity']);
        }
    } catch (PDOException $e) {
        error_log('Get cart quantities error: ' . $e->getMessage());
    }
}

// ===== Wishlist IDs =====
$wishlistIds = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $wishlistIds = array_column($stmt->fetchAll(), 'product_id');
    } catch (PDOException $e) {
        error_log('Get wishlist error: ' . $e->getMessage());
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

// Helper: preserve filters in pagination URLs
function buildSearchQuery($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
        else $params[$k] = $v;
    }
    return http_build_query($params);
}
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
            margin-bottom: 24px;
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
        
        /* ===== Filter bar ===== */
        .search-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: #fff;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        
        .search-filters input[type="text"],
        .search-filters input[type="number"] {
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease;
            background: #fafafa;
        }
        
        .search-filters input[type="text"] {
            flex: 1 1 260px;
            min-width: 200px;
        }
        
        .search-filters input[type="number"] {
            width: 110px;
        }
        
        .search-filters input:focus,
        .search-filters select:focus {
            outline: none;
            border-color: #05573c;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }
        
        .search-filters select {
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            background: #fafafa;
            min-width: 140px;
        }
        
        .search-filters .btn-search {
            padding: 10px 22px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .search-filters .btn-search:hover {
            background: #03402c;
        }
        
        .search-filters .btn-clear {
            padding: 10px 18px;
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        
        .search-filters .btn-clear:hover {
            background: #5a6268;
        }
        
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            color: #888;
            font-size: 14px;
        }
        
        .results-info strong {
            color: #05573c;
        }
        
        /* ===== Product Grid ===== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
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
        
        .product-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-radius: 8px;
            background: #f5f5f5;
            margin-bottom: 10px;
        }
        
        .product-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
            display: block;
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
        
        /* Top-left heart */
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
        .wishlist-btn:hover { transform: scale(1.1); color: #e91e63; background: #fff; }
        .wishlist-btn.active { color: #e91e63; background: #fff; }
        .wishlist-btn.loading { pointer-events: none; opacity: 0.7; }
        
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
        
        .product h3:hover { color: #05573c; }
        
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
        
        .product .stock-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin: 5px auto 8px;
            align-self: center;
        }
        
        .stock-badge.in-stock { background: #d4edda; color: #155724; }
        .stock-badge.out-of-stock { background: #f8d7da; color: #721c24; }
        
        /* ===== Action row ===== */
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
        .card-actions .add-to-cart:hover:not(:disabled) { background: #03402c; }
        .card-actions .add-to-cart:disabled { opacity: 0.7; cursor: not-allowed; }
        .card-actions .add-to-cart.added { background: #28a745; }
        .card-actions .add-to-cart.error { background: #dc3545; }
        
        /* In-cart pill + plus */
        .in-cart-pill {
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
        .in-cart-pill .qty-badge {
            background: #05573c;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }
        
        .add-more-btn {
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
        .add-more-btn:hover:not(:disabled) { background: #03402c; transform: scale(1.06); }
        .add-more-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        
        /* Icon-only wishlist square */
        .add-to-wishlist-inline {
            background: #fff;
            color: #e91e63;
            border: 1.5px solid #e91e63;
            border-radius: 6px;
            cursor: pointer;
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
        .add-to-wishlist-inline:hover:not(:disabled) {
            background: #e91e63; color: #fff; transform: scale(1.05);
        }
        .add-to-wishlist-inline.active { background: #e91e63; color: #fff; }
        .add-to-wishlist-inline.active i { animation: heartPop 0.4s ease; }
        
        @keyframes heartPop {
            0%   { transform: scale(1); }
            50%  { transform: scale(1.4); }
            100% { transform: scale(1); }
        }
        
        /* ===== Pagination ===== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 10px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #e0e0e0;
            color: #555;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.15s ease;
        }
        
        .page-link:hover {
            background: #e8f5f0;
            border-color: #05573c;
            color: #05573c;
        }
        
        .page-link.active {
            background: #05573c;
            border-color: #05573c;
            color: #fff;
        }
        
        .page-link.disabled {
            opacity: 0.4;
            pointer-events: none;
        }
        
        .page-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            color: #999;
            font-weight: 600;
        }
        
        /* ===== No results ===== */
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
        
        /* Toast */
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
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            .product-image-container { height: 150px; }
            
            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }
            .search-filters input[type="text"],
            .search-filters input[type="number"],
            .search-filters select { width: 100%; }
            .search-header h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>
    
    <div id="toast" class="toast"></div>

    <main>
        <div class="search-results-container">
            <div class="search-header">
                <h1>Search Results</h1>
                <?php if (!empty($query)): ?>
                    <p>Showing results for: <strong>"<?php echo htmlspecialchars($query); ?>"</strong></p>
                <?php else: ?>
                    <p>Browse and filter products</p>
                <?php endif; ?>
            </div>
            
            <!-- ===== FILTERS ===== -->
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
                
                <input type="number" name="min_price" placeholder="Min Ksh" value="<?php echo $min_price ?: ''; ?>" step="1" min="0">
                <input type="number" name="max_price" placeholder="Max Ksh" value="<?php echo $max_price ?: ''; ?>" step="1" min="0">
                
                <select name="sort">
                    <option value="newest"     <?php echo $sort === 'newest'     ? 'selected' : ''; ?>>Newest</option>
                    <option value="oldest"     <?php echo $sort === 'oldest'     ? 'selected' : ''; ?>>Oldest</option>
                    <option value="price_low"  <?php echo $sort === 'price_low'  ? 'selected' : ''; ?>>Price ↑</option>
                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price ↓</option>
                    <option value="name"       <?php echo $sort === 'name'       ? 'selected' : ''; ?>>Name A-Z</option>
                </select>
                
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                
                <?php if ($has_search): ?>
                    <a href="search.php" class="btn-clear">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
            
            <!-- ===== RESULTS ===== -->
            <?php if (!empty($products)): ?>
                <div class="results-info">
                    <span>
                        Found <strong><?php echo $total_products; ?></strong> product<?php echo $total_products === 1 ? '' : 's'; ?>
                        <?php if ($total_pages > 1): ?>
                            · Page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <?php 
                        $pid = $product['id'];
                        $cart_qty = intval($cartQuantities[$pid] ?? 0);
                        $in_cart = $cart_qty > 0;
                        $in_wishlist = in_array($pid, $wishlistIds);
                        $stock_val = intval($product['stock'] ?? 0);
                        $is_cloudinary = !empty($product['image_url']) && strpos($product['image_url'], 'cloudinary.com') !== false;
                        ?>
                        <div class="product" data-product-id="<?php echo $pid; ?>">
                            <div class="product-image-container">
                                <button class="wishlist-btn <?php echo $in_wishlist ? 'active' : ''; ?>"
                                        data-product-id="<?php echo $pid; ?>"
                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        title="<?php echo $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                                    <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                                </button>
                                
                                <a href="product.php?id=<?php echo $pid; ?>" class="product-link">
                                    <img src="<?php echo htmlspecialchars(getProductImage($product['image'] ?? null, $product['image_url'] ?? null)); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.onerror=null; this.src='uploads/products/no-image.png';">
                                </a>
                                
                                <?php if ($is_cloudinary): ?>
                                    <span class="cloudinary-badge"><i class="fas fa-cloud"></i> Cloud</span>
                                <?php endif; ?>
                            </div>
                            
                            <a href="product.php?id=<?php echo $pid; ?>" class="product-link">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            </a>
                            <p><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 60)); ?>...</p>
                            <span class="price">Ksh <?php echo number_format($product['price'], 0); ?></span>
                            <span class="stock-badge <?php echo $stock_val > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo $stock_val > 0 ? 'In Stock' : 'Out of Stock'; ?>
                            </span>
                            
                            <div class="card-actions" data-product-id="<?php echo $pid; ?>">
                                <?php if ($stock_val <= 0): ?>
                                    <button class="add-to-cart" disabled>
                                        <i class="fas fa-times-circle"></i> Out of Stock
                                    </button>
                                <?php elseif ($in_cart): ?>
                                    <span class="in-cart-pill">
                                        <i class="fas fa-check-circle"></i> In Cart
                                        <span class="qty-badge"><?php echo $cart_qty; ?></span>
                                    </span>
                                    <button type="button" class="add-more-btn"
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
                                        title="<?php echo $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                                    <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- ===== PAGINATION ===== -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php 
                        $prev_disabled = $page <= 1;
                        ?>
                        <a href="<?php echo $prev_disabled ? '#' : '?' . buildSearchQuery(['page' => $page - 1]); ?>"
                           class="page-link <?php echo $prev_disabled ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <?php
                        $range = 2;
                        $start = max(1, $page - $range);
                        $end   = min($total_pages, $page + $range);
                        
                        if ($start > 1) {
                            echo '<a href="?' . buildSearchQuery(['page' => 1]) . '" class="page-link">1</a>';
                            if ($start > 2) echo '<span class="page-ellipsis">…</span>';
                        }
                        
                        for ($i = $start; $i <= $end; $i++) {
                            $active = $i === $page;
                            echo '<a href="' . ($active ? '#' : '?' . buildSearchQuery(['page' => $i])) . '" '
                               . 'class="page-link ' . ($active ? 'active' : '') . '">' . $i . '</a>';
                        }
                        
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) echo '<span class="page-ellipsis">…</span>';
                            echo '<a href="?' . buildSearchQuery(['page' => $total_pages]) . '" class="page-link">' . $total_pages . '</a>';
                        }
                        
                        $next_disabled = $page >= $total_pages;
                        ?>
                        <a href="<?php echo $next_disabled ? '#' : '?' . buildSearchQuery(['page' => $page + 1]); ?>"
                           class="page-link <?php echo $next_disabled ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($has_search): ?>
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
        
        function updateCartBadge(count) {
            if (count === undefined) return;
            const badge = document.querySelector('.cart-count, .cart-badge, .cart-badge-sm');
            if (badge) badge.textContent = count;
        }
        
        // ============================================
        // IN-CART SWAP
        // ============================================
        function switchCardToInCart(actionsRow, qty) {
            const productId = actionsRow.dataset.productId;
            const productName = actionsRow.querySelector('.add-to-cart')?.dataset.productName || '';
            const wishlistBtn = actionsRow.querySelector('.add-to-wishlist-inline');
            
            const addBtn = actionsRow.querySelector('.add-to-cart');
            if (addBtn) addBtn.remove();
            
            const html = `
                <span class="in-cart-pill">
                    <i class="fas fa-check-circle"></i> In Cart
                    <span class="qty-badge">${qty}</span>
                </span>
                <button type="button" class="add-more-btn"
                        data-product-id="${productId}"
                        data-product-name="${productName.replace(/"/g, '&quot;')}"
                        title="Add one more to cart">
                    <i class="fas fa-plus"></i>
                </button>
            `;
            
            if (wishlistBtn) {
                wishlistBtn.insertAdjacentHTML('beforebegin', html);
            } else {
                actionsRow.insertAdjacentHTML('afterbegin', html);
            }
            
            attachAddMoreHandler(actionsRow.querySelector('.add-more-btn'));
        }
        
        // ============================================
        // ADD TO CART
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
                
                const productId   = this.dataset.productId;
                const productName = this.dataset.productName;
                const actionsRow  = this.closest('.card-actions');
                const originalText = this.innerHTML;
                
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                
                const formData = new FormData();
                formData.append('ajax_action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                
                fetch('cart.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            this.innerHTML = '<i class="fas fa-check"></i> Added!';
                            this.classList.add('added');
                            showToast(productName + ' added to cart!', 'success');
                            updateCartBadge(data.cart_count);
                            setTimeout(() => switchCardToInCart(actionsRow, 1), 700);
                        } else {
                            this.innerHTML = originalText;
                            this.disabled = false;
                            showToast(data.message || 'Failed to add to cart', 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        this.innerHTML = originalText;
                        this.disabled = false;
                        showToast('An error occurred. Please try again.', 'error');
                    });
            });
        });
        
        // ============================================
        // "+" BUTTON
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
                
                const productId   = this.dataset.productId;
                const productName = this.dataset.productName;
                const actionsRow  = this.closest('.card-actions');
                const originalHTML = this.innerHTML;
                
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                const formData = new FormData();
                formData.append('ajax_action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                
                fetch('cart.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                        
                        if (data.success) {
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
                    .catch(err => {
                        console.error(err);
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                        showToast('An error occurred. Please try again.', 'error');
                    });
            });
        }
        
        document.querySelectorAll('.card-actions .add-more-btn').forEach(attachAddMoreHandler);
        
        // ============================================
        // WISHLIST
        // ============================================
        function toggleWishlist(btn) {
            const productId = btn.dataset.productId;
            const productName = btn.dataset.productName;
            const wrapper = btn.closest('.product');
            if (!wrapper) return;
            
            if (!isLoggedIn) {
                showToast('Please login to use your wishlist', 'info');
                setTimeout(() => window.location.href = 'home.php', 1500);
                return;
            }
            
            const heartBtn  = wrapper.querySelector('.wishlist-btn');
            const inlineBtn = wrapper.querySelector('.add-to-wishlist-inline');
            
            if (heartBtn) heartBtn.classList.add('loading');
            if (inlineBtn) inlineBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('ajax_action', 'toggle_wishlist');
            formData.append('product_id', productId);
            
            fetch('wishlist.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const added = data.added;
                        
                        if (heartBtn) {
                            heartBtn.classList.toggle('active', added);
                            heartBtn.querySelector('i').className = added ? 'fas fa-heart' : 'far fa-heart';
                        }
                        if (inlineBtn) {
                            inlineBtn.classList.toggle('active', added);
                            inlineBtn.querySelector('i').className = added ? 'fas fa-heart' : 'far fa-heart';
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
                .catch(() => showToast('An error occurred. Please try again.', 'error'))
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
    </script>
</body>
</html>
