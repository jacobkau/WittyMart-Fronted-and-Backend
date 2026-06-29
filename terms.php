<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Policies - WittyMart</title>
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
                    <li><a href="about.html">About</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="terms.html" class="active">Terms</a></li>
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
            <h2><i class="fas fa-th-list"></i> Categories</h2>
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
        <div class="top-cont">
            <h1> Terms & <span>Policies</span></h1>
            <p>Read our policies to understand how we operate</p>
        </div>
        
        <nav class="subnav">
            <a href="#" onclick="showPage('privacy')" class="active-link" id="privacyLink">
                Privacy Policy
            </a>
            <a href="#" onclick="showPage('terms')" id="termsLink">
                Terms & Conditions
            </a>
            <a href="#" onclick="showPage('returns')" id="returnsLink">
                 Return Policy
            </a>
        </nav>

        <!-- Privacy Policy Section -->
        <div id="privacy" class="subpage active">
            <h2> Privacy Policy</h2>
            <p>Welcome to WittyMart. We value your privacy and are committed to protecting your personal data. This Privacy Policy outlines how we collect, use, disclose, and safeguard your information when you visit our website or use our services. By using WittyMart, you agree to the practices described in this policy.</p>
            <ul>
                <li>
                    <strong> Information Collection:</strong>
                    We collect personal information when you create an account, make a purchase, or sign up for our newsletters and promotional offers. This includes, but is not limited to, your name, email address, phone number, billing address, shipping address, payment information, and browsing activity on our website. We also gather non-personal data such as your IP address, browser type, device type, and access times for improving your experience.
                </li>
                <li>
                    <strong> How We Use Your Information:</strong>
                    The information we collect is used to process orders, communicate with you about your orders, personalize your shopping experience, provide customer support, and send you relevant marketing materials. We may also use your data to enhance the functionality of our site, improve our products and services, and track user behavior for analytics.
                </li>
                <li>
                    <strong> Data Sharing and Disclosure:</strong>
                    We respect your privacy and will never sell, rent, or trade your personal information. However, we may share your information with trusted third parties who assist us in processing transactions, shipping orders, and offering customer support. We may also share your information to comply with legal obligations or to protect the rights, property, or safety of WittyMart and its users.
                </li>
                <li>
                    <strong> Cookies and Tracking Technologies:</strong>
                    We use cookies, web beacons, and other tracking technologies to collect information about your activity on our website. Cookies help us remember your preferences and improve site functionality, while also enabling us to deliver personalized advertising. You can manage your cookie preferences through your browser settings or through the cookie management options on our site.
                </li>
                <li>
                    <strong> Data Security:</strong>
                    We implement robust security measures to protect your personal information, including the use of encryption protocols during transactions. However, no security system is impenetrable, and while we take all reasonable precautions to protect your data, we cannot guarantee complete security due to the nature of the internet.
                </li>
                <li>
                    <strong> Your Rights:</strong> You have the right to access, correct, update, and delete your personal data at any time. If you wish to review, modify, or delete your information, or if you have any concerns about how we handle your data, please contact us at
                    s<a href="mailto:kaujacob4@gmail.com" style="color:#33ffbc" >kaujacob4@gmail.com</a>. You also have the right to opt-out of receiving marketing communications from us by clicking the unsubscribe link in our emails.
                </li>
                <li>
                    <strong> Changes to This Privacy Policy:</strong>
                    We reserve the right to update or change this Privacy Policy at any time. When we do, we will update the "Last Revised" date at the top of the policy. We encourage you to review this page periodically for any changes. Your continued use of our services after any modifications signifies your acceptance of the updated policy.
                </li>
            </ul>
        </div>

        <!-- Terms and Conditions Section -->
        <div id="terms" class="subpage">
            <h2> Terms & Conditions</h2>
            <p>By accessing or using WittyMart, you agree to abide by the terms and conditions outlined below. These terms govern your access to and use of our services, so please read them carefully. If you disagree with any part of these terms, you should not use our website or services.</p>
            <ul>
                <li>
                    <strong> Acceptance of Terms:</strong>
                    By using the WittyMart website, creating an account, or making a purchase, you agree to be bound by these Terms & Conditions. If you do not accept these terms, you may not access or use the services provided by WittyMart.
                </li>
                <li>
                    <strong>Use of the Website:</strong>
                    WittyMart grants you a limited, non-exclusive, non-transferable license to access and use the website for personal, non-commercial purposes. You agree not to use our website for any illegal or unauthorized purpose, including but not limited to violating intellectual property rights or transmitting harmful code (e.g., viruses).
                </li>
                <li>
                    <strong>Account Responsibility:</strong>
                    To access certain features of our website, you must create an account. You are responsible for maintaining the confidentiality of your account details, including your username and password. You must immediately notify WittyMart if you suspect unauthorized access or any security breach related to your account.
                </li>
                <li>
                    <strong> Product Availability:</strong>
                    We strive to keep product listings accurate and up-to-date; however, availability is not guaranteed. WittyMart reserves the right to limit quantities, discontinue items, or cancel orders in the event of out-of-stock products or errors in product descriptions.
                </li>
                <li>
                    <strong> Pricing and Payments:</strong>
                    All prices listed on WittyMart are in local currency and may change without prior notice. Prices are valid only at the time of purchase. We accept various forms of payment, including credit cards and PayPal. You agree to pay the total price, including any taxes and shipping fees, at the time of your order.
                </li>
                <li>
                    <strong> Shipping and Delivery:</strong>
                    We aim to process and ship orders promptly. Delivery times may vary depending on your location and the shipping method chosen. You agree to provide accurate shipping information and acknowledge that delays may occur due to unforeseen circumstances.
                </li>
                <li>
                    <strong> Termination:</strong>
                    WittyMart reserves the right to suspend or terminate your account and access to the website at any time without notice if we believe you have violated these terms or engaged in fraudulent activities. Upon termination, all rights granted to you under these terms will immediately cease.
                </li>
            </ul>
        </div>

        <!-- Return Policy Section -->
        <div id="returns" class="subpage">
            <h2>Return Policy</h2>
            <p>We want you to be fully satisfied with your purchase at WittyMart. If for any reason you're not happy with your order, our return policy allows you to return items within 7 days of receiving your order for a refund or exchange. Please review the following conditions:</p>
            <ul>
                <li>
                    <strong> Eligibility for Returns:</strong>
                    Items may be returned within 7 days from the date of receipt, provided they are unused, in original condition, and in the original packaging. Some items, such as personalized or perishable products, may not be eligible for return.
                </li>
                <li>
                    <strong> Return Process:</strong> To initiate a return, contact our customer service team at
                    <a href="mailto:kaujacob4@gmail.com">kaujacob4@gmail.com</a> with your order number, the reason for the return, and the item(s) you wish to return. Our team will provide you with return instructions and a return authorization number.
                </li>
                <li>
                    <strong> Refunds:</strong>
                    Once we receive the returned item and confirm it meets the return conditions, we will process your refund. Refunds will be issued to the original payment method within 7-10 business days. Please note that shipping fees are non-refundable.
                </li>
                <li>
                    <strong>Exchanges:</strong>
                    If you wish to exchange an item, you will need to return the original item for a refund and then place a new order for the replacement item. If the exchange is due to a defective or damaged product, we will cover the shipping cost for the replacement.
                </li>
                <li>
                    <strong> Damaged or Defective Items:</strong>
                    If your item arrives damaged or defective, please notify us within 48 hours of receiving your order. We will arrange for a replacement or issue a full refund, including any applicable shipping charges.
                </li>
                <li>
                    <strong>Non-Returnable Items:</strong>
                    The following items are not eligible for return: gift cards, downloadable software, and certain health and personal care products. Please check the product page for specific return restrictions.
                </li>
            </ul>
        </div>
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
                    <li><a href="#" onclick="showPage('privacy')">Privacy Policy</a></li>
                    <li><a href="#" onclick="showPage('terms')">Terms of Service</a></li>
                    <li><a href="#" onclick="showPage('returns')">Return Policy</a></li>
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
