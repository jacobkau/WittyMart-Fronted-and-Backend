<?php
require_once 'includes/config.php';


// Get all products from database
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' OR p.status IS NULL
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get products error: ' . $e->getMessage());
    $products = [];
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Shop - WittyMart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        .product .add-to-cart.added {
            background: #28a745;
        }

        .product .add-to-cart.error {
            background: #dc3545;
        }

        .product .add-to-cart:disabled {
            opacity: 0.7;
            cursor: not-allowed;
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

        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .no-products i {
            font-size: 60px;
            display: block;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-products h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #555;
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
        <section class="products-section">
            <h2>Our <span>Smart Picks</span></h2>
            
            <?php if (!empty($products)): ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product">
                            <img src="<?php echo htmlspecialchars(getProductImageUrl($product['image'] ?? '')); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='uploads/products/no-image.png'">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
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
            <?php else: ?>
                <div class="no-products">
                    <i class="fas fa-box-open"></i>
                    <h3>No Products Available</h3>
                    <p>Products will appear here soon. Please check back later.</p>
                </div>
            <?php endif; ?>
        </section>
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
        // ADD TO CART FUNCTION (Prevents Double Click)
        // ============================================
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Prevent double click
                if (this.disabled) {
                    return;
                }
                
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                const originalText = this.innerHTML;
                const originalClass = this.className;
                
                // Disable button and show loading state
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                
                // Send AJAX request
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
                            const cartBadge = document.querySelector('.cart-count');
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
