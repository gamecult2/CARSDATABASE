<?php
/**
 * View Individual Message
 * Car Dealership Client and Logistics Management System
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'functions_messaging.php';
require_once 'functions_media.php';

requireLogin();

// Only clients can access
if ($_SESSION['role'] !== 'client') {
    header('Location: Admin/index.php');
    exit;
}

// Get client ID - directly from session for clients
$client_id = $_SESSION['user_id'];

// Get message ID from URL
$message_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$message_id) {
    setAlert('danger', 'Invalid message ID');
    header('Location: messages.php');
    exit;
}

// Get message (verify it belongs to client)
$stmt = $pdo->prepare("
    SELECT m.*, o.order_id, u_sender.full_name as sender_name
    FROM messages m
    LEFT JOIN orders o ON m.order_id = o.id
    LEFT JOIN admin_users u_sender ON m.sender_id = u_sender.id
    WHERE m.id = ? AND m.client_id = ?
");
$stmt->execute([$message_id, $client_id]);
$message = $stmt->fetch();

if (!$message) {
    setAlert('danger', 'Message not found');
    header('Location: messages.php');
    exit;
}

// Mark as read if not already read
if (!$message['is_read']) {
    markMessageAsRead($message_id, $client_id);
    $message['is_read'] = 1;
}

// Get message media (attachments) - currently not supported for messages
$media = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Message - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .message-content {
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .attachment-item {
            display: inline-block;
            margin: 5px;
            text-align: center;
        }
        .attachment-preview {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar navbar-light bg-white mb-4 rounded shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="client_portal.php">
                    <i class="bi bi-car-front"></i> <?php echo APP_NAME; ?>
                </a>
                <div>
                    <a href="messages.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Back to Messages
                    </a>
                    <a href="client_portal.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-speedometer2"></i> Portal
                    </a>
                </div>
            </div>
        </nav>
        
        <?php displayAlert(); ?>
        
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($message['subject']); ?></h5>
                    <?php if (!$message['is_read']): ?>
                        <span class="badge bg-warning">Unread</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>From:</strong> <?php echo htmlspecialchars($message['sender_name'] ?? 'Admin'); ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <strong>Date:</strong> <?php echo formatDate($message['created_at']); ?>
                    </div>
                </div>
                
                <?php if ($message['order_id']): ?>
                    <div class="mb-3">
                        <strong>Related Order:</strong> 
                        <span class="badge bg-info"><?php echo htmlspecialchars($message['order_id']); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="message-content bg-light p-3 rounded mb-4">
                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                </div>
                
                <?php if (!empty($media)): ?>
                    <div class="mb-3">
                        <h6><i class="bi bi-paperclip"></i> Attachments (<?php echo count($media); ?>)</h6>
                        <div>
                            <?php foreach ($media as $file): ?>
                                <div class="attachment-item">
                                    <?php if (strpos($file['media_type'], 'image') === 0): ?>
                                        <a href="serve_media.php?id=<?php echo $file['id']; ?>" target="_blank">
                                            <img src="serve_media.php?id=<?php echo $file['id']; ?>&thumb=1" 
                                                 alt="<?php echo htmlspecialchars($file['file_name']); ?>" 
                                                 class="attachment-preview img-thumbnail">
                                        </a>
                                    <?php else: ?>
                                        <a href="serve_media.php?id=<?php echo $file['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($file['file_name']); ?>
                                        </a>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1">
                                        <?php echo formatBytes($file['file_size']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between">
                    <a href="messages.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Messages
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-primary">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>