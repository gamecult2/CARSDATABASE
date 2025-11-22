<?php
/**
 * Enhanced Client Portal
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

// Get client ID from session (in the new structure, user_id is the client_id)
$client_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    setAlert('danger', 'Client profile not found. Please contact administrator.');
    header('Location: logout.php');
    exit;
}

// Get unread message count
$unread_count = getUnreadMessageCount($client_id);

// Get client orders with full details
$stmt = $pdo->prepare("
    SELECT o.*, car.brand, car.model, car.year, car.trim,
           (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = o.id) as total_paid,
           cont.container_id as container_code, 
           cont.container_number,
           cont.estimated_arrival_date, 
           cont.actual_arrival_date,
           cont.status as container_status
    FROM orders o
    JOIN cars car ON o.car_id = car.id
    LEFT JOIN containers cont ON o.container_id = cont.id
    WHERE o.client_id = ?
    ORDER BY o.order_date DESC
");
$stmt->execute([$client_id]);
$orders = $stmt->fetchAll();

// Get client info
$client_info = $client;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .order-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        .nav-pills .nav-link {
            color: #667eea;
        }
        .nav-pills .nav-link.active {
            background-color: #667eea;
        }
        @media (max-width: 768px) {
            .portal-header {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php displayAlert(); ?>
    
    <div class="container">
        <div class="portal-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-person-circle"></i> Client Portal</h1>
                    <p class="mb-0">Welcome, <?php echo htmlspecialchars($client_info['name']); ?>!</p>
                </div>
                <div class="text-end">
                    <a href="logout.php" class="btn btn-light">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-list"></i> Navigation</h5>
                    </div>
                    <div class="card-body">
                        <div class="nav flex-column nav-pills" role="tablist">
                            <a class="nav-link active" href="#orders" data-bs-toggle="pill" role="tab">
                                <i class="bi bi-cart-check"></i> My Orders
                            </a>
                            <a class="nav-link" href="#profile" data-bs-toggle="pill" role="tab">
                                <i class="bi bi-person"></i> My Profile
                            </a>
                            <a class="nav-link" href="#messages" data-bs-toggle="pill" role="tab">
                                <i class="bi bi-envelope"></i> Messages 
                                <?php if ($unread_count > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="orders">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-cart-check"></i> My Orders</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($orders)): ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-cart-x" style="font-size: 3rem; color: #ccc;"></i>
                                        <h4 class="mt-3">No Orders Found</h4>
                                        <p class="text-muted">You don't have any orders yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($orders as $order): ?>
                                            <?php 
                                            $modelName = $order['brand'] . ' ' . $order['model'];
                                            $remaining = $order['total_sale_price'] - $order['total_paid'];
                                            $progress = $order['total_sale_price'] > 0 ? ($order['total_paid'] / $order['total_sale_price']) * 100 : 100;
                                            $tracking_link = getContainerTrackingLink($order['container_number'] ?? '');
                                            ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="order-card card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h5 class="card-title">
                                                                    <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?>
                                                                </h5>
                                                                <p class="card-text text-muted mb-1">
                                                                    <?php echo $order['year']; ?> | <?php echo htmlspecialchars($order['trim']); ?>
                                                                </p>
                                                            </div>
                                                            <span class="badge bg-primary">ID: <?php echo htmlspecialchars($order['order_id']); ?></span>
                                                        </div>
                                                        
                                                        <p class="text-muted mb-2">
                                                            <strong>VIN:</strong> <code><?php echo htmlspecialchars($order['vin'] ?? 'Not assigned'); ?></code><br>
                                                            <strong>Order Date:</strong> <?php echo formatDate($order['order_date']); ?>
                                                        </p>
                                                        
                                                        <hr>
                                                        
                                                        <div class="mb-3">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span><strong>Total Price:</strong></span>
                                                                <span><?php echo formatCurrency($order['total_sale_price']); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span><strong>Paid:</strong></span>
                                                                <span class="text-success"><?php echo formatCurrency($order['total_paid']); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span><strong>Remaining:</strong></span>
                                                                <span class="<?php echo $remaining > 0 ? 'text-warning fw-bold' : 'text-success'; ?>">
                                                                    <?php echo formatCurrency($remaining); ?>
                                                                </span>
                                                            </div>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar <?php echo $progress >= 100 ? 'bg-success' : 'bg-warning'; ?>" 
                                                                     role="progressbar" 
                                                                     style="width: <?php echo min(100, $progress); ?>%">
                                                                    <?php echo number_format($progress, 1); ?>%
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <hr>
                                                        
                                                        <div class="mb-3">
                                                            <strong>Shipping Status:</strong><br>
                                                            <span class="badge bg-info status-badge"><?php echo $order['shipping_status']; ?></span>
                                                        </div>
                                                        
                                                        <?php if ($order['container_code']): ?>
                                                        <div class="mb-3">
                                                            <strong>Container:</strong> <?php echo htmlspecialchars($order['container_code']); ?><br>
                                                            <?php if ($order['container_number']): ?>
                                                                <small class="text-muted">Container #: <code><?php echo htmlspecialchars($order['container_number']); ?></code></small>
                                                                <?php if ($tracking_link): ?>
                                                                    <br><a href="<?php echo htmlspecialchars($tracking_link); ?>" target="_blank" class="btn btn-sm btn-info mt-1">
                                                                        <i class="bi bi-box-arrow-up-right"></i> Track Container
                                                                    </a>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <?php if ($order['estimated_arrival_date']): ?>
                                                                <br><small class="text-muted">
                                                                    Est. Arrival: <?php echo formatDate($order['estimated_arrival_date']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                            <?php if ($order['actual_arrival_date']): ?>
                                                                <br><small class="text-success">
                                                                    Arrived: <?php echo formatDate($order['actual_arrival_date']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="mt-3">
                                                            <a href="order_view.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-eye"></i> View Details
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="profile">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-person"></i> My Profile</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Name:</strong></label>
                                            <p><?php echo htmlspecialchars($client_info['name']); ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Phone:</strong></label>
                                            <p><?php echo htmlspecialchars($client_info['phone']); ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Email:</strong></label>
                                            <p><?php echo htmlspecialchars($client_info['email'] ?? 'Not provided'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Passport Number:</strong></label>
                                            <p><?php echo htmlspecialchars($client_info['passport_number']); ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Address:</strong></label>
                                            <p><?php echo htmlspecialchars($client_info['address'] ?? 'Not provided'); ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Client ID:</strong></label>
                                            <p><?php echo htmlspecialchars($client_info['client_id']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <a href="edit_profile.php" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Edit Profile
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="messages">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-envelope"></i> Messages</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $messages = getClientMessages($client_id);
                                if (empty($messages)):
                                ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-envelope-x" style="font-size: 3rem; color: #ccc;"></i>
                                        <h4 class="mt-3">No Messages</h4>
                                        <p class="text-muted">You don't have any messages yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>From</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($messages as $message): ?>
                                                    <tr class="<?php echo $message['is_read'] ? '' : 'table-warning'; ?>">
                                                        <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                                        <td>Admin</td>
                                                        <td><?php echo formatDate($message['created_at']); ?></td>
                                                        <td>
                                                            <?php if ($message['is_read']): ?>
                                                                <span class="badge bg-success">Read</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Unread</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="message_view.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <a href="messages.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Send Message
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>