<?php
/**
 * Add Client and Order
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole(['admin', 'sales_agent']);

$error = '';

// Get distinct brands for the first dropdown
$stmt = $pdo->query("SELECT DISTINCT brand FROM cars WHERE status = 'Available' ORDER BY brand");
$brands = $stmt->fetchAll();

// Get all cars for JavaScript processing
$stmt = $pdo->query("SELECT id, brand, model, year, trim, sale_price_usd FROM cars WHERE status = 'Available' ORDER BY brand, model");
$cars = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Client data
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $passport_number = sanitize($_POST['passport_number'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $user_password = $_POST['user_password'] ?? '';
    
    // Order data
    $car_id = (int)($_POST['car_id'] ?? 0);
    $order_date = sanitize($_POST['order_date'] ?? '');
    $total_sale_price = (float)($_POST['total_sale_price'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (empty($name) || empty($phone)) {
        $error = 'Client name and phone are required';
    } elseif (empty($car_id) || empty($order_date) || empty($total_sale_price)) {
        $error = 'Car, order date, and total sale price are required';
    } else {
        $pdo->beginTransaction();
        try {
            // Create client
            $client_id = generateUniqueID('CLI', 'clients', 'client_id');
            
            $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO clients (client_id, name, phone, email, passport_number, address, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$client_id, $name, $phone, $email, $passport_number, $address, $hashed_password])) {
                $client_db_id = $pdo->lastInsertId();
                
                // Create order
                $order_id = generateUniqueID('ORD', 'orders', 'order_id');
                $stmt = $pdo->prepare("
                    INSERT INTO orders (order_id, client_id, car_id, order_date, total_sale_price, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$order_id, $client_db_id, $car_id, $order_date, $total_sale_price, $notes])) {
                    $pdo->commit();
                    setAlert('success', 'Client and order added successfully');
                    header('Location: clients.php');
                    exit;
                } else {
                    throw new Exception('Error adding order');
                }
            } else {
                throw new Exception('Error adding client');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error adding client and order: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Client and Order - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-person-plus"></i> Add Client and Order</h1>
                    <a href="clients.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Clients
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <!-- Client Information -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="bi bi-person"></i> Client Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="phone" name="phone" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                    <div class="mb-3">
                                        <label for="passport_number" class="form-label">Passport Number</label>
                                        <input type="text" class="form-control" id="passport_number" name="passport_number">
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="user_password" class="form-label">Portal Password</label>
                                        <input type="text" class="form-control" id="user_password" name="user_password" value="<?php echo htmlspecialchars($passport_number ?? ''); ?>">
                                        <div class="form-text">By default, the passport number will be used as the password</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Information -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="bi bi-cart"></i> Order Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="car_brand" class="form-label">Car Brand <span class="text-danger">*</span></label>
                                        <select class="form-select" id="car_brand" name="car_brand">
                                            <option value="">Select a brand</option>
                                            <?php foreach ($brands as $brand): ?>
                                                <option value="<?php echo htmlspecialchars($brand['brand']); ?>">
                                                    <?php echo htmlspecialchars($brand['brand']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="car_id" class="form-label">Car Model <span class="text-danger">*</span></label>
                                        <select class="form-select" id="car_id" name="car_id" required disabled>
                                            <option value="">Select a brand first</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="total_sale_price" class="form-label">Total Sale Price ($) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="total_sale_price" name="total_sale_price" step="0.01" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Client and Order
                        </button>
                        <a href="clients.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store cars data in JavaScript
            const carsData = <?php echo json_encode($cars); ?>;
            const brandSelect = document.getElementById('car_brand');
            const modelSelect = document.getElementById('car_id');
            const priceInput = document.getElementById('total_sale_price');
            
            // When brand selection changes
            brandSelect.addEventListener('change', function() {
                const selectedBrand = this.value;
                
                // Clear and disable model select
                modelSelect.innerHTML = '<option value="">Select a model</option>';
                modelSelect.disabled = true;
                priceInput.value = '';
                
                if (selectedBrand) {
                    // Filter cars by selected brand
                    const filteredCars = carsData.filter(car => car.brand === selectedBrand);
                    
                    if (filteredCars.length > 0) {
                        // Enable model select and populate options
                        modelSelect.disabled = false;
                        filteredCars.forEach(car => {
                            const option = document.createElement('option');
                            option.value = car.id;
                            option.setAttribute('data-price', car.sale_price_usd);
                            option.textContent = car.model + ' ' + car.year + ' (' + car.trim + ') - $' + parseFloat(car.sale_price_usd).toLocaleString();
                            modelSelect.appendChild(option);
                        });
                    } else {
                        modelSelect.innerHTML = '<option value="">No models available</option>';
                    }
                } else {
                    modelSelect.innerHTML = '<option value="">Select a brand first</option>';
                }
            });
            
            // When model selection changes
            modelSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.getAttribute('data-price');
                if (price) {
                    priceInput.value = price;
                } else {
                    priceInput.value = '';
                }
            });
        });
    </script>
</body>
</html>