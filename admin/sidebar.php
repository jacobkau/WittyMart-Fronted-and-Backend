<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <img src="images/logo.png" alt="WittyMart">
        <h2>WittyMart</h2>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="products.php" class="<?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="orders.php" class="<?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Orders
        </a>
        <a href="customers.php" class="<?php echo $current_page === 'customers.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Customers
        </a>
        <a href="categories.php" class="<?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
            <i class="fas fa-tags"></i> Categories
        </a>
        <a href="admins.php" class="<?php echo isActive('admin_management.php'); ?>">
    <i class="fas fa-user-shield"></i> Admin Management
</a>
        <a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i> Profile
        </a>
        <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="activity_logs.php" class="<?php echo $current_page === 'activity_logs.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> Activity Logs
        </a>
        <hr class="sidebar-divider">
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>
<style>
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
    color: var(--white);
    border-left-color: var(--white);
}

.sidebar-nav a.active {
    background: rgba(255, 255, 255, 0.15);
    color: var(--white);
    border-left-color: var(--white);
}

.sidebar-nav a i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.sidebar-divider {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin: 10px 20px;
}
</style>
