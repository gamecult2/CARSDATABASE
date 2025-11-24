<?php
/**
 * Create New Order
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)($_POST['client_id'] ?? 0);
    $car_id = (int)($_POST['car_id'] ?? 0);
    $vin = strtoupper(sanitize($_POST['vin'] ?? ''));
    $order_date = $_POST['order_date'] ?? date('Y-m-d');
    $total_sale_price = (float)($_POST['total_sale_price'] ?? 0);
    $deposit_amount = (float)($_POST['deposit_amount'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (!$client_id || !$car_id || $total_sale_price <= 0) {
        $error = 'Please fill all required fields';
    } else {
        // Check if car model is available
        $stmt = $pdo->prepare("SELECT status FROM cars WHERE id = ?");
        $stmt->execute([$car_id]);
        $car = $stmt->fetch();
        
        if (!$car) {
            $error = 'Car model not found';
        } else {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Generate order ID
                $order_id = generateUniqueID('ORD', 'orders', 'order_id');
                
                // Create order (VIN is optional, can be added later)
                $stmt = $pdo->prepare("
                    INSERT INTO orders (order_id, client_id, car_id, vin, order_date, total_sale_price, notes, shipping_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Awaiting Container')
                ");
                $stmt->execute([$order_id, $client_id, $car_id, $vin ?: null, $order_date, $total_sale_price, $notes]);
                $order_db_id = $pdo->lastInsertId();
                
                // Update car status
                $stmt = $pdo->prepare("UPDATE cars SET status = 'Sold' WHERE id = ?");
                $stmt->execute([$car_id]);
                
                // Create deposit payment if provided
                if ($deposit_amount > 0) {
                    $payment_id = generateUniqueID('PAY', 'payments', 'payment_id');
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (payment_id, order_id, payment_date, amount_paid, payment_method, payment_type)
                        VALUES (?, ?, ?, ?, 'Bank Transfer', 'Deposit')
                    ");
                    $stmt->execute([$payment_id, $order_db_id, $order_date, $deposit_amount]);
                }
                
                $pdo->commit();
                setAlert('success', 'Order created successfully');
                header('Location: order_details.php?id=' . $order_db_id);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error creating order: ' . $e->getMessage();
            }
        }
    }
}

// Get available clients
$stmt = $pdo->query("SELECT id, client_id, name FROM clients ORDER BY name");
$clients = $stmt->fetchAll();

// Get unique brands for the brand dropdown
$stmt = $pdo->query("SELECT DISTINCT brand FROM cars WHERE status IN ('Available', 'Reserved') ORDER BY brand");
$brands = $stmt->fetchAll();

// Get available cars (models) - will be filtered by brand using JavaScript
$stmt = $pdo->query("SELECT id, brand, model, year, trim, sale_price_usd, status FROM cars WHERE status IN ('Available', 'Reserved') ORDER BY brand, model, year");
$cars = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-cart-plus"></i> Create New Order</h1>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Orders
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="orderForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select" id="client_id" name="client_id" required>
                                        <option value="">Select Client</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>">
                                                <?php echo htmlspecialchars($client['client_id'] . ' - ' . $client['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small><a href="add_client.php" target="_blank">Add new client</a></small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="brand" class="form-label">Car Brand <span class="text-danger">*</span></label>
                                    <select class="form-select" id="brand" name="brand" required>
                                        <option value="">Select Brand</option>
                                        <?php foreach ($brands as $brand): ?>
                                            <option value="<?php echo htmlspecialchars($brand['brand']); ?>">
                                                <?php echo htmlspecialchars($brand['brand']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="car_id" class="form-label">Car Model <span class="text-danger">*</span></label>
                                    <select class="form-select" id="car_id" name="car_id" required>
                                        <option value="">Select Car Model</option>
                                        <!-- Options will be populated by JavaScript based on brand selection -->
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="vin" class="form-label">VIN (Vehicle Identification Number)</label>
                                    <input type="text" class="form-control" id="vin" name="vin" maxlength="17" placeholder="Optional - can be added later">
                                    <small class="text-muted">17 characters - specific to this order</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="total_sale_price" class="form-label">Total Sale Price (USD) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="total_sale_price" name="total_sale_price" step="0.01" min="0" required>
                                    <small class="text-muted">Will auto-fill from car model price when selected</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="deposit_amount" class="form-label">Initial Deposit (USD)</label>
                                    <input type="number" class="form-control" id="deposit_amount" name="deposit_amount" step="0.01" min="0" value="0">
                                    <small class="text-muted">Optional: Create initial payment record</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Create Order
                            </button>
                            <a href="orders.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare car data grouped by brand
        const carsData = <?php echo json_encode($cars); ?>;
        const carsByBrand = {};
        
        // Group cars by brand
        carsData.forEach(car => {
            if (!carsByBrand[car.brand]) {
                carsByBrand[car.brand] = [];
            }
            const modelName = car.brand + ' ' + car.model + ' ' + car.year + (car.trim ? ' ' + car.trim : '');
            carsByBrand[car.brand].push({
                id: car.id,
                name: modelName,
                price: car.sale_price_usd
            });
        });
        
        // Handle brand selection change
        document.getElementById('brand').addEventListener('change', function() {
            const brand = this.value;
            const carSelect = document.getElementById('car_id');
            
            // Clear current options
            carSelect.innerHTML = '<option value="">Select Car Model</option>';
            
            // Populate with models of selected brand
            if (brand && carsByBrand[brand]) {
                carsByBrand[brand].forEach(car => {
                    const option = document.createElement('option');
                    option.value = car.id;
                    option.textContent = car.name + ' - ' + new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: 'USD'
                    }).format(car.price);
                    option.setAttribute('data-price', car.price);
                    carSelect.appendChild(option);
                });
            }
            
            // Reset price field
            document.getElementById('total_sale_price').value = '';
        });
        
        // Auto-fill price when car is selected
        document.getElementById('car_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                document.getElementById('total_sale_price').value = price;
            } else {
                document.getElementById('total_sale_price').value = '';
            }
        });
    </script>
</body>
</html>