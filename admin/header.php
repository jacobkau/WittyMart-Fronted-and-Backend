<?php
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
    exit;
}

$page_title = $page_title ?? 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - WittyMart Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <img src="../images/Witty Mart.png" alt="WittyMart">
                <h2>WittyMart</h2>
                <p class="admin-role">Admin Panel</p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" <?php echo ($page ?? '') === 'dashboard' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="products.php" <?php echo ($page ?? '') === 'products' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-box"></i> Products
                </a>
                <a href="orders.php" <?php echo ($page ?? '') === 'orders' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
                <a href="customers.php" <?php echo ($page ?? '') === 'customers' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-users"></i> Customers
                </a>
                <a href="categories.php" <?php echo ($page ?? '') === 'categories' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-tags"></i> Categories
                </a>
                <a href="settings.php" <?php echo ($page ?? '') === 'settings' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-cog"></i> Settings
                </a>
                <hr class="sidebar-divider">
                <a href="../index.php" target="_blank">
                    <i class="fas fa-store"></i> View Store
                </a>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Top Header -->
            <header class="admin-top-header">
                <div class="header-left">
                    <button class="sidebar-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><?php echo $page_title; ?></h1>
                </div>
                <div class="header-right">
                    <div class="admin-user">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></span>
                        <span class="user-badge">Admin</span>
                    </div>
                </div>
            </header>
