<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
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
    }
}

// ===== GET FEATURED PRODUCTS =====
try {
    $stmt = $pdo->query("
        SELECT fp.*, p.name, p.price, p.image, p.sku
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

$page_title = 'Manage Featured Products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add Featured Product -->
        <?php if (!empty($available_products)): ?>
            <form method="POST" class="form-inline" style="display: flex; gap: 10px; margin-bottom: 20px;">
                <input type="hidden" name="action" value="add">
                <select name="product_id" class="form-control" required>
                    <option value="">Select Product</option>
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
            <p class="text-muted">All products are already featured.</p>
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
                                <img src="<?php echo htmlspecialchars($item['image'] ?? 'images/no-image.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            </td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td><?php echo formatPrice($item['price']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_order">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <input type="number" name="display_order" value="<?php echo $item['display_order']; ?>" 
                                           style="width: 60px; padding: 4px;">
                                    <button type="submit" class="btn-sm btn-edit">Update</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Remove from featured?')">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted text-center" style="padding: 40px 0;">
                <i class="fas fa-star" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                No featured products yet.
            </p>
        <?php endif; ?>
    </div>
</div>
       </div>
    </div>
