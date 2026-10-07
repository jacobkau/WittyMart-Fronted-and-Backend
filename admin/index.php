<?php
require_once 'includes/config.php';
requireAdmin();

global $pdo;

// ============================================
// REAL STATS
// ============================================
$stats = [
    'products' => 0,
    'orders'   => 0,
    'customers'=> 0,
    'revenue'  => 0.0,
];

try {
    $stats['products']  = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stats['orders']    = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['customers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $stats['revenue']   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
} catch (PDOException $e) {
    error_log('Admin dashboard stats: ' . $e->getMessage());
}

$page_title = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin · WittyMart Dashboard</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================
           HERO
           ============================================ */
        .admin-hero {
            background: linear-gradient(145deg, #05573c 0%, #04452f 100%);
            padding: 3rem 1rem 2.5rem;
            border-radius: 0 0 2rem 2rem;
            box-shadow: 0 12px 30px rgba(5, 87, 60, 0.15);
        }

        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .hero-badge {
            background: #d4a017;
            color: #1a1208;
            padding: 0.35rem 1rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .hero-logout {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            text-decoration: none;
            padding: 0.55rem 1.3rem;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .hero-logout:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.4);
        }

        /* ============================================
           HERO BODY
           ============================================ */
        .hero-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 2.5rem;
            align-items: center;
        }

        @media (max-width: 860px) {
            .hero-grid { grid-template-columns: 1fr; gap: 1.5rem; }
        }

        .hero-welcome {
            color: rgba(255,255,255,0.75);
            font-size: 0.9rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            margin-bottom: 0.6rem;
        }

        .hero-title {
            color: #fff;
            font-size: 2.1rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 0.9rem;
        }

        .hero-title span { color: #d4a017; }

        .hero-subtitle {
            color: rgba(255,255,255,0.75);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            max-width: 480px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1.4rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .hero-btn.primary {
            background: #fff;
            color: #05573c;
        }

        .hero-btn.primary:hover {
            background: #f0f4f2;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }

        .hero-btn.outline {
            background: transparent;
            color: #fff;
            border: 1.5px solid rgba(255,255,255,0.4);
        }

        .hero-btn.outline:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.6);
        }

        /* ============================================
           STATS GRID
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .stat-card {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 1.1rem 1.2rem;
            color: #fff;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.2);
        }

        .stat-card .number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .stat-card .label {
            font-size: 0.72rem;
            opacity: 0.75;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
            margin-top: 0.2rem;
        }

        /* ============================================
           FOOTER
           ============================================ */
        .footer-admin {
            margin-top: auto;
            background: #fff;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            padding: 1.1rem 0;
            font-size: 0.8rem;
        }

        .footer-admin .container-custom {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-admin .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .footer-admin .brand i { color: #05573c; }

        @media (max-width: 520px) {
            .admin-hero { padding: 2rem 1rem 1.75rem; }
            .hero-title { font-size: 1.6rem; }
            .hero-subtitle { font-size: 0.88rem; }
            .stat-card .number { font-size: 1.3rem; }
            .stat-card .label { font-size: 0.65rem; }
        }
    </style>
</head>
<body>

    <!-- ============================================
         HERO
         ============================================ -->
    <div class="admin-hero">
        <div class="container-custom">

            <!-- Top bar inside hero -->
            <div class="hero-top">
                <span class="hero-badge">
                    <i class="fas fa-user-shield"></i> ADMIN
                </span>
                <a href="logout.php" class="hero-logout" onclick="return confirm('Log out?')">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Hero body -->
            <div class="hero-grid">

                <!-- Left: welcome + actions -->
                <div>
                    <p class="hero-welcome">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></p>
                    <h1 class="hero-title">
                        Smart Shopping for <span>Witty Minds</span>
                    </h1>
                    <p class="hero-subtitle">
                        Manage products, orders, and insights — all from one place.
                    </p>
                    <div class="hero-actions">
                        <a href="manage_products.php" class="hero-btn primary">
                            <i class="fas fa-boxes"></i> Manage Products
                        </a>
                        <a href="orders.php" class="hero-btn outline">
                            <i class="fas fa-shopping-bag"></i> View Orders
                        </a>
                    </div>
                </div>

                <!-- Right: stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo number_format($stats['products']); ?></div>
                        <div class="label">Products</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo number_format($stats['orders']); ?></div>
                        <div class="label">Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo number_format($stats['customers']); ?></div>
                        <div class="label">Customers</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">Ksh <?php echo number_format($stats['revenue'], 0); ?></div>
                        <div class="label">Revenue</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ============================================
         FOOTER
         ============================================ -->
    <footer class="footer-admin">
        <div class="container-custom">
            <span class="brand">
                <i class="fas fa-store-alt"></i> WittyMart · Admin Dashboard
            </span>
            <span>&copy; <?php echo date('Y'); ?> · Smart Shopping for Witty Minds</span>
        </div>
    </footer>

</body>
</html>
