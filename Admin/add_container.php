<?php
/**
 * Add New Container
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$error = '';

// Get available cars (not in any container and have orders)
// Fixed query to properly get all cars that can be added to containers
$stmt = $pdo->query("
    SELECT DISTINCT car.id, o.vin, car.brand, car.model, car.year, car.trim, o.id as order_id, o.order_id as order_code, c.name as client_name
    FROM cars car
    JOIN orders o ON car.id = o.car_id
    JOIN clients c ON o.client_id = c.id
    WHERE NOT EXISTS (
        SELECT 1 FROM container_cars cc 
        WHERE cc.order_id = o.id
    )
    AND o.shipping_status = 'Awaiting Container'
    ORDER BY car.brand, car.model
");
$available_cars = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $container_id = strtoupper(sanitize($_POST['container_id'] ?? ''));
    $departure_port = sanitize($_POST['departure_port'] ?? '');
    $arrival_port = sanitize($_POST['arrival_port'] ?? 'Algeria Port');
    $departure_date = $_POST['departure_date'] ?? '';
    $estimated_arrival_date = $_POST['estimated_arrival_date'] ?? '';
    $container_shipping_cost = (float)($_POST['container_shipping_cost'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'Scheduled');
    
    $container_number = sanitize($_POST['container_number'] ?? '');
    
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
            
            // Check if container ID already exists
            $stmt = $pdo->prepare("SELECT id FROM containers WHERE container_id = ?");
            $stmt->execute([$container_id]);
            if ($stmt->fetch()) {
                throw new Exception('Container ID already exists');
            }
            
            // Insert container
            $stmt = $pdo->prepare("
                INSERT INTO containers (container_id, container_number, departure_port, arrival_port, departure_date, estimated_arrival_date, container_shipping_cost, status, max_capacity)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 4)
            ");
            
            if (!$stmt->execute([$container_id, $container_number ?: null, $departure_port, $arrival_port, $departure_date, $estimated_arrival_date, $container_shipping_cost, $status])) {
                throw new Exception('Error adding container');
            }
            
            $container_id_db = $pdo->lastInsertId();
            
            // Process selected cars
            foreach ($selected_cars as $car_data) {
                $parts = explode(':', $car_data);
                if (count($parts) == 2) {
                    $car_id = (int)$parts[0];
                    $order_id = (int)$parts[1];
                    
                    // Check if this car is already assigned to this container
                    $stmt = $pdo->prepare("SELECT id FROM container_cars WHERE container_id = ? AND car_id = ?");
                    $stmt->execute([$container_id_db, $car_id]);
                    
                    if (!$stmt->fetch()) {
                        // Allocate car to container only if not already assigned
                        $stmt = $pdo->prepare("
                            INSERT INTO container_cars (container_id, car_id, order_id)
                            VALUES (?, ?, ?)
                        ");
                        
                        if (!$stmt->execute([$container_id_db, $car_id, $order_id])) {
                            throw new Exception('Error allocating car to container');
                        }
                    }
                    
                    // Update order container_id and shipping status
                    $stmt = $pdo->prepare("UPDATE orders SET container_id = ?, shipping_status = 'Shipped on Container' WHERE id = ?");
                    $stmt->execute([$container_id_db, $order_id]);
                }
            }

            $pdo->commit();
            setAlert('success', 'Container added successfully with selected cars');
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
    <title>Add Container - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-box-seam"></i> Add New Container</h1>
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
                                    <input type="text" class="form-control" id="container_id" name="container_id" required>
                                    <small class="text-muted">Unique container identifier</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="container_number" class="form-label">Container Number</label>
                                    <input type="text" class="form-control" id="container_number" name="container_number" placeholder="e.g., SHAF62369400">
                                    <small class="text-muted">For tracking link (searates.com)</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="Scheduled">Scheduled</option>
                                        <option value="En Route">En Route</option>
                                        <option value="Arrived">Arrived</option>
                                        <option value="Unloaded">Unloaded</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="departure_port" class="form-label">Departure Port <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="departure_port" name="departure_port" placeholder="e.g., Shanghai Port" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="arrival_port" class="form-label">Arrival Port</label>
                                    <input type="text" class="form-control" id="arrival_port" name="arrival_port" value="Algeria Port">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="departure_date" class="form-label">Departure Date</label>
                                    <input type="date" class="form-control" id="departure_date" name="departure_date">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="estimated_arrival_date" class="form-label">Estimated Arrival Date</label>
                                    <input type="date" class="form-control" id="estimated_arrival_date" name="estimated_arrival_date">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="container_shipping_cost" class="form-label">Shipping Cost (USD)</label>
                                    <input type="number" class="form-control" id="container_shipping_cost" name="container_shipping_cost" step="0.01" min="0">
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
                                                        <div class="col-md-6 mb-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input car-checkbox" type="checkbox" 
                                                                       name="cars[]" 
                                                                       value="<?php echo $car['id'] . ':' . $car['order_id']; ?>" 
                                                                       id="car_<?php echo $index; ?>">
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
                                <i class="bi bi-info-circle"></i> <strong>Note:</strong> Maximum capacity is 4 cars per container. You can select up to 4 cars from the list above.
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Container
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
        // Limit selection to 4 cars - enhanced version with better handling
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.car-checkbox');
            
            // Function to update selection count
            function updateSelectionCount() {
                const checkedBoxes = document.querySelectorAll('.car-checkbox:checked');
                const count = checkedBoxes.length;
                
                // Update counter if present
                const counter = document.getElementById('selected-car-count');
                if (counter) {
                    counter.textContent = count;
                }
                
                return count;
            }
            
            // Initialize counter
            updateSelectionCount();
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const count = updateSelectionCount();
                    
                    if (count > 4) {
                        this.checked = false;
                        alert('You can select a maximum of 4 cars per container.');
                        updateSelectionCount(); // Update count after unchecking
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>