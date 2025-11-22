<?php
/**
 * Secure Media Delivery Endpoint
 * Car Dealership Client and Logistics Management System
 * Serves media files with access control and logging
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'functions_media.php';

// Require authentication
requireLogin();

// Get media ID from GET parameter
$media_id = (int)($_GET['id'] ?? 0);

if (!$media_id) {
    http_response_code(400);
    die('Bad Request: Media ID required');
}

// Get media info
$media = getMediaById($media_id);

if (!$media) {
    http_response_code(404);
    die('Media not found');
}

// Check access permissions
if (!canAccessMedia($media_id, $_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(403);
    die('Access Denied: You do not have permission to view this media');
}

// Verify file exists
if (!file_exists($media['file_path'])) {
    http_response_code(404);
    die('Media file not found on server');
}

// Log access
logMediaAccess($media_id, $_SESSION['user_id'], 'view');

// Determine file size
$file_size = filesize($media['file_path']);

// Get file extension
$ext = strtolower(pathinfo($media['file_path'], PATHINFO_EXTENSION));

// Set appropriate MIME type
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'avi' => 'video/x-msvideo',
    'mkv' => 'video/x-matroska',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$mime_type = $mime_types[$ext] ?? 'application/octet-stream';

// Handle range requests for video streaming
if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = intval($matches[1]);
        $end = $matches[2] !== '' ? intval($matches[2]) : $file_size - 1;

        if ($start > $end || $start >= $file_size || $end >= $file_size) {
            http_response_code(416);
            header("Content-Range: bytes */$file_size");
            exit;
        }

        http_response_code(206);
        header("Content-Range: bytes $start-$end/$file_size");
        header("Content-Length: " . ($end - $start + 1));
        
        $fp = fopen($media['file_path'], 'rb');
        fseek($fp, $start);
        echo fread($fp, $end - $start + 1);
        fclose($fp);
        exit;
    }
}

// Set headers for file download/display
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . $file_size);
header('Content-Disposition: inline; filename="' . basename($media['file_path']) . '"');
header('Cache-Control: public, max-age=86400'); // Cache for 24 hours
header('Last-Modified: ' . gmdate('r', filemtime($media['file_path'])));
header('Accept-Ranges: bytes');

// Prevent caching of private data
session_cache_limiter('private');

// Output file
readfile($media['file_path']);
exit;
?>
