<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - WittyMart</title>
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
                    <li><a href="index.html">Home</a></li>
                    <li><a href="shop.html">Shop</a></li>
                    <li><a href="cart.html">Cart</a></li>
                    <li><a href="about.html" class="active">About</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="terms.html">Terms</a></li>
                    <li><a href="home.html">Account</a></li>
                    <li><button class="theme-toggle" onclick="toggleTheme()" id="theme-icon">🌞</button></li>
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
            <h2><i class="fas fa-th-list" style="color:#ff6738;"></i> Categories</h2>
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
        <section class="about">
            <h1> About <span>WittyMart</span></h1>
            
            <p>WittyMart is your go-to smart shopping platform designed to serve the modern shopper. Whether you're hunting for daily essentials, tech gadgets, home goods, fashion, or unique finds, WittyMart connects you with top-quality products at competitive prices. We help witty minds like you save time, money, and energy by combining intelligent features with a smooth and secure online experience. Our vision is to redefine e-commerce in Kenya and beyond through innovation, convenience, and customer satisfaction.</p>
            
            <h2> Our Mission</h2>
            <p>To provide customers with a seamless, user-friendly, and intelligent shopping experience powered by modern web technology. We aim to empower every shopper with the tools to make informed decisions, enjoy fast delivery, and feel confident in their online purchases.</p>
            
            <h2>What We Offer</h2>
            <ul>
                <li><i class="fas fa-shopping-bag"></i> A wide range of curated products from verified vendors and brands.</li>
                <li><i class="fas fa-truck"></i> Reliable nationwide delivery services with real-time tracking.</li>
                <li><i class="fas fa-lock"></i> Secure payments via M-Pesa, cards, PayPal, and bank transfer.</li>
                <li><i class="fas fa-undo"></i> Easy return and refund policies with dedicated customer support.</li>
                <li><i class="fas fa-mobile-alt"></i> A responsive shopping interface for both mobile and desktop users.</li>
            </ul>
            
            <h2> Why Choose Us?</h2>
            <ul>
                <li><i class="fas fa-check-circle"></i> <strong>Wide selection:</strong> Browse thousands of top-rated, affordable products across multiple categories.</li>
                <li><i class="fas fa-lightbulb"></i> <strong>Smart features:</strong> Wishlist, product comparisons, reviews, and intelligent recommendations to help you shop better.</li>
                <li><i class="fas fa-bolt"></i> <strong>Fast and secure checkout:</strong> Optimized for speed, trust, and simplicity – no stress, no delays.</li>
                <li><i class="fas fa-headset"></i> <strong>Friendly local support:</strong> Our Kenyan-based support team is available 7 days a week for any issues or inquiries.</li>
                <li><i class="fas fa-heart" style="color:#05573c;"></i> <strong>Built with 💙:</strong> WittyMart is proudly powered by <em>Witty Highbrow Technologies</em>, a tech-driven company passionate about innovation and customer happiness.</li>
            </ul>
            
            <h2> Join the WittyMart Experience</h2>
            <p>We believe shopping should be smart, secure, and satisfying. Whether you're a first-time buyer or a loyal customer, WittyMart is here to serve you better every time. Start your journey with us today and discover the difference of shopping the witty way.</p>
            
            <!-- FAQ Section -->
            <div class="faq">
                <h2> Frequently Asked Questions</h2>
                
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">Do you offer delivery services, and how long does it take?</button>
                    <div class="faq-answer">
                        <p>Yes! We provide delivery services across the country using reliable courier partners. Standard delivery takes 2–5 business days, depending on your location. You will receive tracking information once your order is shipped.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">Can I return items if I change my mind?</button>
                    <div class="faq-answer">
                        <p>Absolutely. You can return items within 7 days of delivery, provided they are unused, in their original packaging, and in resellable condition. Some exclusions apply (e.g., perishable or hygiene-sensitive goods).</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">How do I track my order after purchase?</button>
                    <div class="faq-answer">
                        <p>Once your order is dispatched, we'll send you a confirmation email with a tracking link. You can also log into your WittyMart account and view the order status in your dashboard under "My Orders".</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">Do you offer customer support if I face issues?</button>
                    <div class="faq-answer">
                        <p>Yes, our support team is available 7 days a week via email, live chat, and phone. We're here to assist you with product queries, order issues, returns, and more.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">What payment methods do you accept?</button>
                    <div class="faq-answer">
                        <p>We accept multiple payment options including M-Pesa, credit/debit cards, PayPal, and direct bank transfers. All transactions are securely processed to protect your data.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">Is it safe to shop on WittyMart?</button>
                    <div class="faq-answer">
                        <p>Yes! WittyMart uses SSL encryption and secure payment gateways to protect your personal and payment information. Your privacy and security are our top priorities.</p>
                    </div>
                </div>
            </div>
            
            <!-- Contact Box -->
            <div class="contact-box">
                <h2> Contact Us</h2>
                <p><i class="fas fa-envelope"></i> Email: <a href="mailto:kaujacob4@gmail.com">kaujacob4@gmail.com</a></p>
                <p><i class="fas fa-phone"></i> Phone: <a href="tel:+254768374497">+254 768 374 497</a></p>
                <p><i class="fas fa-map-marker-alt"></i> Office: Nairobi, Kenya</p>
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
                <p>Email: <a href="mailto:kaujacob4@gmail.com">kaujacob4@gmail.com</a></p>
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
<script src="script.js" defer></script>
</body>
</html>
