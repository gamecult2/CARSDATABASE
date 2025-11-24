<?php
/**
 * Add New Car
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$error = '';

// Get distinct brands for reference
$stmt = $pdo->query("SELECT DISTINCT brand FROM cars ORDER BY brand");
$existing_brands = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand = sanitize($_POST['brand'] ?? '');
    $model = sanitize($_POST['model'] ?? '');
    $year = (int)($_POST['year'] ?? date('Y'));
    $trim = sanitize($_POST['trim'] ?? '');
    $purchase_price_usd = (float)($_POST['purchase_price_usd'] ?? 0);
    $sale_price_usd = (float)($_POST['sale_price_usd'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'Available');
    $color = sanitize($_POST['color'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (empty($brand) || empty($model)) {
        $error = 'Brand and model are required';
    } else {
        // Check if model combination already exists
        $stmt = $pdo->prepare("SELECT id FROM cars WHERE brand = ? AND model = ? AND year = ? AND (trim = ? OR (trim IS NULL AND ? IS NULL))");
        $stmt->execute([$brand, $model, $year, $trim, $trim]);
        if ($stmt->fetch()) {
            $error = 'This model combination already exists';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO cars (brand, model, year, trim, purchase_price_usd, sale_price_usd, status, color, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$brand, $model, $year, $trim, $purchase_price_usd, $sale_price_usd, $status, $color, $description])) {
                setAlert('success', 'Car model added successfully');
                header('Location: cars.php');
                exit;
            } else {
                $error = 'Error adding car model';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Car - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-car-front"></i> Add New Car Model</h1>
                    <a href="cars.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Cars
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
                                    <label for="brand" class="form-label">Brand <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="brand" name="brand" list="existing-brands" required>
                                    <datalist id="existing-brands">
                                        <?php foreach ($existing_brands as $brand): ?>
                                            <option value="<?php echo htmlspecialchars($brand['brand']); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text">Start typing to see existing brands or enter a new one</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="model" name="model" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="year" class="form-label">Year</label>
                                    <input type="number" class="form-control" id="year" name="year" min="1900" max="<?php echo date('Y') + 5; ?>" value="<?php echo date('Y'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="trim" class="form-label">Trim</label>
                                    <input type="text" class="form-control" id="trim" name="trim">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="purchase_price_usd" class="form-label">Purchase Price ($)</label>
                                    <input type="number" class="form-control" id="purchase_price_usd" name="purchase_price_usd" step="0.01" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="sale_price_usd" class="form-label">Sale Price ($)</label>
                                    <input type="number" class="form-control" id="sale_price_usd" name="sale_price_usd" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="Available">Available</option>
                                        <option value="In Production">In Production</option>
                                        <option value="In Transit">In Transit</option>
                                        <option value="Customs Cleared">Customs Cleared</option>
                                        <option value="Delivered">Delivered</option>
                                        <option value="Sold">Sold</option>
                                        <option value="Reserved">Reserved</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="color" class="form-label">Color</label>
                                    <input type="text" class="form-control" id="color" name="color">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Add Car Model
                                </button>
                                <a href="cars.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>