<?php
/**
 * Common Functions File
 * Car Dealership Client and Logistics Management System
 */

require_once 'config.php';

// Include messaging functions if available
if (file_exists(__DIR__ . '/functions_messaging.php')) {
    require_once __DIR__ . '/functions_messaging.php';
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $role || $_SESSION['role'] === 'admin';
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . BASE_URL . '/Admin/index.php?error=access_denied');
        exit;
    }
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency (USD) - All prices displayed in USD
 */
function formatCurrency($amount) {
    return '$' . number_format($amount, 2, '.', ',');
}

/**
 * Format currency (USD) - Alias for consistency
 */
function formatCurrencyUSD($amount) {
    return formatCurrency($amount);
}

/**
 * Format currency (CNY) - Deprecated, kept for backward compatibility
 */
function formatCurrencyCNY($amount) {
    return formatCurrency($amount);
}

/**
 * Format date
 */
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Generate unique ID
 */
function generateUniqueID($prefix, $table, $column) {
    global $pdo;
    do {
        $id = $prefix . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
        $stmt->execute([$id]);
    } while ($stmt->fetchColumn() > 0);
    return $id;
}

/**
 * Calculate remaining balance for an order
 */
function calculateRemainingBalance($orderId) {
    global $pdo;
    
    // Get total sale price
    $stmt = $pdo->prepare("SELECT total_sale_price FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) return 0;
    
    $totalPrice = $order['total_sale_price'];
    
    // Get total payments
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total_paid FROM payments WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $result = $stmt->fetch();
    
    $totalPaid = $result['total_paid'] ?? 0;
    
    return max(0, $totalPrice - $totalPaid);
}

/**
 * Get shipping status for a car/order
 */
function getShippingStatus($orderId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT o.shipping_status, o.container_id, c.status as container_status, 
               c.estimated_arrival_date, c.actual_arrival_date, c.container_id as container_code
        FROM orders o
        LEFT JOIN containers c ON o.container_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    return $stmt->fetch();
}

/**
 * Calculate cost per car for a container
 */
function calculateCostPerCar($containerId) {
    global $pdo;
    
    // Get container shipping cost
    $stmt = $pdo->prepare("SELECT container_shipping_cost FROM containers WHERE id = ?");
    $stmt->execute([$containerId]);
    $container = $stmt->fetch();
    
    if (!$container) return 0;
    
    // Count cars in container
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM container_cars WHERE container_id = ?");
    $stmt->execute([$containerId]);
    $carCount = $stmt->fetchColumn();
    
    if ($carCount == 0) return 0;
    
    return $container['container_shipping_cost'] / $carCount;
}

/**
 * Calculate shipping cost per car in container
 */
function calculateShippingCostPerCar($containerId) {
    global $pdo;
    
    // Get container shipping cost
    $stmt = $pdo->prepare("SELECT container_shipping_cost FROM containers WHERE id = ?");
    $stmt->execute([$containerId]);
    $container = $stmt->fetch();
    
    if (!$container) return 0;
    
    // Count cars in container with valid orders
    $carCount = getContainerCarCount($containerId);
    
    if ($carCount == 0) return 0;
    
    return $container['container_shipping_cost'] / $carCount;
}

/**
 * Get container capacity information
 */
function getContainerCapacity($containerId) {
    global $pdo;
    
    // Get container max capacity
    $stmt = $pdo->prepare("SELECT max_capacity FROM containers WHERE id = ?");
    $stmt->execute([$containerId]);
    $container = $stmt->fetch();
    
    if (!$container) {
        return [
            'max_capacity' => 4,
            'used_capacity' => 0
        ];
    }
    
    // Count cars in container with valid orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as used_capacity
        FROM container_cars cc
        JOIN orders o ON cc.order_id = o.id
        WHERE cc.container_id = ?
    ");
    $stmt->execute([$containerId]);
    $countResult = $stmt->fetch();
    
    return [
        'max_capacity' => $container['max_capacity'],
        'used_capacity' => $countResult['used_capacity'] ?? 0
    ];
}

/**
 * Get number of cars in container with valid orders
 */
function getContainerCarCount($containerId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM container_cars cc
        JOIN orders o ON cc.order_id = o.id
        WHERE cc.container_id = ?
    ");
    $stmt->execute([$containerId]);
    return $stmt->fetchColumn();
}

/**
 * Get total number of cars in container (including those without orders)
 */
function getContainerTotalCarCount($containerId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM container_cars WHERE container_id = ?");
    $stmt->execute([$containerId]);
    return $stmt->fetchColumn();
}

/**
 * Get number of cars in container with valid orders (alternative implementation)
 */
function getContainerCarCountAlt($containerId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM container_cars cc
        JOIN orders o ON cc.order_id = o.id
        WHERE cc.container_id = ?
    ");
    $stmt->execute([$containerId]);
    return $stmt->fetchColumn();
}

/**
 * Upload file and return path
 */
function uploadFile($file, $entityType, $entityId) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check file extension
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Additional security: Validate file content
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    
    // Create directory structure
    $uploadDir = UPLOAD_DIR . $entityType . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return [
            'success' => true,
            'path' => $uploadPath,
            'filename' => $filename
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Upload multiple files
 */
function uploadMultipleFiles($files, $entityType, $entityId) {
    $results = [];
    foreach ($files['name'] as $key => $filename) {
        if (!empty($filename)) {
            $file = [
                'name' => $filename,
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];
            $results[] = uploadFile($file, $entityType, $entityId);
        }
    }
    return $results;
}

/**
 * Get documents for an entity
 */
function getDocuments($entityType, $entityId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT d.*, u.full_name as uploaded_by_name
        FROM documents d
        LEFT JOIN admin_users u ON d.uploaded_by = u.id
        WHERE d.entity_type = ? AND d.entity_id = ?
        ORDER BY d.uploaded_at DESC
    ");
    $stmt->execute([$entityType, $entityId]);
    return $stmt->fetchAll();
}

/**
 * Delete document
 */
function deleteDocument($docId) {
    global $pdo;
    
    // Get file path
    $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
    
    if ($doc) {
        // Delete file
        if (file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        
        // Delete database record
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$docId]);
        
        return true;
    }
    
    return false;
}

/**
 * Get container tracking link
 */
function getContainerTrackingLink($containerNumber) {
    if (empty($containerNumber)) {
        return null;
    }
    return 'https://www.searates.com/container/tracking/?number=' . urlencode($containerNumber);
}

/**
 * Get financial summary
 */
function getFinancialSummary() {
    global $pdo;
    
    // Total sales revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_sale_price), 0) as total_revenue FROM orders");
    $totalRevenue = $stmt->fetchColumn();
    
    // Total payments received
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) as total_paid FROM payments");
    $totalPaid = $stmt->fetchColumn();
    
    // Outstanding payments
    $outstanding = $totalRevenue - $totalPaid;
    
    // Calculate profitability (simplified - would need to account for all costs)
    // Fixed to avoid division by zero
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(o.total_sale_price - c.purchase_price_usd - 
                (CASE 
                    WHEN (SELECT COUNT(*) FROM container_cars cc WHERE cc.container_id = o.container_id) > 0 
                    THEN cont.container_shipping_cost / (SELECT COUNT(*) FROM container_cars cc WHERE cc.container_id = o.container_id)
                    ELSE 0
                END)), 0) as profit
        FROM orders o
        JOIN cars c ON o.car_id = c.id
        LEFT JOIN containers cont ON o.container_id = cont.id
    ");
    $profit = $stmt->fetchColumn();
    
    return [
        'total_revenue' => $totalRevenue,
        'total_paid' => $totalPaid,
        'outstanding' => $outstanding,
        'profit' => $profit
    ];
}

/**
 * Get overdue payments
 */
function getOverduePayments($daysOverdue = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as client_name, c.phone, c.email,
               (o.total_sale_price - COALESCE(SUM(p.amount_paid), 0)) as remaining_balance,
               DATEDIFF(CURDATE(), o.order_date) as days_since_order
        FROM orders o
        JOIN clients c ON o.client_id = c.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.total_sale_price > 0
        GROUP BY o.id, c.name, c.phone, c.email, o.total_sale_price, o.order_date
        HAVING remaining_balance > 0 AND days_since_order > ?
        ORDER BY days_since_order DESC
    ");
    $stmt->execute([$daysOverdue]);
    return $stmt->fetchAll();
}

/**
 * Display alert message
 */
function displayAlert($type = 'info', $message = '') {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        $type = $alert['type'] ?? $type;
        $message = $alert['message'] ?? $message;
    }
    
    if ($message) {
        $class = 'alert-' . $type;
        echo "<div class='alert $class alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($message);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
        echo "</div>";
    }
}

/**
 * Set alert message
 */
function setAlert($type, $message) {
    $_SESSION['alert'] = ['type' => $type, 'message' => $message];
}

/**
 * Get CSS class for status badges
 */
function getStatusClass($status) {
    $statusClasses = [
        'Scheduled' => 'secondary',
        'En Route' => 'primary',
        'Arrived' => 'info',
        'Unloaded' => 'warning',
        'Completed' => 'success',
        'Awaiting Container' => 'secondary',
        'Shipped on Container' => 'primary',
        'Arrived in Algeria' => 'info',
        'Customs Cleared' => 'warning',
        'Ready for Pickup' => 'primary',
        'Delivered' => 'success'
    ];
    
    return $statusClasses[$status] ?? 'secondary';
}

/**
 * Clean up expired MFA codes
 */
function cleanupExpiredMFACodes() {
    global $pdo;
    
    // Delete expired codes (older than 1 hour)
    $stmt = $pdo->prepare("
        DELETE FROM mfa_codes 
        WHERE used = 1 OR expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    return $stmt->execute();
}

function calculateProfit($orderId) {
    global $pdo;
    
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.total_sale_price, o.container_id, cont.container_shipping_cost
        FROM orders o
        LEFT JOIN containers cont ON o.container_id = cont.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        return 0;
    }
    
    $revenue = $order['total_sale_price'];
    
    // Calculate shipping cost for this order
    $shipping_cost = 0;
    if ($order['container_id'] && $order['container_shipping_cost']) {
        // Get number of orders in container with valid container_cars entries
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM container_cars cc
            JOIN orders o ON cc.order_id = o.id
            WHERE cc.container_id = ?
        ");
        $stmt->execute([$order['container_id']]);
        $orders_in_container = $stmt->fetchColumn();
        
        // Calculate shipping cost per order only if there are orders in container
        $shipping_cost = $orders_in_container > 0 ? $order['container_shipping_cost'] / $orders_in_container : 0;
    }
    
    return $revenue - $shipping_cost;
}
