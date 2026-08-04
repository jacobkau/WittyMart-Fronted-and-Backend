<?php
// Include config to get cart count
require_once 'includes/config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'User';
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Get cart count
$cartCount = 0;
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $cartCount = intval($result['total'] ?? 0);
    } catch (PDOException $e) {
        error_log('Get cart count error: ' . $e->getMessage());
        $cartCount = 0;
    }
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
        /* Cart badge styles */
        .nav-links .cart-link {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -12px;
            background: #dc3545;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);
            transition: transform 0.3s ease;
        }
        
        .cart-badge.pulse {
            animation: pulse 0.5s ease;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.4); }
            100% { transform: scale(1); }
        }
        
        .cart-badge.empty {
            display: none;
        }
        
        /* Header actions cart icon with badge */
        .header-cart {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            color: #333;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .header-cart:hover {
            background: rgba(5, 87, 60, 0.1);
        }
        
        .header-cart .cart-icon {
            font-size: 20px;
        }
        
        .header-cart .cart-badge-sm {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);
        }
        
        .header-cart .cart-badge-sm.empty {
            display: none;
        }

        /* Dark mode */
        body.dark-mode .header-cart {
            color: #eee;
        }
        
        body.dark-mode .header-cart:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">
                <img src="images/logo.png" alt="WittyMart Logo">
                <h1>WittyMart Shop</h1>
            </div>
            
            <nav id="main-nav">
                <ul class="nav-links" id="nav-links">
                    <?php
                    // Get the current page filename
                    $current_page = basename($_SERVER['PHP_SELF']);
                    
                    // Define navigation links
                    $nav_links = [
                        'index.php' => 'Home',
                        'shop.php' => 'Shop',
                        'cart.php' => 'Cart',
                        'about.php' => 'About',
                        'contact.php' => 'Contact',
                        'terms.php' => 'Terms'
                    ];
                    
                    foreach ($nav_links as $page => $label):
                        $active_class = ($current_page == $page) ? 'active' : '';
                        $is_cart = ($page == 'cart.php');
                    ?>
                        <li>
                            <a href="<?php echo $page; ?>" class="<?php echo $active_class; ?> <?php echo $is_cart ? 'cart-link' : ''; ?>">
                                <?php echo $label; ?>
                                <?php if ($is_cart && $cartCount > 0): ?>
                                    <span class="cart-badge" id="cartBadge"><?php echo $cartCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    
                    <!-- Account / Login/Register link -->
                    <li>
                        <?php if ($isLoggedIn): ?>
                            <a href="welcome.php" class="<?php echo ($current_page == 'welcome.php') ? 'active' : ''; ?>">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($userName); ?>
                            </a>
                        <?php else: ?>
                            <a href="home.php" class="<?php echo ($current_page == 'home.php') ? 'active' : ''; ?>">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        <?php endif; ?>
                    </li>
                    
                    <!-- Admin link (only for admins) -->
                    <?php if ($isAdmin): ?>
                        <li><a href="admin/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                            <i class="fas fa-crown"></i> Admin
                        </a></li>
                    <?php endif; ?>
                    
                    <li><button class="theme-toggle" onclick="toggleTheme()" id="theme-icon" title="Switch to Dark Mode"><i class="fas fa-sun"></i></button></li>
                </ul>
            </nav>
            
            <div class="header-actions">
                <?php if ($isLoggedIn): ?>
                    <!-- Cart icon with badge in header actions -->
                    <a href="cart.php" class="header-cart" title="View Cart">
                        <i class="fas fa-shopping-cart cart-icon"></i>
                        <span class="cart-badge-sm <?php echo $cartCount > 0 ? '' : 'empty'; ?>" id="headerCartBadge">
                            <?php echo $cartCount > 0 ? $cartCount : ''; ?>
                        </span>
                    </a>
                    
                    <button class="logout-btn" onclick="logoutUser()" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                <?php endif; ?>
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
        <?php if ($isLoggedIn): ?>
            <div class="sidebar-user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-circle" style="font-size: 40px; color: #05573c;"></i>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($userName); ?></p>
                    <p class="user-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></p>
                </div>
            </div>
            <hr style="margin: 10px 20px; border-color: #e0e0e0;">
        <?php endif; ?>
        <ul>
            <li><a href="breadcrumbs.php?#deals"><i class="fas fa-fire"></i> Hot Deals</a></li>
            <li><a href="breadcrumbs.php?#electronics"><i class="fas fa-mobile-alt"></i> Electronics</a></li>
            <li><a href="breadcrumbs.php?#fashion"><i class="fas fa-tshirt"></i> Fashion</a></li>
            <li><a href="breadcrumbs.php?#home-living"><i class="fas fa-home"></i> Home & Living</a></li>
            <li><a href="breadcrumbs.php?#beauty-health"><i class="fas fa-spa"></i> Beauty & Health</a></li>
            <li><a href="breadcrumbs.php?#sports-outdoors"><i class="fas fa-running"></i> Sports & Outdoors</a></li>
            <li><a href="breadcrumbs.php?#toys-hobbies"><i class="fas fa-gamepad"></i> Toys & Hobbies</a></li>
            <li><a href="breadcrumbs.php?#books-stationery"><i class="fas fa-book"></i> Books & Stationery</a></li>
            <li><a href="breadcrumbs.php?#automotive"><i class="fas fa-car"></i> Automotive</a></li>
            <li><a href="breadcrumbs.php?#grocery"><i class="fas fa-shopping-basket"></i> Grocery</a></li>
        </ul>
        <?php if ($isLoggedIn): ?>
            <div class="sidebar-footer">
                <a href="logout.php" class="sidebar-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        <?php endif; ?>
    </div>

    <style>
        /* Header styles for logged-in state */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: #e74c3c;
            font-size: 20px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }
        
        /* Sidebar user info */
        .sidebar-user-info {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8f9fa;
            margin: 0 10px 10px 10px;
            border-radius: 8px;
        }
        
        .sidebar-user-info .user-details {
            flex: 1;
        }
        
        .sidebar-user-info .user-name {
            font-weight: 600;
            color: #333;
            margin: 0;
            font-size: 14px;
        }
        
        .sidebar-user-info .user-email {
            color: #888;
            margin: 2px 0 0;
            font-size: 12px;
        }
        
        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            margin-top: 10px;
        }
        
        .sidebar-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .sidebar-logout:hover {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }
        
        /* Dark mode styles */
        body.dark-mode .sidebar-user-info {
            background: #2a2a3e;
        }
        
        body.dark-mode .sidebar-user-info .user-name {
            color: #eee;
        }
        
        body.dark-mode .sidebar-user-info .user-email {
            color: #999;
        }
        
        body.dark-mode .sidebar-footer {
            border-top-color: #3a3a5e;
        }
        
        body.dark-mode .logout-btn {
            color: #e74c3c;
        }
        
        .nav-links a i {
            margin-right: 5px;
        }
    </style>

    <script>
        // ============================================
        // CART COUNT REFRESH FUNCTION
        // ============================================
        function refreshCartCount() {
            if (!<?php echo json_encode($isLoggedIn); ?>) return;
            
            fetch('cart.php?action=get_cart_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const count = data.count;
                        
                        // Update badge in nav
                        const badge = document.getElementById('cartBadge');
                        if (badge) {
                            if (count > 0) {
                                badge.textContent = count;
                                badge.classList.remove('empty');
                                badge.classList.add('pulse');
                                setTimeout(() => badge.classList.remove('pulse'), 500);
                            } else {
                                badge.classList.add('empty');
                            }
                        }
                        
                        // Update header cart badge
                        const headerBadge = document.getElementById('headerCartBadge');
                        if (headerBadge) {
                            if (count > 0) {
                                headerBadge.textContent = count;
                                headerBadge.classList.remove('empty');
                            } else {
                                headerBadge.classList.add('empty');
                            }
                        }
                    }
                })
                .catch(error => console.error('Error refreshing cart count:', error));
        }

        // ============================================
        // LOGOUT FUNCTION
        // ============================================
        function logoutUser() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // ============================================
        // AUTO-REFRESH CART COUNT
        // ============================================
        // Refresh cart count every 30 seconds
        setInterval(refreshCartCount, 30000);

        // Refresh cart count when page becomes visible again
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshCartCount();
            }
        });

        // ============================================
        // THEME TOGGLE
        // ============================================
        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            const icon = document.getElementById('theme-icon');
            if (icon) {
                icon.innerHTML = isDark ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
                icon.title = isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode';
            }
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        }

        // Load saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            const icon = document.getElementById('theme-icon');
            if (icon) {
                icon.innerHTML = '<i class="fas fa-moon"></i>';
                icon.title = 'Switch to Light Mode';
            }
        }

        // ============================================
        // MOBILE MENU TOGGLE
        // ============================================
        function toggleMenu() {
            const navLinks = document.getElementById('nav-links');
            navLinks.classList.toggle('active');
        }

        // ============================================
        // SIDEBAR TOGGLE
        // ============================================
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        }

        // Close sidebar when clicking overlay
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', function() {
                    toggleSidebar();
                });
            }
        });
    </script>
</body>
</html>
