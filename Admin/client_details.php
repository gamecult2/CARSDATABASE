<?php
/**
 * Client Details
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: clients.php');
    exit;
}

// Get client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    setAlert('danger', 'Client not found');
    header('Location: clients.php');
    exit;
}

// Get client orders
$stmt = $pdo->prepare("
    SELECT o.*, car.brand, car.model, car.year, car.trim,
           (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = o.id) as total_paid
    FROM orders o
    JOIN cars car ON o.car_id = car.id
    WHERE o.client_id = ?
    ORDER BY o.order_date DESC
");
$stmt->execute([$id]);
$orders = $stmt->fetchAll();

// Get documents
$documents = getDocuments('client', $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Details - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-person"></i> Client Details</h1>
                    <div>
                        <a href="edit_client.php?id=<?php echo $id; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="messages.php?client_id=<?php echo $id; ?>" class="btn btn-success">
                            <i class="bi bi-envelope"></i> Send Message
                        </a>
                        <a href="clients.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Client Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Client Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Client ID:</th>
                                        <td><?php echo htmlspecialchars($client['client_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Name:</th>
                                        <td><?php echo htmlspecialchars($client['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($client['email'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Passport:</th>
                                        <td><?php echo htmlspecialchars($client['passport_number'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Address:</th>
                                        <td><?php echo htmlspecialchars($client['address'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Registered:</th>
                                        <td><?php echo formatDateTime($client['created_at']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents -->
                    <div class="col-md-6">
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
                                                <a href="?delete_doc=<?php echo $doc['id']; ?>&id=<?php echo $id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this document?')">
                                                    <i class="bi bi-trash"></i>
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
                
                <!-- Orders History -->
                <div class="card">
                    <div class="card-header">
                        <h5>Order History (<?php echo count($orders); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <p class="text-muted">No orders found</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Car</th>
                                            <th>VIN</th>
                                            <th>Order Date</th>
                                            <th>Total Price</th>
                                            <th>Paid</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                        <?php $balance = $order['total_sale_price'] - $order['total_paid']; ?>
                                        <?php $modelName = $order['brand'] . ' ' . $order['model'] . ' ' . $order['year'] . ($order['trim'] ? ' ' . $order['trim'] : ''); ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                            <td><?php echo htmlspecialchars($modelName); ?></td>
                                            <td><code><?php echo htmlspecialchars($order['vin'] ?? '-'); ?></code></td>
                                            <td><?php echo formatDate($order['order_date']); ?></td>
                                            <td><?php echo formatCurrency($order['total_sale_price']); ?></td>
                                            <td><?php echo formatCurrency($order['total_paid']); ?></td>
                                            <td>
                                                <span class="<?php echo $balance > 0 ? 'text-warning' : 'text-success'; ?>">
                                                    <?php echo formatCurrency($balance); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $order['shipping_status']; ?></span></td>
                                            <td>
                                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
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
                        <input type="hidden" name="entity_type" value="client">
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
</body>
</html>

