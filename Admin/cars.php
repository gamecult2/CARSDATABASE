<?php
/**
 * Cars Inventory Management
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // First check if car has any orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE car_id = ?");
        $stmt->execute([$id]);
        $order_count = $stmt->fetchColumn();
        
        if ($order_count > 0) {
            // Car has orders, cannot delete
            setAlert('danger', 'Cannot delete car because it has ' . $order_count . ' order(s). Please delete the orders first.');
        } else {
            // Car has no orders, safe to delete
            $stmt = $pdo->prepare("DELETE FROM cars WHERE id = ?");
            if ($stmt->execute([$id])) {
                setAlert('success', 'Car deleted successfully');
            } else {
                setAlert('danger', 'Error deleting car');
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        setAlert('danger', 'Error deleting car: ' . $e->getMessage());
    }
    
    header('Location: cars.php');
    exit;
}

// Handle multiple car deletion
if (isset($_POST['delete_selected_cars']) && isset($_POST['selected_cars'])) {
    $selected_cars = $_POST['selected_cars'];
    $deleted_count = 0;
    $error_count = 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($selected_cars as $car_id) {
            $id = (int)$car_id;
            
            // Check if car has any orders
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE car_id = ?");
            $stmt->execute([$id]);
            $order_count = $stmt->fetchColumn();
            
            if ($order_count > 0) {
                // Cannot delete car with orders
                $error_count++;
            } else {
                // Delete car
                $stmt = $pdo->prepare("DELETE FROM cars WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $deleted_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        $pdo->commit();
        
        if ($deleted_count > 0) {
            setAlert('success', "$deleted_count car(s) deleted successfully.");
        }
        
        if ($error_count > 0) {
            setAlert('warning', "$error_count car(s) could not be deleted because they have orders.");
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        setAlert('danger', 'Error deleting cars: ' . $e->getMessage());
    }
    
    header('Location: cars.php');
    exit;
}

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$brand_filter = $_GET['brand'] ?? '';

$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (brand LIKE ? OR model LIKE ? OR trim LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($status_filter)) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($brand_filter)) {
    $where .= " AND brand = ?";
    $params[] = $brand_filter;
}

// Pagination for cars
$cars_per_page = 30;
$cars_page = max(1, (int)($_GET['cars_page'] ?? 1));
$cars_offset = ($cars_page - 1) * $cars_per_page;

// Get total cars count for the current filter
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cars c WHERE $where");
$countStmt->execute($params);
$cars_total = (int)$countStmt->fetchColumn();
$cars_total_pages = (int)ceil($cars_total / $cars_per_page);

// Get cars with pagination
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM orders WHERE car_id = c.id) as order_count
    FROM cars c
    WHERE $where
    ORDER BY c.brand, c.model, c.year DESC
    LIMIT ? OFFSET ?
");
$execParams = array_merge($params, [$cars_per_page, $cars_offset]);
$stmt->execute($execParams);
$cars = $stmt->fetchAll();

// Get distinct brands for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT brand FROM cars ORDER BY brand");
$brands = $stmt->fetchAll();

// Get distinct statuses for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT status FROM cars ORDER BY status");
$statuses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cars Inventory - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="custom_style.css">
    <style>
        /* Ensure cars table shows at least 30 rows without scrollbar */
        .card-body {
            overflow: visible;
        }
        
        .table-responsive {
            max-height: none;
            overflow-y: visible;
        }
        
        /* Adjust table row height for better visibility */
        .table th, .table td {
            padding: 0.75rem;
        }
        
        /* Checkbox styling */
        .select-all-checkbox, .select-checkbox {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-car-front"></i> Cars Inventory</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_car.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add New Car Model
                        </a>
                    </div>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="bi bi-funnel"></i> Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Brand, model, or trim">
                            </div>
                            <div class="col-md-3">
                                <label for="brand" class="form-label">Brand</label>
                                <select class="form-select" id="brand" name="brand">
                                    <option value="">All Brands</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo htmlspecialchars($brand['brand']); ?>" <?php echo ($brand_filter === $brand['brand']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($brand['brand']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status['status']); ?>" <?php echo ($status_filter === $status['status']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status['status']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
                                    <a href="cars.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <form method="POST" id="bulk-car-actions">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <button type="submit" name="delete_selected_cars" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete the selected cars? Cars with orders cannot be deleted.')">
                                <i class="bi bi-trash"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                
                <!-- Cars Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Car Models (<?php echo $cars_total; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cars)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-car-front" style="font-size: 3rem;"></i>
                                <h4>No cars found</h4>
                                <p>Try adjusting your search or filter criteria</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>
                                                <input type="checkbox" class="select-all-checkbox" onclick="toggleAllCars(this)">
                                            </th>
                                            <th>Brand</th>
                                            <th>Model</th>
                                            <th>Year</th>
                                            <th>Trim</th>
                                            <th>Purchase Price</th>
                                            <th>Sale Price</th>
                                            <th>Status</th>
                                            <th>Orders</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cars as $car): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="select-checkbox" name="selected_cars[]" value="<?php echo $car['id']; ?>">
                                                </td>
                                                <td><?php echo htmlspecialchars($car['brand']); ?></td>
                                                <td><?php echo htmlspecialchars($car['model']); ?></td>
                                                <td><?php echo htmlspecialchars($car['year']); ?></td>
                                                <td><?php echo htmlspecialchars($car['trim'] ?? 'N/A'); ?></td>
                                                <td>$<?php echo number_format($car['purchase_price_usd'], 2); ?></td>
                                                <td>$<?php echo number_format($car['sale_price_usd'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $car['status'] === 'Available' ? 'success' : 
                                                            ($car['status'] === 'Sold' ? 'danger' : 
                                                            ($car['status'] === 'Reserved' ? 'warning' : 'secondary')); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($car['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $car['order_count']; ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="edit_car.php?id=<?php echo $car['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="car_details.php?id=<?php echo $car['id']; ?>" class="btn btn-outline-info" title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($car['order_count'] == 0): ?>
                                                        <a href="cars.php?delete=<?php echo $car['id']; ?>" 
                                                           class="btn btn-outline-danger" 
                                                           title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this car model?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                        <?php else: ?>
                                                        <button class="btn btn-outline-secondary" title="Cannot delete - car has orders" disabled>
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php
                                $cars_start = $cars_total ? $cars_offset + 1 : 0;
                                $cars_end = $cars_total ? ($cars_offset + count($cars)) : 0;
                            ?>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted">Showing <?php echo $cars_start; ?>–<?php echo $cars_end; ?> of <?php echo $cars_total; ?> cars</small>
                                <?php if ($cars_total_pages > 1): ?>
                                <nav aria-label="Cars pagination">
                                    <ul class="pagination mb-0">
                                        <?php
                                            $baseParams = $_GET;
                                            for ($p = 1; $p <= $cars_total_pages; $p++):
                                                $baseParams['cars_page'] = $p;
                                                $qs = http_build_query($baseParams);
                                        ?>
                                        <li class="page-item <?php echo $p == $cars_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo $qs; ?>"><?php echo $p; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAllCars(source) {
            const checkboxes = document.querySelectorAll('.select-checkbox[name="selected_cars[]"]');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</body>
</html>