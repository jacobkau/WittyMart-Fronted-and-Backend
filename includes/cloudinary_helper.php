<?php
function getProductImage($image_name, $image_url = null) {
    // If Cloudinary URL exists, use it
    if (!empty($image_url)) {
        return $image_url;
    }
    
    // Fallback to local image
    if (!empty($image_name) && file_exists(UPLOAD_DIR . $image_name)) {
        return '../uploads/products/' . $image_name;
    }
    
    return '../uploads/products/no-image.png';
}
/**
 * Upload image to Cloudinary
 */
function uploadToCloudinary($file_path, $folder = 'products') {
    global $cloudinary;
    
    try {
        // Generate a unique public ID
        $public_id = $folder . '/' . time() . '_' . pathinfo(basename($file_path), PATHINFO_FILENAME);
        
        // Upload to Cloudinary
        $result = $cloudinary->uploadApi()->upload(
            $file_path,
            [
                'public_id' => $public_id,
                'folder' => $folder,
                'quality' => 'auto:best',
                'fetch_format' => 'auto',
                'transformation' => [
                    ['width' => 800, 'height' => 800, 'crop' => 'limit', 'quality' => 'auto']
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
 */
function getProductImageUrl($product) {
    // Check if Cloudinary URL exists first
    if (!empty($product['image_url'])) {
        return $product['image_url'];
    }
    
    // Fallback to local image if available
    if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])) {
        return UPLOAD_URL . $product['image'];
    }
    
    // Default no-image placeholder
    return UPLOAD_URL . 'no-image.png';
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
