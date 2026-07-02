<?php
// admin/sidebar.php - Sidebar with Mobile Toggle

$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'active' : '';
}
?>
<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar Toggle Button (Mobile) -->
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <img src="images/logo.png" alt="WittyMart">
        <h2>WittyMart</h2>
        <span class="admin-role">Admin Panel</span>
        <button class="sidebar-close-btn" onclick="closeSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="sidebar-nav">
        
        <a href="dashboard.php" class="<?php echo isActive('dashboard.php'); ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
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
     
        <div class="sidebar-label">Account</div>
        <a href="admins.php" class="<?php echo isActive('admins.php'); ?>">
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
    
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<style>
/* ===== SIDEBAR STYLES ===== */
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
    transition: transform 0.3s ease;
    z-index: 1000;
}

.sidebar-header {
    text-align: center;
    padding: 25px 20px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
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

/* Sidebar Close Button (Mobile) */
.sidebar-close-btn {
    display: none;
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.sidebar-close-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Sidebar Toggle Button (Mobile) */
.sidebar-toggle-btn {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 999;
    background: #05573c;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.sidebar-toggle-btn:hover {
    background: #03402c;
    transform: scale(1.05);
}

.sidebar-toggle-btn i {
    font-size: 20px;
}

/* Sidebar Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    animation: fadeIn 0.3s ease;
}

.sidebar-overlay.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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

/* ===== RESPONSIVE - MOBILE ===== */
@media (max-width: 768px) {
    .sidebar-toggle-btn {
        display: block;
    }
    
    .sidebar-close-btn {
        display: block;
    }
    
    .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        transform: translateX(-100%);
        z-index: 1000;
        box-shadow: 2px 0 20px rgba(0, 0, 0, 0.2);
    }
    
    .admin-sidebar.open {
        transform: translateX(0);
    }
    
    /* Push main content when sidebar is open */
    .admin-wrapper.sidebar-open .admin-main {
        margin-left: 0;
    }
    
    /* Adjust header for mobile */
    .admin-header {
        padding-left: 60px;
    }
}

@media (max-width: 480px) {
    .admin-sidebar {
        width: 260px;
    }
    
    .sidebar-header img {
        width: 50px;
        height: 50px;
    }
    
    .sidebar-header h2 {
        font-size: 18px;
    }
    
    .sidebar-nav a {
        padding: 10px 20px;
        font-size: 13px;
    }
}

/* ===== DESKTOP ===== */
@media (min-width: 769px) {
    .sidebar-toggle-btn {
        display: none !important;
    }
    
    .sidebar-close-btn {
        display: none !important;
    }
    
    .sidebar-overlay {
        display: none !important;
    }
}
</style>

<script>
// ===== SIDEBAR TOGGLE FUNCTIONS =====
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const wrapper = document.querySelector('.admin-wrapper');
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
    
    if (wrapper) {
        wrapper.classList.toggle('sidebar-open');
    }
    
    // Prevent body scroll when sidebar is open
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}

function closeSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const wrapper = document.querySelector('.admin-wrapper');
    
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    
    if (wrapper) {
        wrapper.classList.remove('sidebar-open');
    }
    
    document.body.style.overflow = '';
}

// ===== CLOSE SIDEBAR ON ESCAPE KEY =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
    }
});

// ===== CLOSE SIDEBAR ON WINDOW RESIZE (Desktop) =====
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeSidebar();
    }
});

// ===== PREVENT CLICK INSIDE SIDEBAR FROM CLOSING =====
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    if (sidebar) {
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});
</script>
