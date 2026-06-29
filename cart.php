<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cart - WittyMart</title>
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
                    <li><a href="cart.html" class="active">Cart</a></li>
                    <li><a href="about.html">About</a></li>
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
        <section class="cart">
            <h1>Your <span>Shopping Cart</span></h1>
            <div class="cart-items" id="cart-items">
                <!-- Cart items will be dynamically added here -->
                <div class="cart-item">
                    <img src="images/watch3.jpg" alt="Smart Watch">
                    <div class="cart-item-details">
                        <h3>Smart Watch</h3>
                        <p>Keep track of time and health.</p>
                    </div>
                    <div class="cart-item-price">Ksh 4,500</div>
                    <div class="cart-item-actions">
                        <button onclick="updateQuantity(this, -1)">-</button>
                        <span class="quantity">1</span>
                        <button onclick="updateQuantity(this, 1)">+</button>
                        <button class="remove-btn" onclick="removeItem(this)">Remove</button>
                    </div>
                </div>
                <div class="cart-item">
                    <img src="images/head4.jpg" alt="Bluetooth Headphones">
                    <div class="cart-item-details">
                        <h3>Bluetooth Headphones</h3>
                        <p>Immersive sound experience.</p>
                    </div>
                    <div class="cart-item-price">Ksh 3,000</div>
                    <div class="cart-item-actions">
                        <button onclick="updateQuantity(this, -1)">-</button>
                        <span class="quantity">1</span>
                        <button onclick="updateQuantity(this, 1)">+</button>
                        <button class="remove-btn" onclick="removeItem(this)">Remove</button>
                    </div>
                </div>
                <div class="cart-item">
                    <img src="images/mouse2.webp" alt="Wireless Mouse">
                    <div class="cart-item-details">
                        <h3>Wireless Mouse</h3>
                        <p>Ergonomic and smooth control.</p>
                    </div>
                    <div class="cart-item-price">Ksh 1,200</div>
                    <div class="cart-item-actions">
                        <button onclick="updateQuantity(this, -1)">-</button>
                        <span class="quantity">1</span>
                        <button onclick="updateQuantity(this, 1)">+</button>
                        <button class="remove-btn" onclick="removeItem(this)">Remove</button>
                    </div>
                </div>
            </div>

            <div class="cart-summary">
                <h2>Total: KES <span id="cart-total">8,700</span></h2>
                <button class="checkout-btn" onclick="checkout()">Proceed to Checkout</button>
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
