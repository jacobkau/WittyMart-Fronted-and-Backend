<?php
require_once 'includes/config.php';

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

// ===== HELPER FUNCTION FOR PRODUCT IMAGE =====
function getProductImageUrl($image_path) {
    if (empty($image_path)) {
        return 'uploads/products/no-image.png';
    }
    return $image_path;
}

// ===== HELPER FUNCTION FOR STAR RATING =====
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
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .product-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
            padding: 15px;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .product-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-card h3 {
            font-size: 16px;
            margin: 10px 0 5px;
            color: #333;
        }

        .product-card .price {
            font-size: 18px;
            font-weight: 700;
            color: #05573c;
            margin: 5px 0;
        }

        .product-card .category {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-card .add-to-cart {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
            margin-top: 10px;
        }

        .product-card .add-to-cart:hover {
            background: #03402c;
        }

        .product-card .stock-badge {
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

        /* Responsive */
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }

            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .product-card img {
                height: 140px;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

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
                    <div class="slide">
                        <img src="images/laptops.jpeg" alt="Deal 4">
                        <div class="caption">
                            <h2>Smart Home Devices</h2>
                            <p>Make your home smarter with our devices.</p>
                        </div>
                    </div>
                </div>
                <button class="slider-nav prev" onclick="prevSlide()">‹</button>
                <button class="slider-nav next" onclick="nextSlide()">›</button>
            </div>
        </section>

        <!-- Featured Products -->
        <section>
            <h2>Featured <span>Products</span></h2>
            
            <?php if (!empty($featured_products)): ?>
                <div class="product-grid">
                    <?php foreach ($featured_products as $product): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars(getProductImageUrl($product['image'] ?? '')); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='uploads/products/no-image.png'">
                            <span class="category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price">Ksh <?php echo number_format($product['price'], 2); ?></div>
                            <span class="stock-badge <?php echo ($product['stock'] ?? 0) > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo ($product['stock'] ?? 0) > 0 ? 'In Stock' : 'Out of Stock'; ?>
                            </span>
                            <button class="add-to-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </div>
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
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-row">
            <div class="footer-card">
                <h2>WittyMart</h2>
                <p>Smart Shopping for Witty Minds!</p>
                <br>
                <p>© 2025 WittyMart. All rights reserved.</p>
            </div>
            <div class="footer-card">
                <h2>Subscribe to Our Newsletter</h2>
                <form id="newsletter-form" method="POST" action="subscribe.php">
                    <input type="email" name="email" placeholder="Enter your email" required>
                    <button type="submit">Subscribe</button>
                </form>
            </div>
            <div class="footer-card">
                <h2>Contact Us</h2>
                <p>Email: <a href="mailto:kaujacob4@gmail.com" style="color:#02c786;">kaujacob4@gmail.com</a></p>
                <p>Phone: +254 768 374 497</p>
                <p>Location: Nairobi, Kenya</p>
            </div>
        </div>
        <div class="footer-row">
            <div class="footer-card">
                <h2>Quick Links</h2>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="shop.php">Shop</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div class="footer-card">
                <h2>Follow Us</h2>
                <ul>
                    <li><a href="#">Facebook</a></li>
                    <li><a href="#">Twitter</a></li>
                    <li><a href="#">Instagram</a></li>
                </ul>
            </div>
            <div class="footer-card">
                <h2>Legal</h2>
                <ul>
                    <li><a href="terms.php#privacy">Privacy Policy</a></li>
                    <li><a href="terms.php#terms">Terms of Service</a></li>
                    <li><a href="terms.php#returns">Return Policy</a></li>
                </ul>
            </div>
        </div>
        <div id="footer-bottom">
            <p>Built with 💖 by Witty Highbrow Technologies!</p>
        </div>
    </footer>

    <script>
        // ===== ADD TO CART FUNCTION =====
        function addToCart(productId) {
            // You can implement AJAX cart functionality here
            alert('Product ' + productId + ' added to cart!');
            // Uncomment for AJAX:
            /*
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, quantity: 1 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added to cart!');
                }
            })
            .catch(error => console.error('Error:', error));
            */
        }

        // ===== HERO SLIDER =====
        let currentSlide = 0;
        const slides = document.querySelectorAll('#heroSlides .slide');
        const totalSlides = slides.length;

        function showSlide(index) {
            if (index >= totalSlides) currentSlide = 0;
            if (index < 0) currentSlide = totalSlides - 1;
            
            const offset = -currentSlide * 100;
            document.getElementById('heroSlides').style.transform = `translateX(${offset}%)`;
        }

        function nextSlide() {
            currentSlide++;
            showSlide(currentSlide);
        }

        function prevSlide() {
            currentSlide--;
            showSlide(currentSlide);
        }

        // Auto-slide every 5 seconds
        setInterval(nextSlide, 5000);

        // ===== TESTIMONIAL SLIDER (Alternative - if you prefer sliding) =====
        let currentTestimonial = 0;
        const testimonialSlides = document.querySelectorAll('.slide1');
        const totalTestimonials = testimonialSlides.length;

        function showTestimonial(index) {
            if (index >= totalTestimonials) currentTestimonial = 0;
            if (index < 0) currentTestimonial = totalTestimonials - 1;
            
            const track = document.getElementById('testimonialTrack');
            if (track) {
                const offset = -currentTestimonial * 100;
                track.style.transform = `translateX(${offset}%)`;
            }
        }

        function nextTestimonial() {
            currentTestimonial++;
            showTestimonial(currentTestimonial);
        }

        function prevTestimonial() {
            currentTestimonial--;
            showTestimonial(currentTestimonial);
        }
    </script>
</body>
</html>
