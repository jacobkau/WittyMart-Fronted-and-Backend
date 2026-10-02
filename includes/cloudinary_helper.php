<?php

/**
 * Upload a single image to Cloudinary (returns URL + public_id).
 */
if (!function_exists('uploadToCloudinary')) {
    function uploadToCloudinary($file_path, $folder = 'products') {
        global $cloudinary;
        
        try {
            $public_id = $folder . '/' . time() . '_' . uniqid() . '_' . pathinfo(basename($file_path), PATHINFO_FILENAME);
            $public_id = preg_replace('/[^a-zA-Z0-9._\/-]/', '', $public_id);
            
            $result = $cloudinary->uploadApi()->upload(
                $file_path,
                [
                    'public_id'    => $public_id,
                    'folder'       => $folder,
                    'quality'      => 'auto:best',
                    'fetch_format' => 'auto',
                    'transformation' => [
                        ['width' => 1200, 'height' => 1200, 'crop' => 'limit', 'quality' => 'auto']
                    ]
                ]
            );
            
            return [
                'success'   => true,
                'url'       => $result['secure_url'],
                'public_id' => $result['public_id'],
                'width'     => $result['width'] ?? null,
                'height'    => $result['height'] ?? null,
            ];
        } catch (Exception $e) {
            error_log('Cloudinary upload error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Delete an image from Cloudinary by public_id.
 */
if (!function_exists('deleteFromCloudinary')) {
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
}

/**
 * Get the main product image URL (Cloudinary > local > placeholder).
 */
if (!function_exists('getProductImage')) {
    function getProductImage($image_name = null, $image_url = null) {
        // If called with an array, extract image and image_url fields
        if (is_array($image_name)) {
            $product   = $image_name;
            $image_url = $product['image_url'] ?? null;
            $image_name = $product['image'] ?? null;
        }
        
        if (!empty($image_url)) {
            return $image_url;
        }
        if (!empty($image_name)) {
            if (filter_var($image_name, FILTER_VALIDATE_URL)) {
                return $image_name;
            }
            if (defined('UPLOAD_URL')) {
                return UPLOAD_URL . $image_name;
            }
            return '../uploads/products/' . $image_name;
        }
        return (defined('UPLOAD_URL') ? UPLOAD_URL : '../uploads/products/') . 'no-image.png';
    }
}

/**
 * Legacy alias – accepts a product array.
 */
if (!function_exists('getProductImageUrl')) {
    function getProductImageUrl($product) {
        if (is_array($product)) {
            return getProductImage($product['image'] ?? null, $product['image_url'] ?? null);
        }
        return getProductImage($product);
    }
}

/**
 * Get a Cloudinary-transformed URL (optional helper).
 */
if (!function_exists('getCloudinaryImage')) {
    function getCloudinaryImage($public_id, $options = []) {
        global $cloudinary;
        if (empty($public_id)) return null;
        
        $transformations = [];
        if (isset($options['width']))  $transformations[] = ['width'  => $options['width']];
        if (isset($options['height'])) $transformations[] = ['height' => $options['height']];
        if (isset($options['crop']))   $transformations[] = ['crop'   => $options['crop']];
        
        return $cloudinary->image($public_id, ['transformation' => $transformations]);
    }
}

/**
 * Fetch all images for a product from `product_images`.
 * Falls back to the product's main image, then placeholder.
 */
if (!function_exists('getProductImages')) {
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
        
        // Fallback: build a single entry from the product's main image
        if ($product) {
            $url = null;
            if (!empty($product['image_url'])) {
                $url = $product['image_url'];
            } elseif (!empty($product['image'])) {
                $url = getProductImage($product['image'], null);
            }
            
            if ($url) {
                return [[
                    'id'              => 0,
                    'image_url'       => $url,
                    'image_public_id' => $product['image_public_id'] ?? null,
                    'display_order'   => 0,
                    'is_primary'      => true,
                ]];
            }
        }
        
        return [[
            'id'              => 0,
            'image_url'       => getProductImage(null, null),
            'image_public_id' => null,
            'display_order'   => 0,
            'is_primary'      => true,
        ]];
    }
}
