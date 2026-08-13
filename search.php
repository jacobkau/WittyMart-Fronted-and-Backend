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
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
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
    }
}

// Get categories for filter
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$page_title = 'Search Results';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - WittyMart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>
    
    <main>
        <section class="search-results">
            <h2>Search Results</h2>
            
            <form method="GET" action="search.php" class="search-filters">
                <input type="text" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($query); ?>">
                <select name="category">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
            
            <?php if (!empty($products)): ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <a href="product.php?id=<?php echo $product['id']; ?>">
                                <img src="<?php echo htmlspecialchars(getProductImageUrl($product)); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="price">Ksh <?php echo number_format($product['price'], 2); ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-results">No products found matching your search.</p>
            <?php endif; ?>
        </section>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>
