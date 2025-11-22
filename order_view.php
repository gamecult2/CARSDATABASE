<?php
/**
 * Client Order View
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'functions_media.php';

requireLogin();

if ($_SESSION['role'] !== 'client') {
    header('Location: Admin/index.php');
    exit;
}

// Accept both 'id' and 'order_id' parameters for compatibility
$id = (int)($_GET['id'] ?? $_GET['order_id'] ?? 0);

if (!$id) {
    header('Location: client_portal.php');
    exit;
}

// Get client ID - directly from session for clients
$client_id = $_SESSION['user_id'];

// Get order (verify it belongs to client)
$stmt = $pdo->prepare("
    SELECT o.*, car.brand, car.model, car.year, car.trim, car.color, car.description,
           cont.container_id as container_code, cont.container_number, cont.status as container_status,
           cont.departure_date, cont.estimated_arrival_date, cont.actual_arrival_date
    FROM orders o
    JOIN cars car ON o.car_id = car.id
    LEFT JOIN containers cont ON o.container_id = cont.id
    WHERE o.id = ? AND o.client_id = ?
");
$stmt->execute([$id, $client_id]);
$order = $stmt->fetch();

// Get tracking link
$tracking_link = $order['container_number'] ? getContainerTrackingLink($order['container_number']) : null;

if (!$order) {
    setAlert('danger', 'Order not found');
    header('Location: client_portal.php');
    exit;
}

// Get payments
$stmt = $pdo->prepare("
    SELECT * FROM payments WHERE order_id = ? ORDER BY payment_date DESC
");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

$total_paid = array_sum(array_column($payments, 'amount_paid'));
$remaining = calculateRemainingBalance($id);

// Get media
$media = getOrderMedia($id);
$photo_count = countOrderMedia($id, 'photo');
$video_count = countOrderMedia($id, 'video');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
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
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-cart-check"></i> Order: <?php echo htmlspecialchars($order['order_id']); ?></h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Car Information</h5>
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Brand:</th>
                                <td><?php echo htmlspecialchars($order['brand']); ?></td>
                            </tr>
                                    <tr>
                                        <th>Model:</th>
                                        <td><?php echo htmlspecialchars($order['model']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Year:</th>
                                        <td><?php echo $order['year']; ?></td>
                                    </tr>
                                    <?php if ($order['trim']): ?>
                                    <tr>
                                        <th>Trim:</th>
                                        <td><?php echo htmlspecialchars($order['trim']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>VIN:</th>
                                        <td><code><?php echo htmlspecialchars($order['vin'] ?? 'Not assigned'); ?></code></td>
                                    </tr>
                            <tr>
                                <th>Color:</th>
                                <td><?php echo htmlspecialchars($order['color'] ?? '-'); ?></td>
                            </tr>
                            <?php if ($order['description']): ?>
                            <tr>
                                <th>Description:</th>
                                <td><?php echo nl2br(htmlspecialchars($order['description'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Order & Payment Information</h5>
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Order Date:</th>
                                <td><?php echo formatDate($order['order_date']); ?></td>
                            </tr>
                            <tr>
                                <th>Total Price:</th>
                                <td><strong><?php echo formatCurrency($order['total_sale_price']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Total Paid:</th>
                                <td class="text-success"><strong><?php echo formatCurrency($total_paid); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Remaining:</th>
                                <td>
                                    <span class="<?php echo $remaining > 0 ? 'text-warning fw-bold' : 'text-success'; ?>">
                                        <strong><?php echo formatCurrency($remaining); ?></strong>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Shipping Status:</th>
                                <td>
                                    <span class="badge bg-info fs-6"><?php echo $order['shipping_status']; ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($order['container_code']): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5><i class="bi bi-box-seam"></i> Shipping Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="30%">Container:</th>
                        <td><strong><?php echo htmlspecialchars($order['container_code']); ?></strong></td>
                    </tr>
                    <?php if ($order['container_number']): ?>
                    <tr>
                        <th>Container Number:</th>
                        <td>
                            <code><?php echo htmlspecialchars($order['container_number']); ?></code>
                            <?php if ($tracking_link): ?>
                                <a href="<?php echo htmlspecialchars($tracking_link); ?>" target="_blank" class="btn btn-sm btn-info ms-2">
                                    <i class="bi bi-box-arrow-up-right"></i> Track Container
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Container Status:</th>
                        <td><span class="badge bg-info"><?php echo $order['container_status']; ?></span></td>
                    </tr>
                    <?php if ($order['departure_date']): ?>
                    <tr>
                        <th>Departure Date:</th>
                        <td><?php echo formatDate($order['departure_date']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($order['estimated_arrival_date']): ?>
                    <tr>
                        <th>Estimated Arrival:</th>
                        <td><?php echo formatDate($order['estimated_arrival_date']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($order['actual_arrival_date']): ?>
                    <tr>
                        <th>Actual Arrival:</th>
                        <td class="text-success"><strong><?php echo formatDate($order['actual_arrival_date']); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Media Gallery -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-images"></i> Vehicle Media
                    <span class="badge bg-primary"><?php echo count($media); ?></span>
                    <?php if ($photo_count > 0): ?><span class="badge bg-success"><?php echo $photo_count; ?> photos</span><?php endif; ?>
                    <?php if ($video_count > 0): ?><span class="badge bg-warning"><?php echo $video_count; ?> videos</span><?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($media)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-image" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-3">No media available yet. Check back soon for photos and videos of your vehicle.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($media as $m): ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card h-100">
                                <?php if ($m['media_type'] === 'photo'): ?>
                                    <img src="<?php echo BASE_URL; ?>/serve_media.php?id=<?php echo $m['id']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($m['file_name']); ?>" style="height: 200px; object-fit: cover; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#mediaViewModal<?php echo $m['id']; ?>">
                                <?php else: ?>
                                    <div class="bg-dark d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <div class="text-center text-white">
                                            <i class="bi bi-play-circle" style="font-size: 2rem;"></i>
                                            <p class="mt-2 mb-0">Video</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body p-2">
                                    <small class="text-muted d-block text-truncate"><?php echo htmlspecialchars($m['file_name']); ?></small>
                                    <small class="text-muted d-block"><?php echo formatBytes($m['file_size']); ?></small>
                                    <?php if ($m['description']): ?>
                                        <small class="text-dark mt-2 d-block"><?php echo htmlspecialchars($m['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-light p-2">
                                    <a href="<?php echo BASE_URL; ?>/serve_media.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-info w-100" target="_blank">
                                        <i class="bi bi-download"></i> View / Download
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contact Support -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5><i class="bi bi-chat-dots"></i> Need Help?</h5>
            </div>
            <div class="card-body">
                <p>Have questions about this order? Send a message to our support team.</p>
                <a href="messages.php?order_id=<?php echo $id; ?>" class="btn btn-primary">
                    <i class="bi bi-envelope"></i> Contact Support
                </a>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="bi bi-cash-stack"></i> Payment History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <p class="text-muted">No payments recorded yet</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td class="text-success"><strong><?php echo formatCurrency($payment['amount_paid']); ?></strong></td>
                                    <td><?php echo $payment['payment_method']; ?></td>
                                    <td><span class="badge bg-info"><?php echo $payment['payment_type']; ?></span></td>
                                    <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="2">Total Paid:</th>
                                    <th class="text-success"><?php echo formatCurrency($total_paid); ?></th>
                                    <th colspan="3"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

