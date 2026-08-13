<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'toggle') {
        $product_id = intval($_GET['product_id'] ?? 0);
        
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        try {
            // Check if already in wishlist
            $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Remove from wishlist
                $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                echo json_encode(['success' => true, 'added' => false]);
            } else {
                // Add to wishlist
                $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $product_id]);
                echo json_encode(['success' => true, 'added' => true]);
            }
        } catch (PDOException $e) {
            error_log('Wishlist toggle error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit();
    }
}

// Get wishlist items
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $wishlist_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get wishlist error: ' . $e->getMessage());
    $wishlist_items = [];
}

$page_title = 'My Wishlist';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .wishlist-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .wishlist-header {
            margin-bottom: 30px;
        }
        
        .wishlist-header h1 {
            font-size: 28px;
            color: #333;
            margin: 0;
        }
        
        .wishlist-header p {
            color: #888;
            margin: 5px 0 0;
        }
        
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
        }
        
        .wishlist-item {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            text-align: center;
            padding: 15px;
            position: relative;
        }
        
        .wishlist-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .wishlist-item .image-container {
            width: 100%;
            height: 180px;
            overflow: hidden;
            border-radius: 8px;
            background: #f5f5f5;
        }
        
        .wishlist-item .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .wishlist-item:hover .image-container img {
            transform: scale(1.05);
        }
        
        .wishlist-item .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(220, 53, 69, 0.9);
            color: #fff;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .wishlist-item .remove-btn:hover {
            background: #dc3545;
            transform: scale(1.1);
        }
        
        .wishlist-item h3 {
            font-size: 16px;
            margin: 10px 0 5px;
            color: #333;
        }
        
        .wishlist-item .price {
            font-size: 18px;
            font-weight: 700;
            color: #05573c;
            display: block;
            margin: 5px 0;
        }
        
        .wishlist-item .category {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .wishlist-item .add-to-cart {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }
        
        .wishlist-item .add-to-cart:hover:not(:disabled) {
            background: #03402c;
        }
        
        .wishlist-item .add-to-cart:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .wishlist-item .add-to-cart.added {
            background: #28a745;
        }
        
        .wishlist-item .add-to-cart.error {
            background: #dc3545;
        }
        
        .wishlist-item .stock-badge {
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
        
        .empty-wishlist {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .empty-wishlist i {
            font-size: 60px;
            display: block;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-wishlist h3 {
            font-size: 24px;
            color: #555;
            margin-bottom: 10px;
        }
        
        .empty-wishlist .btn-primary {
            display: inline-block;
            padding: 10px 30px;
            background: #05573c;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .empty-wishlist .btn-primary:hover {
            background: #03402c;
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
            .wishlist-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .wishlist-item .image-container {
                height: 140px;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>
    
    <div id="toast" class="toast"></div>
    
    <main>
        <div class="wishlist-container">
            <div class="wishlist-header">
                <h1><i class="fas fa-heart" style="color: #dc3545;"></i> My Wishlist</h1>
                <p><?php echo count($wishlist_items); ?> items in your wishlist</p>
            </div>
            
            <?php if (!empty($wishlist_items)): ?>
                <div class="wishlist-grid">
                    <?php foreach ($wishlist_items as $item): ?>
                        <div class="wishlist-item" data-product-id="<?php echo $item['id']; ?>">
                            <button class="remove-btn" onclick="removeFromWishlist(<?php echo $item['id']; ?>)">
                                <i class="fas fa-times"></i>
                            </button>
                            
                            <a href="product.php?id=<?php echo $item['id']; ?>">
                                <div class="image-container">
                                    <img src="<?php echo htmlspecialchars(getProductImageUrl($item)); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         onerror="this.src='uploads/products/no-image.png'">
                                </div>
                            </a>
                            
                            <span class="category"><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></span>
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <span class="price">Ksh <?php echo number_format($item['price'], 2); ?></span>
                            <span class="stock-badge <?php echo ($item['stock'] ?? 0) > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo ($item['stock'] ?? 0) > 0 ? 'In Stock' : 'Out of Stock'; ?>
                            </span>
                            <button class="add-to-cart" 
                                    data-product-id="<?php echo $item['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($item['name']); ?>"
                                    <?php echo ($item['stock'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i> 
                                <?php echo ($item['stock'] ?? 0) > 0 ? 'Add to Cart' : 'Out of Stock'; ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-wishlist">
                    <i class="fas fa-heart" style="color: #ddd;"></i>
                    <h3>Your wishlist is empty</h3>
                    <p>Start adding items you love to your wishlist!</p>
                    <a href="shop.php" class="btn-primary">
                        <i class="fas fa-shopping-bag"></i> Start Shopping
                    </a>
                </div>
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
            void toast.offsetWidth;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // ============================================
        // REMOVE FROM WISHLIST
        // ============================================
        function removeFromWishlist(productId) {
            if (!confirm('Remove this item from your wishlist?')) return;
            
            const item = document.querySelector(`.wishlist-item[data-product-id="${productId}"]`);
            
            fetch('wishlist.php?action=toggle&product_id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && !data.added) {
                        if (item) {
                            item.style.transition = 'opacity 0.3s ease';
                            item.style.opacity = '0';
                            setTimeout(() => {
                                item.remove();
                                // Update count
                                const count = document.querySelectorAll('.wishlist-item').length;
                                document.querySelector('.wishlist-header p').textContent = count + ' items in your wishlist';
                                if (count === 0) {
                                    location.reload();
                                }
                            }, 300);
                        }
                        showToast('Removed from wishlist', 'info');
                    } else {
                        showToast('Failed to remove item', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred', 'error');
                });
        }
        
        // ============================================
        // ADD TO CART FROM WISHLIST
        // ============================================
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function() {
                if (this.disabled) return;
                
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                const originalText = this.innerHTML;
                
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
                    showToast('An error occurred', 'error');
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.className = 'add-to-cart';
                        this.disabled = false;
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>
