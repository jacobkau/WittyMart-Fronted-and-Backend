<?php

$isLoggedIn = isLoggedIn();
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'WittyMart'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <header>
        <div class="header-container">
            <div class="logo">
                <img src="images/Witty Mart.png" alt="WittyMart Logo">
                <h1>WittyMart Shop</h1>
            </div>
            
            <nav id="main-nav">
                <ul class="nav-links">
                    <li><a href="index.php" <?php echo ($page ?? '') === 'home' ? 'class="active"' : ''; ?>>Home</a></li>
                    <li><a href="shop.php" <?php echo ($page ?? '') === 'shop' ? 'class="active"' : ''; ?>>Shop</a></li>
                    <li><a href="cart.php" <?php echo ($page ?? '') === 'cart' ? 'class="active"' : ''; ?>>Cart</a></li>
                    <li><a href="about.php" <?php echo ($page ?? '') === 'about' ? 'class="active"' : ''; ?>>About</a></li>
                    <li><a href="contact.php" <?php echo ($page ?? '') === 'contact' ? 'class="active"' : ''; ?>>Contact</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li><a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php else: ?>
                        <li><a href="admin/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="header-actions">
                <?php if ($isLoggedIn): ?>
                    <span class="user-greeting"><i class="fas fa-user"></i> <?php echo $userName; ?></span>
                <?php endif; ?>
                <button class="menu-toggle" onclick="toggleMenu()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <script>
        function toggleMenu() {
            const nav = document.getElementById('main-nav');
            nav.classList.toggle('show');
        }
    </script>
