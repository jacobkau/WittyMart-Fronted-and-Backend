<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.png" alt="WittyMart">
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
