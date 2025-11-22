<?php
/**
 * Media Management Functions
 * Car Dealership Client and Logistics Management System
 * Handles secure upload, validation, retrieval, and deletion of order media
 */

require_once 'config.php';

/**
 * Validate media file for upload
 */
function validateMediaFile($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped file upload'
        ];
        return ['valid' => false, 'error' => $error_messages[$file['error']] ?? 'Unknown upload error'];
    }

    // Check file size
    if ($file['size'] > MAX_MEDIA_FILE_SIZE) {
        return ['valid' => false, 'error' => 'File size exceeds maximum allowed (' . formatBytes(MAX_MEDIA_FILE_SIZE) . ')'];
    }

    if ($file['size'] === 0) {
        return ['valid' => false, 'error' => 'File is empty'];
    }

    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate extension
    if (!in_array($ext, MEDIA_FILE_TYPES)) {
        return ['valid' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', MEDIA_FILE_TYPES)];
    }

    // Validate MIME type
    $valid_mime_types = [
        'jpg' => ['image/jpeg', 'image/jpg'],
        'jpeg' => ['image/jpeg', 'image/jpg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime'],
        'avi' => ['video/x-msvideo', 'video/avi'],
        'mkv' => ['video/x-matroska']
    ];

    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset($valid_mime_types[$ext]) || !in_array($mime, $valid_mime_types[$ext])) {
        // Additional check using file command if available
        if (!function_exists('mime_content_type')) {
            // Fallback: accept if extension matches (less secure but works without mime extension)
            if (!in_array($ext, MEDIA_FILE_TYPES)) {
                return ['valid' => false, 'error' => 'Invalid MIME type for file'];
            }
        } else {
            return ['valid' => false, 'error' => 'Invalid MIME type for file'];
        }
    }

    return ['valid' => true, 'extension' => $ext, 'mime' => $mime];
}

/**
 * Determine media type (photo or video)
 */
function getMediaType($extension) {
    $photo_types = ['jpg', 'jpeg', 'png', 'webp'];
    $video_types = ['mp4', 'webm', 'mov', 'avi', 'mkv'];

    if (in_array($extension, $photo_types)) {
        return 'photo';
    } elseif (in_array($extension, $video_types)) {
        return 'video';
    }
    
    return null;
}

/**
 * Format bytes to human-readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Upload media file for order
 */
function uploadOrderMedia($file, $order_id, $description = '') {
    global $pdo;

    // Verify order exists
    $stmt = $pdo->prepare("SELECT id, client_id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['success' => false, 'error' => 'Order not found'];
    }

    // Validate file
    $validation = validateMediaFile($file);
    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }

    // Determine media type
    $media_type = getMediaType($validation['extension']);
    if (!$media_type) {
        return ['success' => false, 'error' => 'Unable to determine media type'];
    }

    // Generate secure filename
    $timestamp = time();
    $unique_id = uniqid();
    $original_name = basename($file['name']);
    $filename = $order_id . '_' . $timestamp . '_' . $unique_id . '.' . $validation['extension'];

    // Create upload directory
    $upload_dir = MEDIA_UPLOAD_DIR . 'orders/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'error' => 'Failed to save file to server'];
    }

    // Set file permissions
    chmod($file_path, 0644);

    // Generate thumbnail for photos
    $thumbnail_path = null;
    if ($media_type === 'photo') {
        $thumbnail_path = generateThumbnail($file_path, $validation['extension']);
    }

    try {
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO order_media 
            (order_id, media_type, file_name, file_path, file_size, mime_type, description, thumbnail_path, uploaded_by, visibility)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $order_id,
            $media_type,
            $original_name,
            $file_path,
            $file['size'],
            $validation['mime'],
            $description ?: null,
            $thumbnail_path,
            $_SESSION['user_id'] ?? null,
            'client' // Default visibility for clients
        ]);

        $media_id = $pdo->lastInsertId();

        return [
            'success' => true,
            'media_id' => $media_id,
            'media_type' => $media_type,
            'file_name' => $original_name,
            'file_size' => $file['size'],
            'thumbnail_path' => $thumbnail_path
        ];

    } catch (Exception $e) {
        // Delete file if database insert fails
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get media files for order
 */
function getOrderMedia($order_id, $media_type = null) {
    global $pdo;

    $query = "
        SELECT om.*, u.full_name as uploaded_by_name
        FROM order_media om
        LEFT JOIN admin_users u ON om.uploaded_by = u.id
        WHERE om.order_id = ?
    ";

    $params = [$order_id];

    if ($media_type) {
        $query .= " AND om.media_type = ?";
        $params[] = $media_type;
    }

    $query .= " ORDER BY om.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Get media files for message
 */
function getMessageMedia($message_id, $media_type = null) {
    global $pdo;

    // For now, return empty array since we don't have message media support
    // This would need database schema changes to support properly
    return [];
}

/**
 * Generate thumbnail for photo
 */
function generateThumbnail($image_path, $extension, $thumb_width = 200, $thumb_height = 200) {
    // Check if GD library is available
    if (!extension_loaded('gd')) {
        return null; // Thumbnails not available without GD
    }

    // Create thumbnail directory
    $thumb_dir = dirname($image_path) . '/thumbs/';
    if (!file_exists($thumb_dir)) {
        mkdir($thumb_dir, 0755, true);
    }

    $thumb_filename = basename($image_path, '.' . $extension) . '_thumb.jpg';
    $thumb_path = $thumb_dir . $thumb_filename;

    try {
        // Load image based on type
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $src = imagecreatefromjpeg($image_path);
                break;
            case 'png':
                $src = imagecreatefrompng($image_path);
                break;
            case 'webp':
                $src = imagecreatefromwebp($image_path);
                break;
            default:
                return null;
        }

        if (!$src) {
            return null;
        }

        // Get image dimensions
        $width = imagesx($src);
        $height = imagesy($src);

        // Calculate aspect ratio and crop
        $ratio = $width / $height;
        $thumb_ratio = $thumb_width / $thumb_height;

        if ($ratio > $thumb_ratio) {
            // Crop width
            $new_width = $height * $thumb_ratio;
            $x = ($width - $new_width) / 2;
            $crop_width = $new_width;
            $crop_height = $height;
        } else {
            // Crop height
            $new_height = $width / $thumb_ratio;
            $y = ($height - $new_height) / 2;
            $crop_width = $width;
            $crop_height = $new_height;
        }

        // Create thumbnail
        $thumb = imagecreatetruecolor($thumb_width, $thumb_height);
        imagecopyresampled($thumb, $src, 0, 0, $x ?? 0, $y ?? 0, $thumb_width, $thumb_height, $crop_width, $crop_height);

        // Save thumbnail as JPEG
        imagejpeg($thumb, $thumb_path, 85);

        // Free resources
        imagedestroy($src);
        imagedestroy($thumb);

        // Set permissions
        chmod($thumb_path, 0644);

        return $thumb_path;

    } catch (Exception $e) {
        // Return null if thumbnail generation fails
        return null;
    }
}

/**
 * Get media by ID with security check
 */
function getMediaById($media_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT om.*, o.client_id, u.full_name as uploaded_by_name
        FROM order_media om
        JOIN orders o ON om.order_id = o.id
        LEFT JOIN admin_users u ON om.uploaded_by = u.id
        WHERE om.id = ?
    ");

    $stmt->execute([$media_id]);
    return $stmt->fetch();
}

/**
 * Check if user can access media
 */
function canAccessMedia($media_id, $user_id, $user_role) {
    $media = getMediaById($media_id);

    if (!$media) {
        return false;
    }

    // Admin can access all
    if ($user_role === 'admin') {
        return true;
    }

    // Client can access their own orders
    if ($user_role === 'client') {
        $stmt = $GLOBALS['pdo']->prepare("SELECT id FROM clients WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $client = $stmt->fetch();

        if ($client && $client['id'] == $media['client_id']) {
            return true;
        }
    }

    return false;
}

/**
 * Delete media file
 */
function deleteOrderMedia($media_id) {
    global $pdo;

    // Get media info
    $media = getMediaById($media_id);

    if (!$media) {
        return ['success' => false, 'error' => 'Media not found'];
    }

    try {
        // Delete file
        if (file_exists($media['file_path'])) {
            unlink($media['file_path']);
        }

        // Delete thumbnail if exists
        if ($media['thumbnail_path'] && file_exists($media['thumbnail_path'])) {
            unlink($media['thumbnail_path']);
        }

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM order_media WHERE id = ?");
        $stmt->execute([$media_id]);

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update media description
 */
function updateMediaDescription($media_id, $description) {
    global $pdo;

    $stmt = $pdo->prepare("UPDATE order_media SET description = ? WHERE id = ?");
    return $stmt->execute([$description, $media_id]);
}

/**
 * Update media visibility
 */
function updateMediaVisibility($media_id, $visibility) {
    global $pdo;

    if (!in_array($visibility, ['admin', 'client', 'public'])) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE order_media SET visibility = ? WHERE id = ?");
    return $stmt->execute([$visibility, $media_id]);
}

/**
 * Count media by type for order
 */
function countOrderMedia($order_id, $media_type = null) {
    global $pdo;

    $query = "SELECT COUNT(*) as count FROM order_media WHERE order_id = ?";
    $params = [$order_id];

    if ($media_type) {
        $query .= " AND media_type = ?";
        $params[] = $media_type;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();

    return $result['count'] ?? 0;
}

/**
 * Get total media size for order
 */
function getTotalMediaSize($order_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT SUM(file_size) as total_size FROM order_media WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $result = $stmt->fetch();

    return $result['total_size'] ?? 0;
}

/**
 * Log media access for audit trail
 */
function logMediaAccess($media_id, $user_id, $action = 'view') {
    global $pdo;

    try {
        // Create media_logs table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS media_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                media_id INT NOT NULL,
                user_id INT,
                action VARCHAR(50),
                ip_address VARCHAR(50),
                accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (media_id) REFERENCES order_media(id) ON DELETE CASCADE
            )
        ");

        $stmt = $pdo->prepare("
            INSERT INTO media_logs (media_id, user_id, action, ip_address)
            VALUES (?, ?, ?, ?)
        ");

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt->execute([$media_id, $user_id, $action, $ip]);

        return true;

    } catch (Exception $e) {
        // Logging failure should not break functionality
        return false;
    }
}

/**
 * Clean up orphaned media files (media with missing orders)
 */
function cleanupOrphanedMedia() {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT om.* FROM order_media om
            LEFT JOIN orders o ON om.order_id = o.id
            WHERE o.id IS NULL
        ");
        $stmt->execute();
        $orphaned = $stmt->fetchAll();

        foreach ($orphaned as $media) {
            deleteOrderMedia($media['id']);
        }

        return ['success' => true, 'count' => count($orphaned)];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Bulk upload media files
 */
function uploadBulkMedia($files, $order_id, $descriptions = []) {
    $results = [
        'success' => [],
        'failed' => [],
        'total' => 0,
        'successful' => 0,
        'failed_count' => 0
    ];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return ['success' => false, 'error' => 'Invalid file input'];
    }

    foreach ($files['name'] as $key => $filename) {
        if (empty($filename)) {
            continue;
        }

        $results['total']++;

        $file = [
            'name' => $filename,
            'type' => $files['type'][$key],
            'tmp_name' => $files['tmp_name'][$key],
            'error' => $files['error'][$key],
            'size' => $files['size'][$key]
        ];

        $description = $descriptions[$key] ?? '';
        $result = uploadOrderMedia($file, $order_id, $description);

        if ($result['success']) {
            $results['successful']++;
            $results['success'][] = $result;
        } else {
            $results['failed_count']++;
            $results['failed'][] = [
                'filename' => $filename,
                'error' => $result['error']
            ];
        }
    }

    return $results;
}
?>
