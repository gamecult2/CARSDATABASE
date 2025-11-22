<?php
/**
 * Delete Media Handler
 */

require_once '../config.php';
require_once '../functions.php';
require_once '../functions_media.php';

requireLogin();
requireRole('admin');

$media_id = (int)($_POST['media_id'] ?? 0);

if (!$media_id) {
    setAlert('danger', 'Invalid media ID');
    header('Location: orders.php');
    exit;
}

// Get media info to find order
$media = getMediaById($media_id);

if (!$media) {
    setAlert('danger', 'Media not found');
    header('Location: orders.php');
    exit;
}

$order_id = $media['order_id'];

// Delete media
$result = deleteOrderMedia($media_id);

if ($result['success']) {
    setAlert('success', 'Media file deleted successfully');
} else {
    setAlert('danger', 'Failed to delete media: ' . ($result['error'] ?? 'Unknown error'));
}

header('Location: order_details.php?id=' . $order_id);
exit;
?>
