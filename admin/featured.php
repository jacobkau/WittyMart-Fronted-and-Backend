<?php
require_once 'includes/config.php';
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

// ===== HANDLE FORM SUBMISSIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO featured_products (product_id) VALUES (?)");
            if ($stmt->execute([$product_id])) {
                $message = 'Product added to featured!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'remove') {
        try {
            $stmt = $pdo->prepare("DELETE FROM featured_products WHERE product_id = ?");
            if ($stmt->execute([$product_id])) {
                $message = 'Product removed from featured!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'update_order') {
        try {
            $id = intval($_POST['id'] ?? 0);
            $display_order = intval($_POST['display_order'] ?? 0);
            $stmt = $pdo->prepare("UPDATE featured_products SET display_order = ? WHERE id = ?");
            if ($stmt->execute([$display_order, $id])) {
                $message = 'Display order updated!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ===== GET FEATURED PRODUCTS =====
try {
    $stmt = $pdo->query("
        SELECT fp.*, p.name, p.price, p.image, p.image_url, p.sku, p.description
        FROM featured_products fp
        INNER JOIN products p ON fp.product_id = p.id
        ORDER BY fp.display_order ASC
    ");
    $featured = $stmt->fetchAll();
} catch (PDOException $e) {
    $featured = [];
}

// ===== GET ALL PRODUCTS (for adding) =====
try {
    $stmt = $pdo->query("
        SELECT id, name, sku 
        FROM products 
        WHERE id NOT IN (SELECT product_id FROM featured_products)
        ORDER BY name
    ");
    $available_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $available_products = [];
}

// ===== HELPER FUNCTION FOR PRODUCT IMAGE URL (Cloudinary Support) =====
function getProductImageUrl($image_path, $image_url = null) {
    // If Cloudinary URL exists, use it
    if (!empty($image_url)) {
        return $image_url;
    }
    
    // Base URL
    $base_url = 'https://wittymart.onrender.com/';
    
    // If no image, return placeholder
    if (empty($image_path)) {
        return $base_url . 'uploads/products/no-image.png';
    }
    
    // If it's already a full URL, return it
    if (strpos($image_path, 'http://') === 0 || strpos($image_path, 'https://') === 0) {
        return $image_path;
    }
    
    // Clean the path
    $image_path = ltrim($image_path, '/');
    $image_path = str_replace('../', '', $image_path);
    
    // Return full URL
    return $base_url . $image_path;
}

$page_title = 'Manage Featured Products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Featured Products - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .featured-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            background: #f5f5f5;
        }
        
        .cloudinary-badge {
            font-size: 8px;
            color: #3448C5;
            display: block;
            text-align: center;
            margin-top: 2px;
        }
        
        .form-inline {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .form-inline select {
            flex: 1;
            min-width: 200px;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: #fff;
            color: #333;
        }
        
        .form-inline .btn-primary {
            padding: 8px 20px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .form-inline .btn-primary:hover {
            background: #03402c;
        }
        
        .btn-sm {
            padding: 4px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: #28a745;
            color: #fff;
        }
        
        .btn-edit:hover {
            background: #218838;
        }
        
        .btn-delete {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-sm i {
            margin-right: 4px;
        }
        
        .order-form {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .order-form input[type="number"] {
            width: 60px;
            padding: 4px 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        
        .order-form button {
            padding: 4px 8px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.3s ease;
        }
        
        .order-form button:hover {
            background: #03402c;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            color: #555;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .form-inline {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-inline select {
                min-width: auto;
                width: 100%;
            }
            
            .order-form {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"?>
        <div class="admin-main">
            <div class="admin-card">
                <div class="card-header">
                    <h2><i class="fas fa-star"></i> Featured Products</h2>
                    <span class="badge badge-info">Total: <?php echo count($featured); ?></span>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add Featured Product -->
                    <?php if (!empty($available_products)): ?>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="action" value="add">
                            <select name="product_id" required>
                                <option value="">Select Product to Feature</option>
                                <?php foreach ($available_products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name'] . ' (' . $product['sku'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-plus"></i> Add to Featured
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> All products are already featured.
                        </div>
                    <?php endif; ?>

                    <!-- Featured Products List -->
                    <?php if (!empty($featured)): ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Order</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($featured as $item): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $image_url = getProductImageUrl($item['image'] ?? '', $item['image_url'] ?? null);
                                            ?>
                                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="featured-image"
                                                 onerror="this.src='https://wittymart.onrender.com/uploads/products/no-image.png'">
                                            <?php if (!empty($item['image_url'])): ?>
                                                <span class="cloudinary-badge">
                                                    <i class="fas fa-cloud"></i> Cloud
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                            <?php if (!empty($item['description'])): ?>
                                                <br><small style="color: #888;"><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></code></td>
                                        <td><strong><?php echo formatPrice($item['price']); ?></strong></td>
                                        <td>
                                            <form method="POST" class="order-form">
                                                <input type="hidden" name="action" value="update_order">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <input type="number" name="display_order" value="<?php echo $item['display_order']; ?>" min="0">
                                                <button type="submit"><i class="fas fa-save"></i></button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Remove this product from featured?')">
                                                    <i class="fas fa-times"></i> Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <h3>No Featured Products</h3>
                            <p>Add products to the featured list using the form above.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ===== AUTO-HIDE ALERTS =====
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>
