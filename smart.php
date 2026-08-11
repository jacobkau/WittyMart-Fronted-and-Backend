<?php
require_once 'includes/config.php';

// Get smart picks products (limit to 8)
$smart_products = getSmartPicks(8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Picks - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Product image container */
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
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 40px;
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
        
        .no-products-message {
            text-align: center;
            padding: 40px 20px;
            color: #888;
            grid-column: 1 / -1;
        }
        
        .no-products-message i {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
            opacity: 0.3;
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
        <section class="products-section">
            <h2>Our <span>Smart Picks</span></h2>
            
            <div class="products-grid">
                <?php if (!empty($smart_products)): ?>
                    <?php foreach ($smart_products as $product): ?>
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
                <?php else: ?>
                    <div class="no-products-message">
                        <i class="fas fa-box-open"></i>
                        <p>No products available at the moment. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
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
// ADD TO CART FUNCTION
// ============================================
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Prevent double click
        if (this.disabled) {
            return;
        }
        
        // Check if user is logged in
        const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
        
        if (!isLoggedIn) {
            // Redirect to login page
            showToast('Please login to add items to your cart', 'info');
            setTimeout(() => {
                window.location.href = 'home.php';
            }, 1500);
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
                    const cartBadge = document.querySelector('.cart-count, .cart-badge');
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
