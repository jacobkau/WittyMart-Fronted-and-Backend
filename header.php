<?php
// Include config to get cart count
require_once 'includes/config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
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

// Get user initials for avatar
function getUserInitials($name) {
    $initials = '';
    $words = explode(' ', trim($name));
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
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
        /* ============================================
           TWO-ROW HEADER STYLES
           ============================================ */
        header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: box-shadow 0.3s ease;
        }
        
        body.dark-mode header {
            background: #1a1a2e;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        /* Top Row - Logo, Search, Actions */
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            max-width: 1400px;
            margin: 0 auto;
            gap: 15px;
            flex-wrap: wrap;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        body.dark-mode .header-top {
            border-bottom-color: rgba(255,255,255,0.05);
        }
        
        /* Bottom Row - Navigation */
        .header-bottom {
            background: #f8f9fa;
            padding: 0 20px;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        
        body.dark-mode .header-bottom {
            background: #15152a;
        }
        
        .header-bottom .nav-links {
            display: flex;
            align-items: center;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 2px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .header-bottom .nav-links li a {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .header-bottom .nav-links li a:hover {
            background: rgba(5, 87, 60, 0.08);
            color: #05573c;
        }
        
        .header-bottom .nav-links li a.active {
            background: #05573c;
            color: #fff !important;
        }
        
        body.dark-mode .header-bottom .nav-links li a {
            color: #eee;
        }
        
        body.dark-mode .header-bottom .nav-links li a:hover {
            background: rgba(255,255,255,0.08);
            color: #0a7a54;
        }
        
        body.dark-mode .header-bottom .nav-links li a.active {
            background: #0a7a54;
            color: #fff !important;
        }
        
        /* Logo */
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        
        .logo img {
            height: 40px;
            width: auto;
        }
        
        .logo h1 {
            font-size: 20px;
            font-weight: 700;
            color: #05573c;
            margin: 0;
            white-space: nowrap;
        }
        
        body.dark-mode .logo h1 {
            color: #0a7a54;
        }
        
        /* ============================================
           SEARCH BAR
           ============================================ */
        .search-wrapper {
            flex: 1;
            max-width: 500px;
            min-width: 200px;
            position: relative;
        }
        
        .search-form {
            display: flex;
            align-items: center;
            background: #f5f5f5;
            border-radius: 25px;
            padding: 2px 5px 2px 18px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .search-form:focus-within {
            background: #fff;
            border-color: #05573c;
            box-shadow: 0 0 0 3px rgba(5, 87, 60, 0.1);
        }
        
        body.dark-mode .search-form {
            background: #2a2a3e;
        }
        
        body.dark-mode .search-form:focus-within {
            background: #1a1a2e;
            border-color: #0a7a54;
            box-shadow: 0 0 0 3px rgba(10, 122, 84, 0.2);
        }
        
        .search-form input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 8px 0;
            font-size: 14px;
            outline: none;
            color: #333;
            min-width: 100px;
        }
        
        body.dark-mode .search-form input {
            color: #eee;
        }
        
        .search-form input::placeholder {
            color: #999;
        }
        
        body.dark-mode .search-form input::placeholder {
            color: #777;
        }
        
        .search-form .search-btn {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        
        .search-form .search-btn:hover {
            background: #03402c;
            transform: scale(1.02);
        }
        
        .search-form .search-btn i {
            font-size: 14px;
        }
        
        /* Search suggestions dropdown */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            max-height: 400px;
            overflow-y: auto;
            display: none;
            z-index: 1001;
            margin-top: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .search-suggestions.active {
            display: block;
        }
        
        .search-suggestions .suggestion-item {
            padding: 10px 18px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: #333;
        }
        
        .search-suggestions .suggestion-item:hover {
            background: #f8f9fa;
        }
        
        .search-suggestions .suggestion-item i {
            color: #05573c;
            font-size: 14px;
        }
        
        .search-suggestions .suggestion-item .product-name {
            flex: 1;
        }
        
        .search-suggestions .suggestion-item .product-price {
            color: #05573c;
            font-weight: 600;
            font-size: 13px;
        }
        
        .search-suggestions .suggestion-empty {
            padding: 20px;
            text-align: center;
            color: #888;
        }
        
        body.dark-mode .search-suggestions {
            background: #1a1a2e;
            border-color: #2a2a3e;
        }
        
        body.dark-mode .search-suggestions .suggestion-item {
            border-bottom-color: #2a2a3e;
            color: #eee;
        }
        
        body.dark-mode .search-suggestions .suggestion-item:hover {
            background: #2a2a3e;
        }
        
        /* ============================================
           HEADER ACTIONS
           ============================================ */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
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
        
        .login-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            background: #05573c;
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            background: #03402c;
        }
        
        .theme-toggle {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
            color: #555;
        }
        
        .theme-toggle:hover {
            background: rgba(0,0,0,0.05);
        }
        
        body.dark-mode .theme-toggle {
            color: #ddd;
        }
        
        body.dark-mode .theme-toggle:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .categories-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: 1px solid #e0e0e0;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            color: #333;
            transition: all 0.3s ease;
            font-size: 13px;
        }
        
        .categories-btn:hover {
            background: rgba(5, 87, 60, 0.08);
            border-color: #05573c;
            color: #05573c;
        }
        
        body.dark-mode .categories-btn {
            color: #eee;
            border-color: #3a3a5e;
        }
        
        body.dark-mode .categories-btn:hover {
            background: rgba(255,255,255,0.08);
            border-color: #0a7a54;
            color: #0a7a54;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #333;
            padding: 5px 10px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .menu-toggle:hover {
            background: rgba(0,0,0,0.05);
        }
        
        body.dark-mode .menu-toggle {
            color: #eee;
        }
        
        body.dark-mode .menu-toggle:hover {
            background: rgba(255,255,255,0.1);
        }

        /* ============================================
           USER DROPDOWN MENU
           ============================================ */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .user-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .user-dropdown .dropdown-toggle:hover {
            background: rgba(5, 87, 60, 0.08);
            color: #05573c;
        }
        
        body.dark-mode .user-dropdown .dropdown-toggle {
            color: #eee;
        }
        
        body.dark-mode .user-dropdown .dropdown-toggle:hover {
            background: rgba(255,255,255,0.08);
            color: #0a7a54;
        }
        
        .user-dropdown .dropdown-toggle .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #05573c;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }
        
        .user-dropdown .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 8px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            min-width: 220px;
            z-index: 1002;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }
        
        .user-dropdown .dropdown-menu.active {
            display: block;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu {
            background: #1a1a2e;
            border-color: #2a2a3e;
        }
        
        .user-dropdown .dropdown-menu .dropdown-header {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            background: #f8f9fa;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu .dropdown-header {
            background: #15152a;
            border-bottom-color: #2a2a3e;
        }
        
        .user-dropdown .dropdown-menu .dropdown-header .dropdown-user-name {
            font-weight: 600;
            color: #333;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu .dropdown-header .dropdown-user-name {
            color: #eee;
        }
        
        .user-dropdown .dropdown-menu .dropdown-header .dropdown-user-email {
            font-size: 12px;
            color: #888;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu .dropdown-header .dropdown-user-email {
            color: #999;
        }
        
        .user-dropdown .dropdown-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .user-dropdown .dropdown-menu .dropdown-item:hover {
            background: #f8f9fa;
            color: #05573c;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu .dropdown-item {
            color: #eee;
            border-bottom-color: #2a2a3e;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu .dropdown-item:hover {
            background: #2a2a3e;
            color: #0a7a54;
        }
        
        .user-dropdown .dropdown-menu .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .user-dropdown .dropdown-menu .dropdown-item i {
            width: 20px;
            text-align: center;
            color: #05573c;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu .dropdown-item i {
            color: #0a7a54;
        }
        
        .user-dropdown .dropdown-menu .dropdown-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 5px 0;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu .dropdown-divider {
            background: #2a2a3e;
        }
        
        .user-dropdown .dropdown-menu .dropdown-item.text-danger {
            color: #dc3545;
        }
        
        .user-dropdown .dropdown-menu .dropdown-item.text-danger i {
            color: #dc3545;
        }
        
        .user-dropdown .dropdown-menu .dropdown-item.text-danger:hover {
            background: #f8d7da;
            color: #c82333;
        }
        
        body.dark-mode .user-dropdown .dropdown-menu .dropdown-item.text-danger:hover {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        /* ============================================
           MOBILE RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .search-wrapper {
                flex: 1 1 100%;
                max-width: 100%;
                order: 3;
            }
            
            .header-top {
                gap: 10px;
            }
            
            .header-bottom .nav-links {
                display: none;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .header-bottom .nav-links.mobile-open {
                display: flex;
                flex-direction: column;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                padding: 15px 20px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                z-index: 1000;
                border-radius: 0 0 12px 12px;
                width: 100%;
                gap: 2px;
            }
            
            .header-bottom .nav-links.mobile-open li {
                width: 100%;
            }
            
            .header-bottom .nav-links.mobile-open li a {
                display: block;
                padding: 12px 15px;
                border-radius: 6px;
                width: 100%;
                font-size: 16px;
            }
            
            .header-bottom .nav-links.mobile-open li a i {
                margin-right: 10px;
                width: 20px;
                text-align: center;
            }
            
            body.dark-mode .header-bottom .nav-links.mobile-open {
                background: #1a1a2e;
                border-top: 1px solid #2a2a3e;
            }
            
            .header-bottom {
                position: relative;
                padding: 5px 20px;
                min-height: 48px;
                justify-content: flex-end;
            }
            
            .header-bottom .menu-toggle {
                display: block;
                margin-left: auto;
            }
            
            .header-bottom .nav-links.mobile-open {
                justify-content: flex-start;
            }
        }

        @media (max-width: 768px) {
            .logo h1 {
                font-size: 16px;
            }
            
            .logo img {
                height: 32px;
            }
            
            .search-form {
                border-radius: 20px;
                padding: 2px 5px 2px 14px;
            }
            
            .search-form .search-btn span {
                display: none;
            }
            
            .search-form .search-btn {
                padding: 8px 14px;
                border-radius: 50%;
            }
            
            .search-form input {
                font-size: 13px;
                padding: 6px 0;
            }
            
            .categories-btn span {
                display: none;
            }
            
            .categories-btn {
                padding: 6px 10px;
            }
            
            .header-top {
                padding: 8px 12px;
                gap: 8px;
            }
            
            .header-bottom {
                padding: 5px 12px;
            }
            
            .user-dropdown .dropdown-toggle .user-avatar {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }
            
            .user-dropdown .dropdown-toggle span:not(.user-avatar) {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .logo h1 {
                font-size: 14px;
            }
            
            .logo img {
                height: 28px;
            }
            
            .header-actions {
                gap: 4px;
            }
            
            .header-cart .cart-icon {
                font-size: 18px;
            }
            
            .search-form {
                padding: 2px 5px 2px 12px;
            }
            
            .search-form input {
                font-size: 12px;
                padding: 5px 0;
                min-width: 60px;
            }
            
            .search-form .search-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
        
        /* ============================================
           SIDEBAR STYLES
           ============================================ */
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
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <!-- TOP ROW: Logo, Search, Actions -->
        <div class="header-top">
            <div class="logo">
                <img src="images/logo.png" alt="WittyMart Logo">
                <h1>WittyMart Shop</h1>
            </div>
            
            <!-- Search Bar -->
            <div class="search-wrapper">
                <form class="search-form" action="search.php" method="GET" id="searchForm">
                    <input type="text" name="q" id="searchInput" placeholder="Search products..." autocomplete="off">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        <span>Search</span>
                    </button>
                </form>
                <!-- Search Suggestions -->
                <div class="search-suggestions" id="searchSuggestions"></div>
            </div>
            
            <!-- Header Actions -->
            <div class="header-actions">
                <!-- Cart Icon -->
                <a href="cart.php" class="header-cart" title="View Cart">
                    <i class="fas fa-shopping-cart cart-icon"></i>
                    <span class="cart-badge-sm <?php echo $cartCount > 0 ? '' : 'empty'; ?>" id="headerCartBadge">
                        <?php echo $cartCount > 0 ? $cartCount : ''; ?>
                    </span>
                </a>
                
                <?php if ($isLoggedIn): ?>
                    <!-- User Dropdown -->
                    <div class="user-dropdown" id="userDropdown">
                        <button class="dropdown-toggle" onclick="toggleDropdown()" title="My Account">
                            <span class="user-avatar">
                                <?php echo getUserInitials($userName); ?>
                            </span>
                            <span><?php echo htmlspecialchars($userName); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                        </button>
                        <div class="dropdown-menu" id="dropdownMenu">
                            <div class="dropdown-header">
                                <div class="dropdown-user-name"><?php echo htmlspecialchars($userName); ?></div>
                                <div class="dropdown-user-email"><?php echo htmlspecialchars($userEmail); ?></div>
                            </div>
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <a href="orders.php" class="dropdown-item">
                                <i class="fas fa-shopping-bag"></i> My Orders
                            </a>
                            <a href="wishlist.php" class="dropdown-item">
                                <i class="fas fa-heart"></i> Wishlist
                            </a>
                            <?php if ($isAdmin): ?>
                                <a href="admin/dashboard.php" class="dropdown-item">
                                    <i class="fas fa-crown"></i> Admin Dashboard
                                </a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to logout?')">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login/Register Button -->
                    <a href="home.php" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
                
                <button class="categories-btn" onclick="toggleSidebar()" title="Categories">
                    <i class="fas fa-th-list"></i>
                    <span>Categories</span>
                </button>
            </div>
        </div>
        
        <!-- BOTTOM ROW: Navigation (Centered) -->
        <div class="header-bottom">
            <?php
            // Get the current page filename
            $current_page = basename($_SERVER['PHP_SELF']);
            
            // Define navigation links
            $nav_links = [
                'index.php' => ['label' => 'Home', 'icon' => 'fa-home'],
                'shop.php' => ['label' => 'Shop', 'icon' => 'fa-store'],
                'about.php' => ['label' => 'About', 'icon' => 'fa-info-circle'],
                'contact.php' => ['label' => 'Contact', 'icon' => 'fa-envelope'],
                'terms.php' => ['label' => 'Terms', 'icon' => 'fa-file-contract']
            ];
            ?>
            
            <ul class="nav-links" id="navLinks">
                <?php foreach ($nav_links as $page => $data): 
                    $active_class = ($current_page == $page) ? 'active' : '';
                ?>
                    <li><a href="<?php echo $page; ?>" class="<?php echo $active_class; ?>">
                        <i class="fas <?php echo $data['icon']; ?>"></i> <?php echo $data['label']; ?>
                    </a></li>
                <?php endforeach; ?>
                
                <!-- Theme Toggle moved to bottom row -->
                <li><button class="theme-toggle" onclick="toggleTheme()" id="theme-icon" title="Switch to Dark Mode"><i class="fas fa-sun"></i></button></li>
            </ul>
            
            <button class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle Menu" id="menuToggleBtn">
                <i class="fas fa-bars" id="menuIcon"></i>
            </button>
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
                    <p class="user-email"><?php echo htmlspecialchars($userEmail); ?></p>
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

    <script>
        // ============================================
        // USER DROPDOWN TOGGLE
        // ============================================
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            if (dropdown) {
                dropdown.classList.toggle('active');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const menu = document.getElementById('dropdownMenu');
            if (dropdown && menu && menu.classList.contains('active')) {
                if (!dropdown.contains(event.target)) {
                    menu.classList.remove('active');
                }
            }
        });

        // ============================================
        // MOBILE MENU TOGGLE
        // ============================================
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            const menuIcon = document.getElementById('menuIcon');
            
            if (!navLinks) {
                console.error('navLinks element not found');
                return;
            }
            
            navLinks.classList.toggle('mobile-open');
            
            if (navLinks.classList.contains('mobile-open')) {
                if (menuIcon) menuIcon.className = 'fas fa-times';
            } else {
                if (menuIcon) menuIcon.className = 'fas fa-bars';
            }
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const navLinks = document.getElementById('navLinks');
            const menuToggle = document.getElementById('menuToggleBtn');
            const headerBottom = document.querySelector('.header-bottom');
            
            if (navLinks && navLinks.classList.contains('mobile-open')) {
                if (!headerBottom.contains(event.target) && !menuToggle.contains(event.target)) {
                    navLinks.classList.remove('mobile-open');
                    const menuIcon = document.getElementById('menuIcon');
                    if (menuIcon) {
                        menuIcon.className = 'fas fa-bars';
                    }
                }
            }
        });

        // Close menu when a link is clicked
        document.querySelectorAll('.nav-links a').forEach(function(link) {
            link.addEventListener('click', function() {
                const navLinks = document.getElementById('navLinks');
                if (navLinks && navLinks.classList.contains('mobile-open')) {
                    navLinks.classList.remove('mobile-open');
                    const menuIcon = document.getElementById('menuIcon');
                    if (menuIcon) {
                        menuIcon.className = 'fas fa-bars';
                    }
                }
            });
        });

        // ============================================
        // SEARCH SUGGESTIONS (Live Search)
        // ============================================
        const searchInput = document.getElementById('searchInput');
        const suggestions = document.getElementById('searchSuggestions');
        let searchTimeout;

        if (searchInput) {
            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-wrapper')) {
                    suggestions.classList.remove('active');
                }
            });

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    suggestions.classList.remove('active');
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    fetch('includes/ajax.php?action=search_products&q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.products && data.products.length > 0) {
                                let html = '';
                                data.products.forEach(function(product) {
                                    const price = 'Ksh ' + parseFloat(product.price).toFixed(2);
                                    html += `
                                        <a href="product.php?id=${product.id}" class="suggestion-item" data-product-id="${product.id}">
                                            <i class="fas fa-search"></i>
                                            <span class="product-name">${product.name}</span>
                                            <span class="product-price">${price}</span>
                                        </a>
                                    `;
                                });
                                suggestions.innerHTML = html;
                                suggestions.classList.add('active');
                            } else {
                                suggestions.innerHTML = '<div class="suggestion-empty">No products found</div>';
                                suggestions.classList.add('active');
                            }
                        })
                        .catch(function(error) {
                            console.error('Search error:', error);
                        });
                }, 300);
            });

            // Handle suggestion item clicks directly
            suggestions.addEventListener('click', function(e) {
                const item = e.target.closest('.suggestion-item');
                if (item) {
                    e.preventDefault();
                    const href = item.getAttribute('href');
                    if (href) {
                        window.location.href = href;
                    }
                }
            });

            // Close suggestions when pressing Escape
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    suggestions.classList.remove('active');
                    this.blur();
                }
            });

            // Prevent form submission if no query
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                if (!searchInput.value.trim()) {
                    e.preventDefault();
                }
            });
        }

        // ============================================
        // CART COUNT REFRESH FUNCTION
        // ============================================
        function refreshCartCount() {
            if (!<?php echo json_encode($isLoggedIn); ?>) return;
            
            fetch('cart.php?action=get_cart_count')
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        var count = data.count;
                        
                        var headerBadge = document.getElementById('headerCartBadge');
                        if (headerBadge) {
                            if (count > 0) {
                                headerBadge.textContent = count;
                                headerBadge.classList.remove('empty');
                                headerBadge.classList.add('pulse');
                                setTimeout(function() { headerBadge.classList.remove('pulse'); }, 500);
                            } else {
                                headerBadge.textContent = '';
                                headerBadge.classList.add('empty');
                            }
                        }
                    }
                })
                .catch(function(error) { console.error('Error refreshing cart count:', error); });
        }

        // ============================================
        // AUTO-REFRESH CART COUNT
        // ============================================
        setInterval(refreshCartCount, 30000);

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
            var isDark = document.body.classList.contains('dark-mode');
            var icon = document.getElementById('theme-icon');
            if (icon) {
                icon.innerHTML = isDark ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
                icon.title = isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode';
            }
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        }

        // Load saved theme
        var savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            var icon = document.getElementById('theme-icon');
            if (icon) {
                icon.innerHTML = '<i class="fas fa-moon"></i>';
                icon.title = 'Switch to Light Mode';
            }
        }

        // ============================================
        // SIDEBAR TOGGLE
        // ============================================
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.classList.toggle('sidebar-open');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', function() {
                    toggleSidebar();
                });
            }
        });
    </script>
</body>
</html>
