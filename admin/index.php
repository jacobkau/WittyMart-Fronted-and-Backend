<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/config.php';

$page_title = "Admin Dashboard - WittyMart";
$featured_products = getFeaturedProducts(8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin · WittyMart Dashboard</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .admin-hero {
            background: linear-gradient(145deg, #0b2b3f 0%, #1a4b62 100%);
            padding: 4rem 1rem 3rem;
            border-radius: 0 0 3rem 3rem;
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
        }
        .admin-hero h1 span {
            color: #ffc857;
        }
        .badge-admin {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(4px);
            color: #fff;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 500;
            letter-spacing: 0.3px;
            display: inline-block;
        }
        .stat-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(2px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            color: white;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-card .label {
            font-size: 0.85rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-outline-light-custom {
            border: 1.5px solid rgba(255,255,255,0.5);
            color: white;
            border-radius: 60px;
            padding: 0.6rem 1.8rem;
            font-weight: 500;
            transition: 0.2s;
            background: transparent;
        }
        .btn-outline-light-custom:hover {
            background: white;
            color: #1a4b62;
            border-color: white;
        }
        .btn-login-gold {
            background: #ffc857;
            border: none;
            border-radius: 60px;
            padding: 0.7rem 2.2rem;
            font-weight: 600;
            color: #0b2b3f;
            box-shadow: 0 6px 14px rgba(255,200,87,0.3);
            transition: 0.2s;
        }
        .btn-login-gold:hover {
            background: #ffd97a;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255,200,87,0.4);
            color: #0b2b3f;
        }
        .section-title {
            font-weight: 700;
            color: #0b2b3f;
            letter-spacing: -0.3px;
        }
        .section-title span {
            color: #1a4b62;
            border-bottom: 4px solid #ffc857;
            padding-bottom: 4px;
        }
        .product-grid .card {
            border: none;
            border-radius: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: 0.25s ease;
            background: white;
            height: 100%;
        }
        .product-grid .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 30px rgba(0,0,0,0.07);
        }
        .product-grid .card-img-top {
            border-radius: 24px 24px 0 0;
            object-fit: cover;
            height: 180px;
            background: #f1f5f9;
        }
        .product-grid .badge-stock {
            font-weight: 500;
            font-size: 0.7rem;
            padding: 0.4rem 0.9rem;
            border-radius: 40px;
        }
        .product-grid .price {
            font-weight: 700;
            color: #0b2b3f;
            font-size: 1.2rem;
        }
        .product-grid .btn-view {
            background: #eef2f6;
            border: none;
            border-radius: 40px;
            padding: 0.3rem 1.2rem;
            font-weight: 500;
            color: #1a4b62;
            transition: 0.15s;
        }
        .product-grid .btn-view:hover {
            background: #1a4b62;
            color: white;
        }
        .empty-state i {
            font-size: 3.5rem;
            color: #b9c7d4;
        }
        .footer-admin {
            background: white;
            border-top: 1px solid #e9edf2;
            color: #4b5e6b;
        }
        a {
            text-decoration: none;
        }
        .container-custom {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        .admin-badge-top {
            background: #ffc857;
            color: #0b2b3f;
            padding: 0.2rem 1rem;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: inline-block;
        }
        .bg-success-subtle { background: #d1e7dd; }
        .bg-danger-subtle { background: #f8d7da; }
        .text-success { color: #0f6848 !important; }
        .text-danger { color: #b02a37 !important; }
        .border-success { border-color: #0f6848 !important; }
        .border-danger { border-color: #b02a37 !important; }
        .btn-outline-secondary { border-color: #b7c4cf; }
        .btn-outline-secondary:hover { background: #1a4b62; border-color: #1a4b62; color: white; }
        .btn-light { background: white; }
    </style>
</head>
<body>
<div class="admin-hero mb-5">
    <div class="container-custom">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div class="d-flex align-items-center gap-3">
                <span class="admin-badge-top"><i class="fas fa-user-shield me-1"></i> ADMIN</span>
                <span class="text-white-50 d-none d-sm-inline"><i class="fas fa-circle" style="font-size: 0.3rem; vertical-align: middle;"></i> WittyMart dashboard</span>
            </div>
            <a href="login.php" class="btn btn-login-gold">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </a>
        </div>

        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="badge-admin mb-3">
                    <i class="fas fa-store me-2"></i> Admin · WittyMart
                </div>
                <h1 class="display-4 fw-bold text-white mb-3">Smart Shopping for <span>Witty Minds</span></h1>
                <p class="text-light opacity-75 fs-5 mb-4">Manage products, orders, and insights — all from one place.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="shop.php" class="btn btn-light btn-lg rounded-pill px-5 fw-semibold" style="color:#0b2b3f;">
                        <i class="fas fa-boxes me-2"></i> Manage Products
                    </a>
                    <a href="#" class="btn btn-outline-light-custom btn-lg rounded-pill px-4">
                        <i class="fas fa-chart-simple me-2"></i> Analytics
                    </a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="stat-card text-center">
                            <div class="number">1.2k</div>
                            <div class="label">Products</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card text-center">
                            <div class="number">342</div>
                            <div class="label">Orders</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card text-center">
                            <div class="number">4.9★</div>
                            <div class="label">Rating</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card text-center">
                            <div class="number">18</div>
                            <div class="label">New users</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="container-custom pb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h2 class="section-title fs-2 m-0">Featured <span>Products</span></h2>
        <a href="shop.php" class="btn btn-outline-secondary rounded-pill px-4 py-2 border-2 fw-semibold">
            View All <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <?php if (!empty($featured_products)): ?>
        <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 product-grid">
            <?php foreach ($featured_products as $product): ?>
                <div class="col">
                    <div class="card h-100">
                        <img 
                            src="<?php echo htmlspecialchars($product['image'] ?? 'uploads/products/no-image.png'); ?>" 
                            class="card-img-top" 
                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                            onerror="this.src='uploads/products/aa.png'"
                        >
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="text-secondary small fw-semibold"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                                <span class="badge-stock bg-<?php echo ($product['stock'] ?? 0) > 0 ? 'success-subtle' : 'danger-subtle'; ?> text-<?php echo ($product['stock'] ?? 0) > 0 ? 'success' : 'danger'; ?> border border-<?php echo ($product['stock'] ?? 0) > 0 ? 'success' : 'danger'; ?> bg-opacity-10">
                                    <?php echo ($product['stock'] ?? 0) > 0 ? 'In Stock' : 'Out of Stock'; ?>
                                </span>
                            </div>
                            <h5 class="card-title fw-bold mt-1"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text small text-secondary mb-2"><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)); ?>…</p>
                            <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="price">Ksh <?php echo number_format($product['price'] ?? 0, 2); ?></span>
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-view">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state text-center py-5">
            <i class="fas fa-box-open"></i>
            <h4 class="mt-3 fw-bold">No featured products</h4>
            <p class="text-secondary">Add products to the featured list in the admin panel.</p>
        </div>
    <?php endif; ?>
</section>
<footer class="footer-admin py-4 mt-5">
    <div class="container-custom d-flex flex-wrap justify-content-between align-items-center">
        <span class="small"><i class="fas fa-store-alt me-1"></i> WittyMart · Admin Dashboard</span>
        <span class="small text-secondary">© 2026 · Smart Shopping for Witty Minds</span>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
