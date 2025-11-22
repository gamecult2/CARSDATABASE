<?php
/**
 * Container Details and Car Allocation
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: containers.php');
    exit;
}

// Get container
$stmt = $pdo->prepare("SELECT * FROM containers WHERE id = ?");
$stmt->execute([$id]);
$container = $stmt->fetch();

if (!$container) {
    setAlert('danger', 'Container not found');
    header('Location: containers.php');
    exit;
}

// Get capacity info (refresh after any changes)
$capacity = getContainerCapacity($id);

// Get cars in container - Fixed query to properly retrieve all cars
$stmt = $pdo->prepare("
    SELECT cc.*, o.vin, car.brand, car.model, car.year, car.trim, o.order_id, c.name as client_name, o.id as order_db_id
    FROM container_cars cc
    JOIN cars car ON cc.car_id = car.id
    JOIN orders o ON cc.order_id = o.id
    JOIN clients c ON o.client_id = c.id
    WHERE cc.container_id = ?
    ORDER BY cc.loaded_at DESC
");
$stmt->execute([$id]);
$container_cars = $stmt->fetchAll();

// Get available cars (not in any container and have orders)
$stmt = $pdo->prepare("
    SELECT DISTINCT car.id, o.vin, car.brand, car.model, car.year, car.trim, o.id as order_id, o.order_id as order_code, c.name as client_name
    FROM cars car
    JOIN orders o ON car.id = o.car_id
    JOIN clients c ON o.client_id = c.id
    WHERE o.id NOT IN (
        SELECT cc.order_id
        FROM container_cars cc 
        WHERE cc.order_id IS NOT NULL
    )
    AND o.shipping_status = 'Awaiting Container'
    AND o.container_id IS NULL
    ORDER BY car.brand, car.model
");
$available_cars = $stmt->fetchAll();

// Get tracking link
$tracking_link = getContainerTrackingLink($container['container_number'] ?? '');

// Handle car allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_car'])) {
    $car_id = (int)($_POST['car_id'] ?? 0);
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    
    if (!$car_id) {
        setAlert('danger', 'Please select a car');
    } elseif ($capacity['used_capacity'] >= $capacity['max_capacity']) {
        setAlert('danger', 'Container is at maximum capacity (4 cars)');
    } else {
        // Verify that the order_id is associated with the car_id
        if ($order_id) {
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND car_id = ?");
            $stmt->execute([$order_id, $car_id]);
            if (!$stmt->fetch()) {
                setAlert('danger', 'Invalid car-order combination');
                $order_id = null; // Reset order_id if it doesn't match
            }
        }
        
        // Check if car is already in this specific container
        $stmt = $pdo->prepare("SELECT id FROM container_cars WHERE container_id = ? AND car_id = ?");
        $stmt->execute([$id, $car_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            setAlert('danger', 'This car is already allocated to this container');
        } else {
            // Check if car is already in another container
            $stmt = $pdo->prepare("SELECT container_id FROM container_cars WHERE car_id = ? AND container_id != ?");
            $stmt->execute([$car_id, $id]);
            $otherContainer = $stmt->fetch();
            
            if ($otherContainer) {
                setAlert('danger', 'Car is already allocated to another container');
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Allocate car to container
                    $stmt = $pdo->prepare("
                        INSERT INTO container_cars (container_id, car_id, order_id)
                        VALUES (?, ?, ?)
                    ");
                    
                    if (!$stmt->execute([$id, $car_id, $order_id])) {
                        throw new Exception('Failed to allocate car to container');
                    }
                    
                    // Update order container_id and shipping status
                    if ($order_id) {
                        $stmt = $pdo->prepare("UPDATE orders SET container_id = ?, shipping_status = 'Shipped on Container' WHERE id = ?");
                        if (!$stmt->execute([$id, $order_id])) {
                            throw new Exception('Failed to update order status');
                        }
                    }
                    
                    $pdo->commit();
                    setAlert('success', 'Car allocated to container successfully');
                    header('Location: container_details.php?id=' . $id);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollback();
                    setAlert('danger', 'Error allocating car: ' . $e->getMessage());
                }
            }
        }
    }
}

// Handle remove car from container
if (isset($_GET['remove_car']) && isset($_GET['car_id'])) {
    $car_id = (int)$_GET['car_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete from container_cars
        $stmt = $pdo->prepare("DELETE FROM container_cars WHERE container_id = ? AND car_id = ?");
        $stmt->execute([$id, $car_id]);
        
        // Reset container_id and shipping_status for orders linked to this car/container
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET container_id = NULL, shipping_status = 'Awaiting Container' 
            WHERE container_id = ? AND car_id = ?
        ");
        $stmt->execute([$id, $car_id]);
        
        $pdo->commit();
        
        setAlert('success', 'Car removed from container');
    } catch (Exception $e) {
        $pdo->rollback();
        setAlert('danger', 'Error removing car from container: ' . $e->getMessage());
    }
    
    header('Location: container_details.php?id=' . $id);
    exit;
}

// Check if there are any cars in containers (to determine if we can add cars)
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM container_cars cc
    JOIN orders o ON cc.order_id = o.id
");
$stmt->execute();
$has_cars_in_containers = $stmt->fetchColumn() > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Container Details - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-box-seam"></i> Container Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="containers.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Containers
                        </a>
                    </div>
                </div>

                <?php displayAlert(); ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Container Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th>Container ID:</th>
                                        <td><?php echo htmlspecialchars($container['container_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Container Number:</th>
                                        <td><?php echo htmlspecialchars($container['container_number'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Departure Port:</th>
                                        <td><?php echo htmlspecialchars($container['departure_port']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Arrival Port:</th>
                                        <td><?php echo htmlspecialchars($container['arrival_port']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusClass($container['status']); ?>">
                                                <?php echo htmlspecialchars($container['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Capacity:</th>
                                        <td><?php echo $capacity['used_capacity']; ?>/<?php echo $capacity['max_capacity']; ?> cars</td>
                                    </tr>
                                    <tr>
                                        <th>Shipping Cost:</th>
                                        <td><?php echo formatCurrency($container['container_shipping_cost']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Cost per Car:</th>
                                        <td><?php echo formatCurrency(calculateShippingCostPerCar($id)); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($tracking_link): ?>
                        <div class="mt-3">
                            <a href="<?php echo $tracking_link; ?>" target="_blank" class="btn btn-info">
                                <i class="bi bi-truck"></i> Track Container
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Cars in Container</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($container_cars)): ?>
                            <p class="text-muted">No cars allocated to this container yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Vehicle Identification Number (VIN)</th>
                                            <th>Car Details</th>
                                            <th>Order ID</th>
                                            <th>Client</th>
                                            <th>Loaded At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($container_cars as $car): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($car['vin'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ')'); ?></td>
                                                <td><?php echo htmlspecialchars($car['order_id']); ?></td>
                                                <td><?php echo htmlspecialchars($car['client_name']); ?></td>
                                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($car['loaded_at']))); ?></td>
                                                <td>
                                                    <a href="?id=<?php echo $id; ?>&remove_car=1&car_id=<?php echo $car['car_id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Are you sure you want to remove this car from the container?')">
                                                        <i class="bi bi-trash"></i> Remove
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

                <?php if ($capacity['used_capacity'] < $capacity['max_capacity']): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Allocate Cars to Container</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($available_cars)): ?>
                            <p class="text-muted">
                                <?php if ($has_cars_in_containers): ?>
                                    No available cars. All cars are already allocated to containers.
                                <?php else: ?>
                                    No cars available for container allocation. Cars must have associated orders with "Awaiting Container" status.
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="car_id" class="form-label">Select Car</label>
                                    <select class="form-select" id="car_id" name="car_id" required>
                                        <option value="">Choose a car...</option>
                                        <?php foreach ($available_cars as $car): ?>
                                            <option value="<?php echo $car['id']; ?>" 
                                                    data-order="<?php echo $car['order_id']; ?>">
                                                <?php echo htmlspecialchars($car['vin'] . ' - ' . $car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ') - Order: ' . $car['order_code'] . ' - ' . $car['client_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" name="order_id" id="order_id">
                                <input type="hidden" name="allocate_car" value="1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Add Car to Container
                                </button>
                            </form>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i> <strong>Note:</strong> Maximum capacity is <?php echo $capacity['max_capacity']; ?> cars per container. 
                                You can add <?php echo ($capacity['max_capacity'] - $capacity['used_capacity']); ?> more car(s).
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Container Full:</strong> 
                    This container has reached its maximum capacity of <?php echo $capacity['max_capacity']; ?> cars.
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set order_id when car is selected
        document.getElementById('car_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const orderId = selectedOption.getAttribute('data-order');
            document.getElementById('order_id').value = orderId;
        });
    </script>
</body>
</html>