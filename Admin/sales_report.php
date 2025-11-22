<?php
/**
 * Sales Performance Report
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

// Default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// Fetch sales data within the date range
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_sales,
        SUM(o.total_sale_price) as total_revenue,
        AVG(o.total_sale_price) as avg_sale_price
    FROM orders o
    WHERE o.order_date BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$summary = $stmt->fetch();

// Fetch sales by car brand
$stmt = $pdo->prepare("
    SELECT
        c.brand,
        COUNT(*) as cars_sold,
        SUM(o.total_sale_price) as brand_revenue
    FROM orders o
    JOIN cars c ON o.car_id = c.id
    WHERE o.order_date BETWEEN ? AND ?
    GROUP BY c.brand
    ORDER BY brand_revenue DESC
");
$stmt->execute([$start_date, $end_date]);
$sales_by_brand = $stmt->fetchAll();

// Fetch sales by car model
$stmt = $pdo->prepare("
    SELECT
        c.brand,
        c.model,
        c.year,
        COUNT(*) as cars_sold,
        SUM(o.total_sale_price) as model_revenue
    FROM orders o
    JOIN cars c ON o.car_id = c.id
    WHERE o.order_date BETWEEN ? AND ?
    GROUP BY c.brand, c.model, c.year
    ORDER BY model_revenue DESC
");
$stmt->execute([$start_date, $end_date]);
$sales_by_model = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Performance Report - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-graph-up"></i> Sales Performance Report</h1>
                </div>

                <?php displayAlert(); ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Filter by Date Range</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-5">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="text-uppercase">Total Sales</h6>
                                <h2><?php echo $summary['total_sales'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="text-uppercase">Total Revenue</h6>
                                <h2><?php echo formatCurrency($summary['total_revenue'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="text-uppercase">Average Sale Price</h6>
                                <h2><?php echo formatCurrency($summary['avg_sale_price'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Sales by Brand</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Brand</th>
                                            <th>Cars Sold</th>
                                            <th>Total Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales_by_brand as $brand): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($brand['brand']); ?></td>
                                            <td><?php echo $brand['cars_sold']; ?></td>
                                            <td><?php echo formatCurrency($brand['brand_revenue']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Sales by Model</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Model</th>
                                            <th>Cars Sold</th>
                                            <th>Total Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales_by_model as $model): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($model['brand'] . ' ' . $model['model'] . ' ' . $model['year']); ?></td>
                                            <td><?php echo $model['cars_sold']; ?></td>
                                            <td><?php echo formatCurrency($model['model_revenue']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
