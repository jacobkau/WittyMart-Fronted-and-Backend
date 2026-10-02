<?php
require_once 'includes/config.php';
require_once 'includes/cloudinary_helper.php';
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
        SELECT fp.id as featured_id, fp.display_order,
               p.id as product_id, p.name, p.price, p.image, p.image_url, 
               p.sku, p.description, p.stock, p.status, p.supplier, p.category_id,
               c.name as category_name
        FROM featured_products fp
        INNER JOIN products p ON fp.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
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

// ===== HELPER: escape JS =====
if (!function_exists('jsEscape')) {
    function jsEscape($str) {
        if ($str === null) return '';
        $str = str_replace("\\", "\\\\", $str);
        $str = str_replace("'", "\\'", $str);
        $str = str_replace('"', '\\"', $str);
        $str = str_replace("\r", "\\r", $str);
        $str = str_replace("\n", "\\n", $str);
        $str = str_replace("\t", "\\t", $str);
        return $str;
    }
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
        
        .btn-view {
            background: #17a2b8;
            color: #fff;
        }
        
        .btn-view:hover {
            background: #138496;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
        
        /* ===== VIEW PRODUCT MODAL ===== */
        #viewProductModal .modal-content {
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .view-product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        .view-gallery {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .view-main-image {
            position: relative;
            width: 100%;
            height: 340px;
            border-radius: 10px;
            overflow: hidden;
            background: #f5f5f5;
            cursor: zoom-in;
        }
        
        .view-main-image img {
            width: 100%; height: 100%;
            object-fit: contain;
            display: block;
            transition: opacity 0.25s ease;
        }
        
        .view-image-counter {
            position: absolute; top: 10px; left: 10px;
            background: rgba(0,0,0,0.7); color: #fff;
            font-size: 11px; padding: 4px 10px;
            border-radius: 12px; font-weight: 600;
            display: flex; align-items: center; gap: 5px;
        }
        
        .view-gallery-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.9); color: #333;
            border: none; width: 36px; height: 36px;
            border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .view-gallery-nav:hover { background: #05573c; color: #fff; }
        .view-gallery-nav.prev { left: 10px; }
        .view-gallery-nav.next { right: 10px; }
        
        .view-thumbs {
            display: flex; gap: 8px;
            overflow-x: auto; padding: 4px 2px;
        }
        .view-thumb {
            flex: 0 0 64px; width: 64px; height: 64px;
            border-radius: 6px; overflow: hidden;
            cursor: pointer; border: 2px solid transparent;
            transition: all 0.2s ease; background: #f5f5f5;
        }
        .view-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .view-thumb.active { border-color: #05573c; }
        .view-thumb:hover { transform: translateY(-2px); }
        
        .view-details h2 {
            margin: 0 0 8px 0; color: #222; font-size: 22px;
        }
        .view-details .view-price {
            font-size: 26px; font-weight: 700;
            color: #05573c; margin: 6px 0 14px;
        }
        .view-meta {
            display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;
        }
        .view-meta-badge {
            background: #f0f0f0; padding: 4px 12px;
            border-radius: 12px; font-size: 12px;
            color: #555; display: inline-flex; align-items: center; gap: 5px;
        }
        .view-meta-badge.cloud { background: #e7eaff; color: #3448C5; font-weight: 600; }
        
        .view-section-title {
            font-size: 12px; text-transform: uppercase;
            letter-spacing: 0.5px; color: #888;
            margin: 14px 0 6px; font-weight: 700;
        }
        .view-description {
            color: #444; line-height: 1.7;
            background: #f8f9fa; padding: 12px;
            border-radius: 8px; font-size: 14px;
            white-space: pre-wrap;
        }
        .view-data-row {
            display: flex; justify-content: space-between;
            padding: 6px 0; border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .view-data-row:last-child { border-bottom: none; }
        .view-data-row .label { color: #888; }
        .view-data-row .value { color: #333; font-weight: 600; }
        
        /* ===== LIGHTBOX ===== */
        .adm-lightbox {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.95);
            z-index: 100000;
            justify-content: center; align-items: center;
            padding: 20px;
        }
        .adm-lightbox.active { display: flex; }
        .adm-lightbox img {
            max-width: 95vw; max-height: 90vh;
            object-fit: contain; border-radius: 6px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        }
        .adm-lightbox .adm-lb-close {
            position: absolute; top: 20px; right: 24px;
            background: rgba(255,255,255,0.15); color: #fff;
            border: none; width: 44px; height: 44px;
            border-radius: 50%; cursor: pointer; font-size: 22px;
            display: flex; align-items: center; justify-content: center;
        }
        .adm-lightbox .adm-lb-close:hover { background: rgba(255,255,255,0.3); }
        .adm-lb-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.15); color: #fff;
            border: none; width: 50px; height: 50px;
            border-radius: 50%; cursor: pointer; font-size: 20px;
            display: flex; align-items: center; justify-content: center;
        }
        .adm-lb-nav:hover { background: rgba(255,255,255,0.3); }
        .adm-lb-nav.prev { left: 20px; }
        .adm-lb-nav.next { right: 20px; }
        
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
            
            .view-product-grid { grid-template-columns: 1fr; }
            .view-main-image { height: 240px; }
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
                                            $image_url = getProductImage($item['image'] ?? null, $item['image_url'] ?? null);
                                            ?>
                                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="featured-image"
                                                 onerror="this.src='uploads/products/no-image.png'">
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
                                                <input type="hidden" name="id" value="<?php echo $item['featured_id']; ?>">
                                                <input type="number" name="display_order" value="<?php echo $item['display_order']; ?>" min="0">
                                                <button type="submit"><i class="fas fa-save"></i></button>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-sm btn-view" onclick="viewProduct(<?php echo $item['product_id']; ?>)" title="View details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                    <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Remove this product from featured?')">
                                                        <i class="fas fa-times"></i> Remove
                                                    </button>
                                                </form>
                                            </div>
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

    <!-- ============================================
         VIEW PRODUCT MODAL
         ============================================ -->
    <div id="viewProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Product Details</h2>
                <span class="close" onclick="closeModal('viewProductModal')">&times;</span>
            </div>
            <div id="viewProductContent">
                <p style="text-align:center;padding:40px;color:#888;">
                    <i class="fas fa-spinner fa-spin"></i> Loading product…
                </p>
            </div>
        </div>
    </div>

    <!-- ============================================
         ADMIN LIGHTBOX
         ============================================ -->
    <div class="adm-lightbox" id="admLightbox">
        <button class="adm-lb-close" onclick="closeAdmLightbox()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
        <button class="adm-lb-nav prev" onclick="admLightboxPrev()" aria-label="Previous">
            <i class="fas fa-chevron-left"></i>
        </button>
        <img src="" alt="Preview" id="admLightboxImg">
        <button class="adm-lb-nav next" onclick="admLightboxNext()" aria-label="Next">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <script>
        // ===== STORE FEATURED PRODUCT DATA =====
        var featuredProductData = {};
        
        <?php foreach ($featured as $item): ?>
            featuredProductData[<?php echo $item['product_id']; ?>] = {
                id: <?php echo $item['product_id']; ?>,
                name: '<?php echo jsEscape($item['name']); ?>',
                description: '<?php echo jsEscape($item['description'] ?? ''); ?>',
                price: '<?php echo $item['price']; ?>',
                stock: '<?php echo intval($item['stock'] ?? 0); ?>',
                status: '<?php echo jsEscape($item['status'] ?? 'active'); ?>',
                sku: '<?php echo jsEscape($item['sku'] ?? ''); ?>',
                supplier: '<?php echo jsEscape($item['supplier'] ?? ''); ?>',
                category_name: '<?php echo jsEscape($item['category_name'] ?? 'Uncategorized'); ?>',
                image: '<?php echo $item['image'] ? jsEscape($item['image']) : ''; ?>',
                image_url: '<?php echo $item['image_url'] ? jsEscape($item['image_url']) : ''; ?>'
            };
        <?php endforeach; ?>
        
        // ============================================
        // MODAL HELPERS
        // ============================================
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // ============================================
        // VIEW PRODUCT MODAL
        // ============================================
        let viewGallery = [];
        let viewGalleryIndex = 0;
        
        function viewProduct(productId) {
            const data = featuredProductData[productId];
            if (!data) { alert('Product not found'); return; }
            
            // Show loading state
            document.getElementById('viewProductContent').innerHTML =
                '<p style="text-align:center;padding:40px;color:#888;">' +
                '<i class="fas fa-spinner fa-spin"></i> Loading gallery…</p>';
            
            openModal('viewProductModal');
            
            // Fetch gallery images
            fetch('includes/ajax.php?action=get_product_images&id=' + productId)
                .then(r => r.json())
                .then(res => {
                    let images = [];
                    
                    if (res.success && res.images && res.images.length) {
                        images = res.images.map(i => i.image_url);
                    } else {
                        // Fallback to main image
                        if (data.image_url) images.push(data.image_url);
                        else if (data.image) images.push('../uploads/products/' + data.image);
                        else images.push('../uploads/products/no-image.png');
                    }
                    
                    viewGallery = images;
                    viewGalleryIndex = 0;
                    renderViewProduct(data);
                })
                .catch(err => {
                    console.error(err);
                    viewGallery = [];
                    if (data.image_url) viewGallery.push(data.image_url);
                    else if (data.image) viewGallery.push('../uploads/products/' + data.image);
                    else viewGallery.push('../uploads/products/no-image.png');
                    viewGalleryIndex = 0;
                    renderViewProduct(data);
                });
        }
        
        function renderViewProduct(data) {
            const hasMultiple = viewGallery.length > 1;
            const isCloudinary = data.image_url && data.image_url.includes('cloudinary.com');
            const stockVal = parseInt(data.stock) || 0;
            
            let thumbsHtml = '';
            if (hasMultiple) {
                thumbsHtml = '<div class="view-thumbs" id="viewThumbs">';
                viewGallery.forEach((url, i) => {
                    thumbsHtml += '<div class="view-thumb ' + (i === 0 ? 'active' : '') + '" data-index="' + i + '">' +
                                  '<img src="' + url + '" alt="Image ' + (i+1) + '"></div>';
                });
                thumbsHtml += '</div>';
            }
            
            const html = `
                <div class="view-product-grid">
                    <div class="view-gallery">
                        <div class="view-main-image" onclick="openAdmLightbox(${viewGalleryIndex})">
                            <img src="${viewGallery[0]}" alt="${escapeHtml(data.name)}" id="viewMainImage">
                            <span class="view-image-counter">
                                <i class="fas fa-images"></i>
                                <span id="viewImageCounter">1</span> / ${viewGallery.length}
                            </span>
                            ${hasMultiple ? `
                                <button type="button" class="view-gallery-nav prev" onclick="event.stopPropagation();viewGalleryPrev()">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button type="button" class="view-gallery-nav next" onclick="event.stopPropagation();viewGalleryNext()">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            ` : ''}
                        </div>
                        ${thumbsHtml}
                    </div>
                    
                    <div class="view-details">
                        <h2>${escapeHtml(data.name)}</h2>
                        <div class="view-price">Ksh ${parseFloat(data.price).toLocaleString()}</div>
                        
                        <div class="view-meta">
                            <span class="view-meta-badge">
                                <i class="fas fa-tag"></i> ${escapeHtml(data.category_name || 'Uncategorized')}
                            </span>
                            <span class="view-meta-badge" style="${stockVal > 0 ? 'background:#d4edda;color:#155724;' : 'background:#f8d7da;color:#721c24;'}">
                                <i class="fas fa-cubes"></i> ${stockVal > 0 ? 'In Stock (' + stockVal + ')' : 'Out of Stock'}
                            </span>
                            <span class="view-meta-badge">
                                <i class="fas fa-toggle-on"></i> ${escapeHtml(data.status)}
                            </span>
                            ${isCloudinary ? '<span class="view-meta-badge cloud"><i class="fas fa-cloud"></i> Cloudinary</span>' : ''}
                        </div>
                        
                        <div class="view-section-title">Description</div>
                        <div class="view-description">${escapeHtml(data.description || 'No description provided.')}</div>
                        
                        <div class="view-section-title">Details</div>
                        <div class="view-data-row"><span class="label">Product ID</span><span class="value">#${data.id}</span></div>
                        ${data.sku ? `<div class="view-data-row"><span class="label">SKU</span><span class="value">${escapeHtml(data.sku)}</span></div>` : ''}
                        ${data.supplier ? `<div class="view-data-row"><span class="label">Supplier</span><span class="value">${escapeHtml(data.supplier)}</span></div>` : ''}
                        <div class="view-data-row"><span class="label">Images</span><span class="value">${viewGallery.length}</span></div>
                    </div>
                </div>
            `;
            
            document.getElementById('viewProductContent').innerHTML = html;
            
            document.querySelectorAll('#viewThumbs .view-thumb').forEach(thumb => {
                thumb.addEventListener('click', function() {
                    setViewImage(parseInt(this.dataset.index));
                });
            });
        }
        
        function setViewImage(idx) {
            if (idx < 0) idx = viewGallery.length - 1;
            if (idx >= viewGallery.length) idx = 0;
            viewGalleryIndex = idx;
            
            const mainImg = document.getElementById('viewMainImage');
            const counter = document.getElementById('viewImageCounter');
            
            if (mainImg) {
                mainImg.style.opacity = '0.4';
                setTimeout(() => {
                    mainImg.src = viewGallery[idx];
                    mainImg.style.opacity = '1';
                }, 100);
            }
            if (counter) counter.textContent = idx + 1;
            
            document.querySelectorAll('#viewThumbs .view-thumb').forEach((t, i) => {
                t.classList.toggle('active', i === idx);
            });
        }
        
        function viewGalleryPrev() { setViewImage(viewGalleryIndex - 1); }
        function viewGalleryNext() { setViewImage(viewGalleryIndex + 1); }
        
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        
        // ============================================
        // ADMIN LIGHTBOX
        // ============================================
        function openAdmLightbox(idx) {
            if (!viewGallery.length) return;
            document.getElementById('admLightboxImg').src = viewGallery[idx];
            document.getElementById('admLightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeAdmLightbox() {
            document.getElementById('admLightbox').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        function admLightboxPrev() {
            setViewImage(viewGalleryIndex - 1);
            document.getElementById('admLightboxImg').src = viewGallery[viewGalleryIndex];
        }
        function admLightboxNext() {
            setViewImage(viewGalleryIndex + 1);
            document.getElementById('admLightboxImg').src = viewGallery[viewGalleryIndex];
        }
        
        // ============================================
        // GLOBAL HANDLERS
        // ============================================
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            if (event.target.id === 'admLightbox') {
                closeAdmLightbox();
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('admLightbox').classList.contains('active')) {
                if (e.key === 'Escape') closeAdmLightbox();
                if (e.key === 'ArrowLeft') admLightboxPrev();
                if (e.key === 'ArrowRight') admLightboxNext();
                return;
            }
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(function(modal) {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
        
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
