<?php

require_once 'config.php';

/**
 * Upload image to Cloudinary
 */
function uploadToCloudinary($file_path, $folder = 'products') {
    global $cloudinary;
    
    try {
        // Generate a unique public ID
        $public_id = $folder . '/' . time() . '_' . uniqid() . '_' . pathinfo(basename($file_path), PATHINFO_FILENAME);
        $public_id = preg_replace('/[^a-zA-Z0-9._\/-]/', '', $public_id);
        
        // Upload to Cloudinary
        $result = $cloudinary->uploadApi()->upload(
            $file_path,
            [
                'public_id' => $public_id,
                'folder' => $folder,
                'quality' => 'auto:best',
                'fetch_format' => 'auto',
                'transformation' => [
                    ['width' => 1200, 'height' => 1200, 'crop' => 'limit', 'quality' => 'auto']
                ]
            ]
        );
        
        return [
            'success' => true,
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
            'width' => $result['width'] ?? null,
            'height' => $result['height'] ?? null,
        ];
        
    } catch (Exception $e) {
        error_log('Cloudinary upload error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete image from Cloudinary
 */
function deleteFromCloudinary($public_id) {
    global $cloudinary;
    
    if (empty($public_id)) {
        return ['success' => true, 'message' => 'No image to delete'];
    }
    
    try {
        $result = $cloudinary->uploadApi()->destroy($public_id);
        return ['success' => true, 'result' => $result];
    } catch (Exception $e) {
        error_log('Cloudinary delete error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get product image URL (supports both Cloudinary and local)
 * Accepts either a product array OR (image_name, image_url) strings.
 */
function getProductImage($image_name = null, $image_url = null) {
    // If called with an array (legacy signature), extract fields
    if (is_array($image_name)) {
        $product = $image_name;
        $image_url = $product['image_url'] ?? null;
        $image_name = $product['image'] ?? null;
    }
    
    // If it's already a full URL
    if (!empty($image_url)) {
        return $image_url;
    }
    
    if (!empty($image_name)) {
        // URL passed as the first arg
        if (filter_var($image_name, FILTER_VALIDATE_URL)) {
            return $image_name;
        }
        // Local file path
        if (defined('UPLOAD_URL')) {
            return UPLOAD_URL . $image_name;
        }
        return '../uploads/products/' . $image_name;
    }
    
    return (defined('UPLOAD_URL') ? UPLOAD_URL : '../uploads/products/') . 'no-image.png';
}

/**
 * Legacy alias – accepts a product array
 */
function getProductImageUrl($product) {
    return getProductImage($product);
}

/**
 * Get Cloudinary image with transformations
 */
function getCloudinaryImage($public_id, $options = []) {
    global $cloudinary;
    
    if (empty($public_id)) {
        return null;
    }
    
    $transformations = [];
    
    if (isset($options['width'])) {
        $transformations[] = ['width' => $options['width']];
    }
    if (isset($options['height'])) {
        $transformations[] = ['height' => $options['height']];
    }
    if (isset($options['crop'])) {
        $transformations[] = ['crop' => $options['crop']];
    }
    
    return $cloudinary->image($public_id, ['transformation' => $transformations]);
}

/**
 * Fetch all gallery images for a product.
 * Falls back to the product's main image if no rows exist.
 */
function getProductImages($product_id, $product = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, image_url, image_public_id, display_order, is_primary
            FROM product_images
            WHERE product_id = ?
            ORDER BY is_primary DESC, display_order ASC, id ASC
        ");
        $stmt->execute([$product_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($images)) {
            return $images;
        }
    } catch (PDOException $e) {
        error_log('getProductImages error: ' . $e->getMessage());
    }
    
    // Fallback: single entry using the product's main image
    if ($product) {
        $url = null;
        if (!empty($product['image_url'])) {
            $url = $product['image_url'];
        } elseif (!empty($product['image'])) {
            $url = getProductImage($product['image'], null);
        }
        
        if ($url) {
            return [[
                'id' => 0,
                'image_url' => $url,
                'image_public_id' => $product['image_public_id'] ?? null,
                'display_order' => 0,
                'is_primary' => true,
            ]];
        }
    }
    
    return [[
        'id' => 0,
        'image_url' => getProductImage(null, null),
        'image_public_id' => null,
        'display_order' => 0,
        'is_primary' => true,
    ]];
}
