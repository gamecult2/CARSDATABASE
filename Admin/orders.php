<?php
/**
 * Orders Management
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    if ($stmt->execute([$id])) {
        setAlert('success', 'Order deleted successfully');
    } else {
        setAlert('danger', 'Error deleting order');
    }
    header('Location: orders.php');
    exit;
}

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (o.order_id LIKE ? OR c.name LIKE ? OR car.vin LIKE ? OR car.brand LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

if (!empty($status_filter)) {
    $where .= " AND o.shipping_status = ?";
    $params[] = $status_filter;
}

// Get orders
$stmt = $pdo->prepare("
    SELECT o.*, c.name as client_name, c.phone, car.brand, car.model, car.year, car.trim,
           (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = o.id) as total_paid,
           cont.container_id as container_code
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    JOIN cars car ON o.car_id = car.id
    LEFT JOIN containers cont ON o.container_id = cont.id
    WHERE $where
    ORDER BY o.order_date DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="custom_style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-cart-check"></i> Orders Management</h1>
                    <a href="add_client_and_order.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Client & Create Order
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" placeholder="Search by order ID, client name, or brand..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Awaiting Container" <?php echo $status_filter === 'Awaiting Container' ? 'selected' : ''; ?>>Awaiting Container</option>
                                    <option value="Shipped on Container" <?php echo $status_filter === 'Shipped on Container' ? 'selected' : ''; ?>>Shipped on Container</option>
                                    <option value="Arrived in Algeria" <?php echo $status_filter === 'Arrived in Algeria' ? 'selected' : ''; ?>>Arrived in Algeria</option>
                                    <option value="Customs Cleared" <?php echo $status_filter === 'Customs Cleared' ? 'selected' : ''; ?>>Customs Cleared</option>
                                    <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                    <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Orders Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Orders (<?php echo count($orders); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Client</th>
                                        <th>Car Model</th>
                                        <th>VIN</th>
                                        <th>Order Date</th>
                                        <th>Total Price</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Container</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center text-muted">No orders found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                        <?php $balance = $order['total_sale_price'] - $order['total_paid']; ?>
                                        <?php $modelName = $order['brand'] . ' ' . $order['model'] . ' ' . $order['year'] . ($order['trim'] ? ' ' . $order['trim'] : ''); ?>
                                        <tr>
                                            <td><a href="order_details.php?id=<?php echo $order['id']; ?>"><?php echo htmlspecialchars($order['order_id']); ?></a></td>
                                            <td><?php echo htmlspecialchars($order['client_name']); ?></td>
                                            <td><?php echo htmlspecialchars($modelName); ?></td>
                                            <td><code><?php echo htmlspecialchars($order['vin'] ?? '-'); ?></code></td>
                                            <td><?php echo formatDate($order['order_date']); ?></td>
                                            <td><?php echo formatCurrency($order['total_sale_price']); ?></td>
                                            <td><?php echo formatCurrency($order['total_paid']); ?></td>
                                            <td>
                                                <span class="<?php echo $balance > 0 ? 'text-warning fw-bold' : 'text-success'; ?>">
                                                    <?php echo formatCurrency($balance); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                    $statusMap = [
                                                        'Awaiting Container' => 'bg-warning text-dark',
                                                        'Shipped on Container' => 'bg-info',
                                                        'Arrived in Algeria' => 'bg-success',
                                                        'Customs Cleared' => 'bg-secondary',
                                                        'Ready for Pickup' => 'bg-primary',
                                                        'Delivered' => 'bg-success'
                                                    ];
                                                    $badgeClass = $statusMap[$order['shipping_status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <?php echo $order['shipping_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($order['container_code']): ?>
                                                    <a href="container_details.php?id=<?php echo $order['container_id']; ?>">
                                                        <?php echo htmlspecialchars($order['container_code']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="?delete=1&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

