<?php
// admin/sidebar.php - Fixed Version

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to check active page
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'active' : '';
}
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.png" alt="WittyMart">
        <h2>WittyMart</h2>
        <span class="admin-role">Admin Panel</span>
    </div>
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="dashboard.php" class="<?php echo isActive('dashboard.php'); ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- Store Management -->
        <div class="sidebar-label">Store</div>
        <a href="products.php" class="<?php echo isActive('products.php'); ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="orders.php" class="<?php echo isActive('orders.php'); ?>">
            <i class="fas fa-shopping-cart"></i> Orders
        </a>
        <a href="customers.php" class="<?php echo isActive('customers.php'); ?>">
            <i class="fas fa-users"></i> Customers
        </a>
        <a href="categories.php" class="<?php echo isActive('categories.php'); ?>">
            <i class="fas fa-tags"></i> Categories
        </a>
        
        <hr class="sidebar-divider">
        
        <!-- Account Management -->
        <div class="sidebar-label">Account</div>
        <a href="admin_management.php" class="<?php echo isActive('admin_management.php'); ?>">
            <i class="fas fa-user-shield"></i> Admin Management
        </a>
        <a href="profile.php" class="<?php echo isActive('profile.php'); ?>">
            <i class="fas fa-user-cog"></i> Profile
        </a>
        <a href="settings.php" class="<?php echo isActive('settings.php'); ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="activity_logs.php" class="<?php echo isActive('activity_logs.php'); ?>">
            <i class="fas fa-history"></i> Activity Logs
        </a>
        
        <hr class="sidebar-divider">
        
        <!-- Logout -->
        <a href="../logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<style>
/* Sidebar Styles */
.admin-sidebar {
    width: 260px;
    background: #05573c;
    color: #fff;
    min-height: 100vh;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    flex-shrink: 0;
}

.sidebar-header {
    text-align: center;
    padding: 25px 20px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header img {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.2);
}

.sidebar-header h2 {
    font-size: 20px;
    margin-top: 10px;
    color: #fff;
}

.sidebar-header .admin-role {
    font-size: 12px;
    opacity: 0.7;
    display: block;
    margin-top: 2px;
}

.sidebar-label {
    padding: 10px 25px 5px;
    font-size: 10px;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.4);
    letter-spacing: 1px;
    font-weight: 600;
}

.sidebar-nav {
    padding: 10px 0;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 12px 25px;
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 14px;
    gap: 12px;
    border-left: 3px solid transparent;
}

.sidebar-nav a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border-left-color: #fff;
}

.sidebar-nav a.active {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    border-left-color: #fff;
}

.sidebar-nav a i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.sidebar-nav a.logout-link {
    color: rgba(255, 107, 107, 0.8);
}

.sidebar-nav a.logout-link:hover {
    color: #ff6b6b;
    background: rgba(255, 107, 107, 0.1);
}

.sidebar-divider {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    margin: 10px 20px;
}

/* Scrollbar */
.admin-sidebar::-webkit-scrollbar {
    width: 4px;
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-sidebar {
        width: 220px;
        position: fixed;
        left: -280px;
        z-index: 1000;
        transition: left 0.3s ease;
    }
    
    .admin-sidebar.open {
        left: 0;
    }
}
</style>
