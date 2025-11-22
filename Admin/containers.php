<?php
/**
 * Containers Management
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get all orders associated with this container
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE container_id = ?");
        $stmt->execute([$id]);
        $orders = $stmt->fetchAll();
        
        // Update shipping status for all orders in this container
        $stmt = $pdo->prepare("UPDATE orders SET shipping_status = 'Awaiting Container', container_id = NULL WHERE container_id = ?");
        $stmt->execute([$id]);
        
        // Delete container cars associations (will be automatically deleted due to CASCADE, but we do it explicitly for clarity)
        $stmt = $pdo->prepare("DELETE FROM container_cars WHERE container_id = ?");
        $stmt->execute([$id]);
        
        // Delete the container itself
        $stmt = $pdo->prepare("DELETE FROM containers WHERE id = ?");
        if ($stmt->execute([$id])) {
            $pdo->commit();
            setAlert('success', 'Container deleted successfully and associated cars status updated');
        } else {
            throw new Exception('Error deleting container');
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        setAlert('danger', 'Error deleting container: ' . $e->getMessage());
    }
    
    header('Location: containers.php');
    exit;
}

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND container_id LIKE ?";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
}

// Get containers - Improved query with better data consistency
$stmt = $pdo->prepare("
    SELECT c.*, 
           COALESCE(valid_cars.car_count, 0) as car_count,
           COALESCE(orders.order_count, 0) as order_count
    FROM containers c
    LEFT JOIN (
        SELECT cc.container_id, COUNT(*) as car_count
        FROM container_cars cc
        INNER JOIN orders o ON cc.order_id = o.id
        GROUP BY cc.container_id
    ) valid_cars ON valid_cars.container_id = c.id
    LEFT JOIN (
        SELECT container_id, COUNT(*) as order_count
        FROM orders
        WHERE container_id IS NOT NULL
        GROUP BY container_id
    ) orders ON orders.container_id = c.id
    WHERE $where
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$containers = $stmt->fetchAll();

// Generate tracking links for containers
foreach ($containers as &$container) {
    $container['tracking_link'] = getContainerTrackingLink($container['container_number'] ?? '');
}
unset($container);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Containers - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-box-seam"></i> Containers Management</h1>
                    <a href="add_container.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Container
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" placeholder="Search by container ID..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Scheduled" <?php echo $status_filter === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="En Route" <?php echo $status_filter === 'En Route' ? 'selected' : ''; ?>>En Route</option>
                                    <option value="Arrived" <?php echo $status_filter === 'Arrived' ? 'selected' : ''; ?>>Arrived</option>
                                    <option value="Unloaded" <?php echo $status_filter === 'Unloaded' ? 'selected' : ''; ?>>Unloaded</option>
                                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
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
                
                <!-- Containers Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Containers (<?php echo count($containers); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                        <tr>
                                            <th>Container ID</th>
                                            <th>Container Number</th>
                                            <th>Departure Port</th>
                                            <th>Arrival Port</th>
                                            <th>Departure Date</th>
                                            <th>Est. Arrival</th>
                                            <th>Actual Arrival</th>
                                            <th>Cars</th>
                                            <th>Shipping Cost</th>
                                            <th>Cost/Car</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($containers)): ?>
                                        <tr>
                                            <td colspan="12" class="text-center text-muted">No containers found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($containers as $container): ?>
                                        <?php 
                                            $cost_per_car = $container['car_count'] > 0 ? $container['container_shipping_cost'] / $container['car_count'] : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($container['container_id']); ?></strong></td>
                                            <td>
                                                <?php if ($container['container_number']): ?>
                                                    <code><?php echo htmlspecialchars($container['container_number']); ?></code>
                                                    <?php if ($container['tracking_link']): ?>
                                                        <a href="<?php echo htmlspecialchars($container['tracking_link']); ?>" target="_blank" class="btn btn-sm btn-info ms-1" title="Track Container">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($container['departure_port']); ?></td>
                                            <td><?php echo htmlspecialchars($container['arrival_port']); ?></td>
                                            <td><?php echo formatDate($container['departure_date']); ?></td>
                                            <td><?php echo formatDate($container['estimated_arrival_date']); ?></td>
                                            <td><?php echo formatDate($container['actual_arrival_date']); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $container['car_count']; ?>/<?php echo $container['max_capacity']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatCurrencyUSD($container['container_shipping_cost']); ?></td>
                                            <td>
                                                <?php if ($cost_per_car > 0): ?>
                                                    <?php echo formatCurrencyUSD($cost_per_car); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo strtolower(str_replace(' ', '-', $container['status'])); ?>">
                                                    <?php echo $container['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="container_details.php?id=<?php echo $container['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit_container.php?id=<?php echo $container['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?delete=1&id=<?php echo $container['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure?')">
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