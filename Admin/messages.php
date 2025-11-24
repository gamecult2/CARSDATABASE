<?php
/**
 * Admin Messages Management
 */

require_once '../config.php';
require_once '../functions.php';
require_once '../functions_media.php';

requireLogin();
requireRole('admin');

// Handle send reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $message_id = (int)($_POST['message_id'] ?? 0);
    $client_id = (int)($_POST['client_id'] ?? 0);
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    if (empty($message)) {
        setAlert('danger', 'Message cannot be empty');
    } else {
        $new_message_id = sendMessage($client_id, $order_id, $subject ?: 'Re: ' . ($_POST['original_subject'] ?? ''), $message, 'admin', $_SESSION['user_id']);
        if ($new_message_id) {
            // Handle file uploads
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                uploadMultipleFiles($_FILES['attachments'], 'message', $new_message_id);
            }
            
            // Mark original message as read
            if ($message_id) {
                markMessageAsRead($message_id, $_SESSION['user_id']);
            }
            
            setAlert('success', 'Reply sent successfully');
            header('Location: messages.php');
            exit;
        } else {
            setAlert('danger', 'Error sending reply');
        }
    }
}

// Handle send new message (admin initiating conversation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_new'])) {
    $client_id = (int)($_POST['client_id'] ?? 0);
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (empty($client_id) || empty($message)) {
        setAlert('danger', 'Client and message are required');
    } else {
        $new_message_id = sendMessage($client_id, $order_id, $subject, $message, 'admin', $_SESSION['user_id']);
        if ($new_message_id) {
            // Handle file uploads
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                uploadMultipleFiles($_FILES['attachments'], 'message', $new_message_id);
            }
            
            setAlert('success', 'Message sent successfully');
            header('Location: messages.php');
            exit;
        } else {
            setAlert('danger', 'Error sending message');
        }
    }
}

// Get all clients for dropdown
$stmt = $pdo->query("SELECT id, name, client_id FROM clients WHERE is_active = 1 ORDER BY name");
$clients = $stmt->fetchAll();

// Get messages (with pagination)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->query("
    SELECT m.*, c.name as client_name, c.client_id, o.order_id, u_sender.full_name as sender_name
    FROM messages m
    JOIN clients c ON m.client_id = c.id
    LEFT JOIN orders o ON m.order_id = o.id
    LEFT JOIN admin_users u_sender ON m.sender_id = u_sender.id
    WHERE m.sender_type = 'client' OR (m.sender_type = 'admin' AND m.sender_id = " . $_SESSION['user_id'] . ")
    ORDER BY m.created_at DESC
    LIMIT $limit OFFSET $offset
");
$messages = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->query("SELECT COUNT(*) FROM messages m WHERE m.sender_type = 'client' OR (m.sender_type = 'admin' AND m.sender_id = " . $_SESSION['user_id'] . ")");
$total_messages = $stmt->fetchColumn();
$total_pages = ceil($total_messages / $limit);

// Get unread count
$unread_count = getUnreadAdminMessageCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="custom_style.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php require_once 'includes/header.php'; ?>
        
        <div class="container-fluid mt-4">
            <div class="row mb-3">
                <div class="col-md-6">
                    <h2><i class="bi bi-envelope"></i> Messages</h2>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                        <i class="bi bi-plus-circle"></i> New Message
                    </button>
                </div>
            </div>
            
            <?php displayAlert(); ?>
            
            <!-- Messages List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Message History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <p class="text-center text-muted">No messages found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Order ID</th>
                                        <th>Subject</th>
                                        <th>Sender</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $msg): ?>
                                        <tr class="<?php echo !$msg['is_read'] && $msg['recipient_type'] == 'admin' ? 'table-warning' : ''; ?>">
                                            <td><?php echo htmlspecialchars($msg['client_name']); ?></td>
                                            <td><?php echo $msg['order_id'] ? htmlspecialchars($msg['order_id']) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                            <td><?php echo htmlspecialchars($msg['sender_name'] ?? ($msg['sender_type'] == 'admin' ? 'Admin' : 'Client')); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></td>
                                            <td>
                                                <?php if ($msg['is_read']): ?>
                                                    <span class="badge bg-success">Read</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Unread</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="message_view.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Messages pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1" aria-labelledby="newMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newMessageModalLabel">New Message</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="send_new" value="1">
                        
                        <div class="mb-3">
                            <label for="client_id" class="form-label">Client *</label>
                            <select class="form-select" id="client_id" name="client_id" required>
                                <option value="">Select a client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
                                        <?php echo htmlspecialchars($client['name'] . ' (' . $client['client_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="order_id" class="form-label">Order (Optional)</label>
                            <select class="form-select" id="order_id" name="order_id">
                                <option value="">Select an order</option>
                                <!-- Will be populated via AJAX -->
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" placeholder="Message subject">
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="5" placeholder="Type your message here..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachments" class="form-label">Attachments</label>
                            <input class="form-control" type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            <div class="form-text">You can upload multiple files. Supported formats: JPG, PNG, PDF, DOC, DOCX.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        // Populate orders based on selected client
        document.getElementById('client_id').addEventListener('change', function() {
            const clientId = this.value;
            const orderSelect = document.getElementById('order_id');
            
            // Clear existing options
            orderSelect.innerHTML = '<option value="">Select an order</option>';
            
            if (clientId) {
                // In a real implementation, you would make an AJAX call to get orders for this client
                // For now, we'll leave it empty
            }
        });
    </script>
</body>
</html>