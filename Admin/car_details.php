<?php
/**
 * Car Details
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: cars.php');
    exit;
}

// Get car
$stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->execute([$id]);
$car = $stmt->fetch();

if (!$car) {
    setAlert('danger', 'Car not found');
    header('Location: cars.php');
    exit;
}

// Get orders for this car model
$stmt = $pdo->prepare("
    SELECT o.*, c.name as client_name, c.phone
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    WHERE o.car_id = ?
    ORDER BY o.order_date DESC
");
$stmt->execute([$id]);
$orders = $stmt->fetchAll();

// Get documents
$documents = getDocuments('car', $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Details - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-car-front"></i> Car Details</h1>
                    <div>
                        <a href="edit_car.php?id=<?php echo $id; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="cars.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <?php displayAlert(); ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Car Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Brand:</th>
                                        <td><?php echo htmlspecialchars($car['brand']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Model:</th>
                                        <td><?php echo htmlspecialchars($car['model']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Year:</th>
                                        <td><?php echo $car['year']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Color:</th>
                                        <td><?php echo htmlspecialchars($car['color'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="badge status-<?php echo strtolower(str_replace(' ', '-', $car['status'])); ?>">
                                                <?php echo $car['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Trim:</th>
                                        <td><?php echo htmlspecialchars($car['trim'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Purchase Price:</th>
                                        <td><?php echo formatCurrency($car['purchase_price_usd']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Sale Price:</th>
                                        <td><strong><?php echo formatCurrency($car['sale_price_usd']); ?></strong></td>
                                    </tr>
                                    <?php if ($car['description']): ?>
                                    <tr>
                                        <th>Description:</th>
                                        <td><?php echo nl2br(htmlspecialchars($car['description'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
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
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Orders -->
                <?php if (!empty($orders)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5>Orders for this Car Model (<?php echo count($orders); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Client</th>
                                        <th>Order Date</th>
                                        <th>Total Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><a href="order_details.php?id=<?php echo $order['id']; ?>"><?php echo htmlspecialchars($order['order_id']); ?></a></td>
                                        <td><?php echo htmlspecialchars($order['client_name']); ?></td>
                                        <td><?php echo formatDate($order['order_date']); ?></td>
                                        <td><?php echo formatCurrency($order['total_sale_price']); ?></td>
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
                    </div>
                </div>
                <?php endif; ?>
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
                        <input type="hidden" name="entity_type" value="car">
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

