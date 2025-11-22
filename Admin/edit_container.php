<?php
/**
 * Edit Container
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

// Get capacity info
$capacity = getContainerCapacity($id);

// Get cars in container
$stmt = $pdo->prepare("
    SELECT cc.*, o.vin, car.brand, car.model, car.year, car.trim, o.order_id, c.name as client_name, o.id as order_id_db
    FROM container_cars cc
    JOIN cars car ON cc.car_id = car.id
    LEFT JOIN orders o ON cc.order_id = o.id
    LEFT JOIN clients c ON o.client_id = c.id
    WHERE cc.container_id = ?
    ORDER BY cc.loaded_at DESC
");
$stmt->execute([$id]);
$container_cars = $stmt->fetchAll();

// Check if other containers have cars (to determine if we can add cars to this container)
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM container_cars cc
    JOIN orders o ON cc.order_id = o.id
    WHERE cc.container_id != ?
");
$stmt->execute([$id]);
$other_containers_have_cars = $stmt->fetchColumn() > 0;

// Get available cars (not in any container and have orders)
// Fixed query to properly get all cars that can be added to containers, including those already in this container
$stmt = $pdo->prepare("
    SELECT DISTINCT car.id, o.vin, car.brand, car.model, car.year, car.trim, o.id as order_id, o.order_id as order_code, c.name as client_name
    FROM cars car
    JOIN orders o ON car.id = o.car_id
    JOIN clients c ON o.client_id = c.id
    WHERE (o.id NOT IN (SELECT COALESCE(order_id, 0) FROM container_cars WHERE container_id != ?) OR 
           ? = 0 OR
           o.id IN (SELECT COALESCE(order_id, 0) FROM container_cars WHERE container_id = ?))
    AND o.shipping_status IN ('Awaiting Container', 'Shipped on Container')
    ORDER BY car.brand, car.model
");
$stmt->execute([$id, (int)$other_containers_have_cars, $id]);
$available_cars = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $container_id = strtoupper(sanitize($_POST['container_id'] ?? ''));
    $departure_port = sanitize($_POST['departure_port'] ?? '');
    $arrival_port = sanitize($_POST['arrival_port'] ?? 'Algeria Port');
    $container_number = sanitize($_POST['container_number'] ?? '');
    $departure_date = $_POST['departure_date'] ?? '';
    $estimated_arrival_date = $_POST['estimated_arrival_date'] ?? '';
    $actual_arrival_date = $_POST['actual_arrival_date'] ?? '';
    $container_shipping_cost = (float)($_POST['container_shipping_cost'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'Scheduled');
    
    // Get selected cars
    $selected_cars = $_POST['cars'] ?? [];
    
    // Validate that no more than 4 cars are selected
    if (count($selected_cars) > 4) {
        $error = 'You can select a maximum of 4 cars per container';
    } elseif (empty($container_id) || empty($departure_port)) {
        $error = 'Container ID and departure port are required';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if container ID already exists (excluding current)
            $stmt = $pdo->prepare("SELECT id FROM containers WHERE container_id = ? AND id != ?");
            $stmt->execute([$container_id, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Container ID already exists');
            }
            
            // Update container
            $stmt = $pdo->prepare("
                UPDATE containers 
                SET container_id = ?, container_number = ?, departure_port = ?, arrival_port = ?, departure_date = ?, 
                    estimated_arrival_date = ?, actual_arrival_date = ?, container_shipping_cost = ?, status = ?
                WHERE id = ?
            ");
            
            if (!$stmt->execute([$container_id, $container_number ?: null, $departure_port, $arrival_port, $departure_date, $estimated_arrival_date, $actual_arrival_date, $container_shipping_cost, $status, $id])) {
                throw new Exception('Error updating container');
            }
            
            // Remove all current car associations
            $stmt = $pdo->prepare("DELETE FROM container_cars WHERE container_id = ?");
            $stmt->execute([$id]);
            
            // Reset container_id and shipping_status for orders previously linked to this container
            $stmt = $pdo->prepare("UPDATE orders SET container_id = NULL, shipping_status = 'Awaiting Container' WHERE container_id = ?");
            $stmt->execute([$id]);
            
            // Process selected cars
            foreach ($selected_cars as $car_data) {
                $parts = explode(':', $car_data);
                if (count($parts) == 2) {
                    $car_id = (int)$parts[0];
                    $order_id = (int)$parts[1];
                    
                    // Check if this car is already assigned to this container
                    $stmt = $pdo->prepare("SELECT id FROM container_cars WHERE container_id = ? AND car_id = ?");
                    $stmt->execute([$id, $car_id]);
                    
                    if (!$stmt->fetch()) {
                        // Allocate car to container only if not already assigned
                        $stmt = $pdo->prepare("
                            INSERT INTO container_cars (container_id, car_id, order_id)
                            VALUES (?, ?, ?)
                        ");
                        
                        if (!$stmt->execute([$id, $car_id, $order_id])) {
                            throw new Exception('Error allocating car to container');
                        }
                    }
                    
                    // Update order container_id and shipping status
                    $stmt = $pdo->prepare("UPDATE orders SET container_id = ?, shipping_status = 'Shipped on Container' WHERE id = ?");
                    $stmt->execute([$id, $order_id]);
                } else {
                    // Invalid car data format - this shouldn't happen with proper form validation
                }
            }

            $pdo->commit();
            setAlert('success', 'Container updated successfully with selected cars');
            header('Location: containers.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Container - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-pencil"></i> Edit Container</h1>
                    <a href="containers.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Containers
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="container_id" class="form-label">Container ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="container_id" name="container_id" value="<?php echo htmlspecialchars($container['container_id']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="container_number" class="form-label">Container Number</label>
                                    <input type="text" class="form-control" id="container_number" name="container_number" value="<?php echo htmlspecialchars($container['container_number'] ?? ''); ?>" placeholder="e.g., SHAF62369400">
                                    <small class="text-muted">For tracking link (searates.com)</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="Scheduled" <?php echo $container['status'] === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="En Route" <?php echo $container['status'] === 'En Route' ? 'selected' : ''; ?>>En Route</option>
                                        <option value="Arrived" <?php echo $container['status'] === 'Arrived' ? 'selected' : ''; ?>>Arrived</option>
                                        <option value="Unloaded" <?php echo $container['status'] === 'Unloaded' ? 'selected' : ''; ?>>Unloaded</option>
                                        <option value="Completed" <?php echo $container['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="departure_port" class="form-label">Departure Port <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="departure_port" name="departure_port" value="<?php echo htmlspecialchars($container['departure_port']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="arrival_port" class="form-label">Arrival Port</label>
                                    <input type="text" class="form-control" id="arrival_port" name="arrival_port" value="<?php echo htmlspecialchars($container['arrival_port']); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="departure_date" class="form-label">Departure Date</label>
                                    <input type="date" class="form-control" id="departure_date" name="departure_date" value="<?php echo $container['departure_date'] ? date('Y-m-d', strtotime($container['departure_date'])) : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="estimated_arrival_date" class="form-label">Estimated Arrival Date</label>
                                    <input type="date" class="form-control" id="estimated_arrival_date" name="estimated_arrival_date" value="<?php echo $container['estimated_arrival_date'] ? date('Y-m-d', strtotime($container['estimated_arrival_date'])) : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="actual_arrival_date" class="form-label">Actual Arrival Date</label>
                                    <input type="date" class="form-control" id="actual_arrival_date" name="actual_arrival_date" value="<?php echo $container['actual_arrival_date'] ? date('Y-m-d', strtotime($container['actual_arrival_date'])) : ''; ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="container_shipping_cost" class="form-label">Shipping Cost (USD)</label>
                                    <input type="number" class="form-control" id="container_shipping_cost" name="container_shipping_cost" step="0.01" min="0" value="<?php echo $container['container_shipping_cost']; ?>">
                                </div>
                            </div>
                            
                            <!-- Car Selection Section -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Select Cars for Container (Max 4)</label>
                                    <div class="card">
                                        <div class="card-body">
                                            <?php if (empty($available_cars)): ?>
                                                <p class="text-muted">No available cars to select</p>
                                            <?php else: ?>
                                                <div class="row">
                                                    <?php foreach ($available_cars as $index => $car): ?>
                                                        <?php 
                                                        $is_selected = false;
                                                        foreach ($container_cars as $cc) {
                                                            if ($cc['order_id_db'] == $car['order_id']) {
                                                                $is_selected = true;
                                                                break;
                                                            }
                                                        }
                                                        ?>
                                                        <div class="col-md-6 mb-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input car-checkbox" type="checkbox" 
                                                                       name="cars[]" 
                                                                       value="<?php echo $car['id'] . ':' . $car['order_id']; ?>" 
                                                                       id="car_<?php echo $index; ?>"
                                                                       <?php echo $is_selected ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="car_<?php echo $index; ?>">
                                                                    <?php echo htmlspecialchars($car['vin'] . ' - ' . $car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ') - Order: ' . $car['order_code'] . ' - ' . $car['client_name']); ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> <strong>Note:</strong> Maximum capacity is 4 cars per container. You can select up to 4 cars from the list above. Unchecking a car will remove it from this container.
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Container
                            </button>
                            <a href="containers.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Limit selection to 4 cars with proper handling
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.car-checkbox');
            
            // Function to update selection count
            function updateSelectionCount() {
                const checkedBoxes = document.querySelectorAll('.car-checkbox:checked');
                const count = checkedBoxes.length;
                return count;
            }
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const count = updateSelectionCount();
                    
                    if (count > 4) {
                        this.checked = false;
                        alert('You can select a maximum of 4 cars per container.');
                        return false;
                    }
                });
            });
            
            // Initialize and check if we already have more than 4 selected (shouldn't happen but just in case)
            const initialCount = updateSelectionCount();
            if (initialCount > 4) {
                alert('Warning: More than 4 cars were previously selected. Please review your selection.');
            }
        });
    </script>
</body>
</html>