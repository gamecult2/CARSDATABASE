<?php
/**
 * Document Upload Handler
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $entity_type = sanitize($_POST['entity_type'] ?? '');
    $entity_id = (int)($_POST['entity_id'] ?? 0);
    
    if (!$entity_type || !$entity_id) {
        setAlert('danger', 'Invalid entity type or ID');
    } else {
        $result = uploadFile($_FILES['document'], $entity_type, $entity_id);
        
        if ($result['success']) {
            setAlert('success', 'Document uploaded successfully');
        } else {
            setAlert('danger', $result['message']);
        }
    }
    
    // Redirect back based on entity type
    $redirect = match($entity_type) {
        'client' => 'client_details.php?id=' . $entity_id,
        'car' => 'car_details.php?id=' . $entity_id,
        'order' => 'order_details.php?id=' . $entity_id,
        'container' => 'container_details.php?id=' . $entity_id,
        default => 'index.php'
    };
    
    header('Location: ' . $redirect);
    exit;
} else {
    header('Location: index.php');
    exit;
}

