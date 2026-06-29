<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WittyMart – Smart Shopping for Witty Minds</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">
                <img src="images/Witty Mart.png" alt="WittyMart Logo">
                <h1>WittyMart Shop</h1>
            </div>
            
            <nav id="main-nav">
                <ul class="nav-links" id="nav-links">
                    <li><a href="index.html" class="active">Home</a></li>
                    <li><a href="shop.html">Shop</a></li>
                    <li><a href="cart.html">Cart</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="terms.html">Terms</a></li>
                    <li><a href="home.html">Account</a></li>
                    <li><button class="theme-toggle" onclick="toggleTheme()" id="theme-icon" title="Switch to Dark Mode"><i class="fas fa-sun"></i></button></li>
                </ul>
            </nav>
            
            <div class="header-actions">
                <button class="categories-btn" onclick="toggleSidebar()">
                    <i class="fas fa-th-list"></i>
                    <span>Categories</span>
                </button>
                <button class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle Menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-th-list" style="color:#05573c;"></i> Categories</h2>
            <button class="sidebar-close" onclick="toggleSidebar()">&times;</button>
        </div>
        <ul>
            <li><a href="breadcrumbs.html?#deals"><i class="fas fa-fire"></i> Hot Deals</a></li>
            <li><a href="breadcrumbs.html?#electronics"><i class="fas fa-mobile-alt"></i> Electronics</a></li>
            <li><a href="breadcrumbs.html?#fashion"><i class="fas fa-tshirt"></i> Fashion</a></li>
            <li><a href="breadcrumbs.html?#home-living"><i class="fas fa-home"></i> Home & Living</a></li>
            <li><a href="breadcrumbs.html?#beauty-health"><i class="fas fa-spa"></i> Beauty & Health</a></li>
            <li><a href="breadcrumbs.html?#sports-outdoors"><i class="fas fa-running"></i> Sports & Outdoors</a></li>
            <li><a href="breadcrumbs.html?#toys-hobbies"><i class="fas fa-gamepad"></i> Toys & Hobbies</a></li>
            <li><a href="breadcrumbs.html?#books-stationery"><i class="fas fa-book"></i> Books & Stationery</a></li>
            <li><a href="breadcrumbs.html?#automotive"><i class="fas fa-car"></i> Automotive</a></li>
            <li><a href="breadcrumbs.html?#grocery"><i class="fas fa-shopping-basket"></i> Grocery</a></li>
        </ul>
    </div>

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
            <div class="product-grid" style="
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: flex-start;
            margin-bottom: 30px;
        ">
                <div class="product">
                    <img src="images/watch3.jpg" alt="Product 1">
                    <h3>Smartphone Pro X</h3>
                    <p>Latest smartphone with incredible features.</p>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
                <div class="product">
                    <img src="images/head1.jpeg" alt="Product 2">
                    <h3>Noise Cancelling Headphones</h3>
                    <p>Immerse in sound with comfort and quality.</p>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
                <div class="product">
                    <img src="images/watch1.jpeg" alt="Product 3">
                    <h3>Fitness Smartwatch</h3>
                    <p>Track your health and fitness goals.</p>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
                <div class="product">
                    <img src="images/laptops.jpeg" alt="Product 4">
                    <h3>Smart Home Devices</h3>
                    <p>Make your home smarter with our devices.</p>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
                <div class="product">
                    <img src="images/smart.jpg" alt="Product 5">
                    <h3>Smartphone Pro X</h3>
                    <p>Latest smartphone with incredible features.</p>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>
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
            <div class="slider-container">
                <div class="slider-track" id="testimonialTrack">
                    <div class="slide1">
                        <blockquote>
                            <p>"WittyMart has completely changed how I shop. Fast delivery, great prices!"</p>
                            <cite>– Janet K.</cite>
                        </blockquote>
                    </div>
                    <div class="slide1">
                        <blockquote>
                            <p>"High quality and fantastic customer service. Highly recommended!"</p>
                            <cite>– David M.</cite>
                        </blockquote>
                    </div>
                    <div class="slide1">
                        <blockquote>
                            <p>"Nimefurahia huduma yenu! Vitu vilifika kwa wakati na ziko safi."</p>
                            <cite>– Achieng' O.</cite>
                        </blockquote>
                    </div>
                    <div class="slide1">
                        <blockquote>
                            <p>"Affordable and authentic products. Naipenda WittyMart sana!"</p>
                            <cite>– Brian Ochieng</cite>
                        </blockquote>
                    </div>
                    <div class="slide1">
                        <blockquote>
                            <p>"Customer care ilinishughulikia haraka. Siwezi nunua kwingine tena."</p>
                            <cite>– Mercy W.</cite>
                        </blockquote>
                    </div>
                    <div class="slide1">
                        <blockquote>
                            <p>"The variety and convenience are unmatched. Asante sana!"</p>
                            <cite>– Kevin Mwangi</cite>
                        </blockquote>
                    </div>
                </div>
            </div>
            <div class="slider-controls">
                <button onclick="prevTestimonial()">← Prev</button>
                <button onclick="nextTestimonial()">Next →</button>
            </div>
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
                <form id="newsletter-form">
                    <input type="email" placeholder="Enter your email" required>
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
                    <li><a href="index.html">Home</a></li>
                    <li><a href="shop.html">Shop</a></li>
                    <li><a href="cart.html">Cart</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="contact.html">Contact</a></li>
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
                    <li><a href="terms.html#privacy">Privacy Policy</a></li>
                    <li><a href="terms.html#terms">Terms of Service</a></li>
                    <li><a href="terms.html#returns">Return Policy</a></li>
                </ul>
            </div>
        </div>
        <div id="footer-bottom">
            <p>Built with 💖 by Witty Highbrow Technologies!</p>
        </div>
    </footer>
    <script src="script.js"></script>
</body>
</html>
