<?php
/**
 * Messaging Functions
 * Car Dealership Client and Logistics Management System
 */

require_once 'config.php';
require_once 'functions.php';

/**
 * Generate unique message ID
 */
function generateMessageID() {
    global $pdo;
    do {
        $id = 'MSG' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE message_id = ?");
        $stmt->execute([$id]);
    } while ($stmt->fetchColumn() > 0);
    return $id;
}

/**
 * Send message
 */
function sendMessage($client_id, $order_id, $subject, $message, $sender_type = 'client', $sender_id = null, $recipient_id = null) {
    global $pdo;
    
    $message_id = generateMessageID();
    
    // Determine recipient (admin for client messages, client for admin messages)
    if ($sender_type === 'client') {
        $recipient_type = 'admin';
        // Get first admin user as recipient
        if (!$recipient_id) {
            $stmt = $pdo->query("SELECT id FROM admin_users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
            $admin = $stmt->fetch();
            $recipient_id = $admin['id'] ?? null;
        }
    } else {
        $recipient_type = 'client';
        // For admin to client messages, recipient_id should be the client_id
        if (!$recipient_id) {
            $recipient_id = $client_id;
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (message_id, order_id, client_id, sender_id, sender_type, recipient_id, recipient_type, subject, message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$message_id, $order_id, $client_id, $sender_id, $sender_type, $recipient_id, $recipient_type, $subject, $message])) {
        return $pdo->lastInsertId();
    }
    return false;
}

/**
 * Get messages for a client
 */
function getClientMessages($client_id, $unread_only = false) {
    global $pdo;
    
    $where = "m.client_id = ?";
    $params = [$client_id];
    
    if ($unread_only) {
        $where .= " AND m.is_read = 0";
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*, 
               o.order_id,
               u_sender.full_name as sender_name,
               u_recipient.full_name as recipient_name
        FROM messages m
        LEFT JOIN orders o ON m.order_id = o.id
        LEFT JOIN admin_users u_sender ON m.sender_id = u_sender.id
        LEFT JOIN admin_users u_recipient ON m.recipient_id = u_recipient.id
        WHERE $where
        ORDER BY m.created_at DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get messages for admin (all client messages)
 */
function getAdminMessages($unread_only = false) {
    global $pdo;
    
    $where = "m.recipient_type = 'admin'";
    $params = [];
    
    if ($unread_only) {
        $where .= " AND m.is_read = 0";
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*, 
               c.name as client_name,
                   c.user_id as client_user_id,
                   o.order_id,
               u_sender.full_name as sender_name
        FROM messages m
        JOIN clients c ON m.client_id = c.id
        LEFT JOIN orders o ON m.order_id = o.id
        LEFT JOIN admin_users u_sender ON m.sender_id = u_sender.id
        WHERE $where
        ORDER BY m.created_at DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Mark message as read
 */
function markMessageAsRead($message_id, $user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1, read_at = NOW() 
        WHERE id = ? AND (recipient_id = ? OR sender_id = ?)
    ");
    return $stmt->execute([$message_id, $user_id, $user_id]);
}

/**
 * Get unread message count
 */
function getUnreadMessageCount($client_id = null, $is_admin = false) {
    global $pdo;
    
    if ($is_admin) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE recipient_type = 'admin' AND is_read = 0");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE client_id = ? AND is_read = 0");
        $stmt->execute([$client_id]);
    }
    return $stmt->fetchColumn();
}

/**
 * Get message details
 */
function getMessageDetails($message_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.*, 
               c.name as client_name,
               o.order_id,
               u_sender.full_name as sender_name,
               u_recipient.full_name as recipient_name
        FROM messages m
        JOIN clients c ON m.client_id = c.id
        LEFT JOIN orders o ON m.order_id = o.id
        LEFT JOIN admin_users u_sender ON m.sender_id = u_sender.id
        LEFT JOIN admin_users u_recipient ON m.recipient_id = u_recipient.id
        WHERE m.id = ?
    ");
    $stmt->execute([$message_id]);
    return $stmt->fetch();
}