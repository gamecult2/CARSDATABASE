<?php
/**
 * Client Messaging Interface
 * Car Dealership Client and Logistics Management System
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'functions_messaging.php';

requireLogin();

// Only clients can access
if ($_SESSION['role'] !== 'client') {
    header('Location: Admin/index.php');
    exit;
}

// Get client ID - directly from session for clients
$client_id = $_SESSION['user_id'];

// Verify client exists
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    setAlert('danger', 'Client profile not found');
    header('Location: logout.php');
    exit;
}

// Get pre-selected order_id from URL
$preselected_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

// Get client orders for dropdown
$stmt = $pdo->prepare("
    SELECT o.id, o.order_id, car.brand, car.model, car.year
    FROM orders o
    JOIN cars car ON o.car_id = car.id
    WHERE o.client_id = ?
    ORDER BY o.order_date DESC
");
$stmt->execute([$client_id]);
$client_orders = $stmt->fetchAll();

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    if (empty($message)) {
        setAlert('danger', 'Message cannot be empty');
    } else {
        // sendMessage($client_id, $order_id, $subject, $message, $sender_type, $sender_id, $recipient_id)
        $message_id = sendMessage($client_id, $order_id, $subject, $message, 'client', $client_id, null);
        if ($message_id) {
            // Commenting out file upload for now as it's causing errors
            // Handle file uploads
            // if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            //     foreach ($_FILES['attachments']['name'] as $key => $filename) {
            //         if (!empty($filename)) {
            //             $file = [
            //                 'name' => $filename,
            //                 'type' => $_FILES['attachments']['type'][$key],
            //                 'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
            //                 'error' => $_FILES['attachments']['error'][$key],
            //                 'size' => $_FILES['attachments']['size'][$key]
            //             ];
            //             uploadMessageMedia($file, $message_id);
            //         }
            //     }
            // }
            setAlert('success', 'Message sent successfully');
            header('Location: messages.php');
            exit;
        } else {
            setAlert('danger', 'Error sending message');
        }
    }
}

// Handle mark as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    markMessageAsRead((int)$_GET['id'], $client_id);
    header('Location: messages.php');
    exit;
}

// Get messages
$messages = getClientMessages($client_id);

// Get unread count
$unread_count = getUnreadMessageCount($client_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .message-card {
            border-left: 4px solid #667eea;
            transition: all 0.2s;
        }
        .message-card.unread {
            border-left-color: #dc3545;
            background-color: #fff8f8;
        }
        .attachment-preview {
            max-width: 100px;
            max-height: 100px;
            margin-right: 10px;
            margin-bottom: 10px;
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
                <a href="client_portal.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Portal
                </a>
            </div>
        </nav>
        
        <?php displayAlert(); ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-envelope-plus"></i> New Message</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="order_id" class="form-label">Related Order (Optional)</label>
                                <select class="form-select" id="order_id" name="order_id">
                                    <option value="">Select an order</option>
                                    <?php foreach ($client_orders as $order): ?>
                                        <option value="<?php echo $order['id']; ?>" <?php echo ($preselected_order_id == $order['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($order['order_id'] . ' - ' . $order['brand'] . ' ' . $order['model'] . ' ' . $order['year']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            
                            <button type="submit" name="send_message" class="btn btn-primary w-100">
                                <i class="bi bi-send"></i> Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-chat"></i> Messages</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($messages)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-envelope-x" style="font-size: 3rem; color: #ccc;"></i>
                                <h4 class="mt-3">No Messages</h4>
                                <p class="text-muted">You don't have any messages yet. Send a message using the form.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($messages as $message): ?>
                                    <div class="list-group-item list-group-item-action message-card <?php echo $message['is_read'] ? '' : 'unread'; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                            <small><?php echo formatDate($message['created_at']); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars(substr($message['message'], 0, 100)) . (strlen($message['message']) > 100 ? '...' : ''); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small>
                                                <?php if ($message['order_id']): ?>
                                                    <span class="badge bg-info">Order: <?php echo htmlspecialchars($message['order_id']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!$message['is_read']): ?>
                                                    <span class="badge bg-warning">Unread</span>
                                                <?php endif; ?>
                                            </small>
                                            <div>
                                                <a href="message_view.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>