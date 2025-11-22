<?php
/**
 * Order Details
 */

require_once '../config.php';
require_once '../functions.php';
require_once '../functions_media.php';

requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: orders.php');
    exit;
}

// Get order
$stmt = $pdo->prepare("
    SELECT o.*, c.name as client_name, c.phone, c.email, c.client_id as client_code,
           car.brand, car.model, car.year, car.trim, car.color,
           cont.container_id as container_code, cont.status as container_status
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    JOIN cars car ON o.car_id = car.id
    LEFT JOIN containers cont ON o.container_id = cont.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setAlert('danger', 'Order not found');
    header('Location: orders.php');
    exit;
}

// Get payments
$stmt = $pdo->prepare("
    SELECT * FROM payments WHERE order_id = ? ORDER BY payment_date DESC
");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

// Calculate totals
$total_paid = array_sum(array_column($payments, 'amount_paid'));
$remaining_balance = calculateRemainingBalance($id);

// Get shipping status
$shipping = getShippingStatus($id);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        setAlert('danger', 'Invalid CSRF token.');
        header("Location: order_details.php?id=$id");
        exit;
    }

    $new_status = sanitize($_POST['shipping_status'] ?? '');
    if ($new_status !== $order['shipping_status']) {
        $stmt = $pdo->prepare("UPDATE orders SET shipping_status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $id])) {
            // Send email notification
            $client_email = $order['email'];
            $subject = "Your Order Status has been Updated";
            $body = "Dear " . $order['client_name'] . ",\n\n";
            $body .= "The status of your order #" . $order['order_id'] . " has been updated to: " . $new_status . "\n\n";
            $body .= "You can view your order details here: " . BASE_URL . "/order_view.php?order_id=" . $id . "\n\n";
            $body .= "Thank you for your business.\n";
            send_email_notification($client_email, $subject, $body);

            setAlert('success', 'Shipping status updated and client notified.');
            header('Location: order_details.php?id=' . $id);
            exit;
        }
    } else {
        setAlert('info', 'No change in shipping status.');
        header('Location: order_details.php?id=' . $id);
        exit;
    }
}

// Handle media upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_files'])) {
    $upload_result = uploadBulkMedia($_FILES['media_files'], $id);
    if ($upload_result['total'] > 0) {
        if ($upload_result['successful'] > 0) {
            setAlert('success', "Uploaded {$upload_result['successful']} of {$upload_result['total']} file(s) successfully");
        }
        if ($upload_result['failed_count'] > 0) {
            $errors = implode('; ', array_map(function($f) { return $f['filename'] . ': ' . $f['error']; }, $upload_result['failed']));
            setAlert('warning', "Failed to upload {$upload_result['failed_count']} file(s): {$errors}");
        }
    }
    header('Location: order_details.php?id=' . $id);
    exit;
}

// Get documents
$documents = getDocuments('order', $id);

// Get media
$media = getOrderMedia($id);
$photo_count = countOrderMedia($id, 'photo');
$video_count = countOrderMedia($id, 'video');

// Orders are editable regardless of shipping status
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-cart-check"></i> Order Details: <?php echo htmlspecialchars($order['order_id']); ?></h1>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Orders
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Orders are editable regardless of shipping status -->
                <div class="row mb-4">
                    <!-- Order Information -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Order Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Order ID:</th>
                                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Order Date:</th>
                                        <td><?php echo formatDate($order['order_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Price:</th>
                                        <td><strong><?php echo formatCurrency($order['total_sale_price']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Total Paid:</th>
                                        <td class="text-success"><?php echo formatCurrency($total_paid); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Remaining Balance:</th>
                                        <td>
                                            <span class="<?php echo $remaining_balance > 0 ? 'text-warning fw-bold' : 'text-success'; ?>">
                                                <?php echo formatCurrency($remaining_balance); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Shipping Status:</th>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <select name="shipping_status" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                                    <option value="Awaiting Container" <?php echo $order['shipping_status'] === 'Awaiting Container' ? 'selected' : ''; ?>>Awaiting Container</option>
                                                    <option value="Shipped on Container" <?php echo $order['shipping_status'] === 'Shipped on Container' ? 'selected' : ''; ?>>Shipped on Container</option>
                                                    <option value="Arrived in Algeria" <?php echo $order['shipping_status'] === 'Arrived in Algeria' ? 'selected' : ''; ?>>Arrived in Algeria</option>
                                                    <option value="Customs Cleared" <?php echo $order['shipping_status'] === 'Customs Cleared' ? 'selected' : ''; ?>>Customs Cleared</option>
                                                    <option value="Ready for Pickup" <?php echo $order['shipping_status'] === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                                    <option value="Delivered" <?php echo $order['shipping_status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            
                                        </td>
                                    </tr>
                                    <?php if ($order['container_code']): ?>
                                    <tr>
                                        <th>Container:</th>
                                        <td>
                                            <a href="container_details.php?id=<?php echo $order['container_id']; ?>">
                                                <?php echo htmlspecialchars($order['container_code']); ?>
                                            </a>
                                            <span class="badge bg-info ms-2"><?php echo $order['container_status']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($order['notes']): ?>
                                    <tr>
                                        <th>Notes:</th>
                                        <td><?php echo nl2br(htmlspecialchars($order['notes'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Client Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Client Information</h5>
                            </div>
                            <div class="card-body">
                                <p><strong><?php echo htmlspecialchars($order['client_name']); ?></strong></p>
                                <p><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($order['phone']); ?></p>
                                <p><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($order['email'] ?? '-'); ?></p>
                                <a href="client_details.php?id=<?php echo $order['client_id']; ?>" class="btn btn-sm btn-info">
                                    View Client Details
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Car Information -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5>Car Information</h5>
                            </div>
                            <div class="card-body">
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
                                        <td><code><?php echo htmlspecialchars($order['vin'] ?? '-'); ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Color:</th>
                                        <td><?php echo htmlspecialchars($order['color'] ?? '-'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Documents -->
                        <div class="card">
                                <div class="card-header d-flex justify-content-between">
                                <h5>Documents</h5>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="bi bi-upload"></i> Upload
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($documents)): ?>
                                    <p class="text-muted">No documents uploaded</p>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($documents as $doc): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($doc['document_name']); ?></span>
                                            <div>
                                                <a href="../<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Media Gallery -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-images"></i> Media Gallery
                                    <span class="badge bg-primary"><?php echo count($media); ?></span>
                                    <?php if ($photo_count > 0): ?><span class="badge bg-success"><?php echo $photo_count; ?> photos</span><?php endif; ?>
                                    <?php if ($video_count > 0): ?><span class="badge bg-warning"><?php echo $video_count; ?> videos</span><?php endif; ?>
                                </h5>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadMediaModal">
                                    <i class="bi bi-cloud-upload"></i> Upload Media
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($media)): ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-image" style="font-size: 3rem; color: #ccc;"></i>
                                        <p class="text-muted mt-3">No media uploaded yet. Upload photos and videos of this vehicle order.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($media as $m): ?>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <div class="card h-100 position-relative">
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
                                                    <small class="text-muted d-block mt-1">Uploaded: <?php echo formatDateTime($m['created_at']); ?></small>
                                                </div>
                                                <div class="card-footer bg-light p-2 d-flex gap-2">
                                                    <a href="<?php echo BASE_URL; ?>/serve_media.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-info flex-grow-1" target="_blank">
                                                        <i class="bi bi-download"></i> View
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteMedia(<?php echo $m['id']; ?>)">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
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
                
                <!-- Payments -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5>Payment History</h5>
                        <a href="add_payment.php?order_id=<?php echo $id; ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-plus-circle"></i> Add Payment
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <p class="text-muted">No payments recorded</p>
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
                                            <td><?php echo formatCurrency($payment['amount_paid']); ?></td>
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
            </main>
        </div>
    </div>
    
    <!-- Upload Media Modal -->
    <div class="modal fade" id="uploadMediaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-cloud-upload"></i> Upload Media</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> <strong>Upload Photos and Videos</strong><br>
                            Supported formats: JPG, PNG, WebP, MP4, WebM, MOV, AVI, MKV<br>
                            Maximum file size: 100MB per file
                        </div>
                        <div class="mb-3">
                            <label for="media_files" class="form-label">Select Media Files</label>
                            <input type="file" class="form-control" id="media_files" name="media_files[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska" required>
                            <small class="text-muted d-block mt-2">You can select multiple files at once</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Upload Files
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="upload_document.php" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload Document</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="entity_type" value="order">
                        <input type="hidden" name="entity_id" value="<?php echo $id; ?>">
                        <div class="mb-3">
                            <label for="document" class="form-label">Select File</label>
                            <input type="file" class="form-control" id="document" name="document" required>
                            <small class="text-muted">Max size: 5MB. Allowed: PDF, JPG, PNG, DOC, DOCX</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteMedia(mediaId) {
        if (confirm('Are you sure you want to delete this media file? This action cannot be undone.')) {
            // Create form to delete media
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_media.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'media_id';
            input.value = mediaId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>

