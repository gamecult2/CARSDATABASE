<?php
/**
 * Admin Dashboard
 * Car Dealership Client and Logistics Management System
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

// Get dashboard statistics
$stats = [];

// Total clients
$stmt = $pdo->query("SELECT COUNT(*) FROM clients");
$stats['total_clients'] = $stmt->fetchColumn();

// Total cars
$stmt = $pdo->query("SELECT COUNT(*) FROM cars");
$stats['total_cars'] = $stmt->fetchColumn();

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$stats['total_orders'] = $stmt->fetchColumn();

// Active containers
$stmt = $pdo->query("SELECT COUNT(*) FROM containers WHERE status IN ('Scheduled', 'En Route')");
$stats['active_containers'] = $stmt->fetchColumn();

// Financial summary
$financial = getFinancialSummary();

// Recent orders
$stmt = $pdo->query("
    SELECT o.*, c.name as client_name, car.brand, car.model, car.year, car.trim
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    JOIN cars car ON o.car_id = car.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$recent_orders = $stmt->fetchAll();

// Pending payments
$overdue = getOverduePayments(30);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-speedometer2"></i> Dashboard</h1>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-uppercase">Total Clients</h6>
                                        <h2><?php echo $stats['total_clients']; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-people fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-uppercase">Total Cars</h6>
                                        <h2><?php echo $stats['total_cars']; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-car-front fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-uppercase">Total Orders</h6>
                                        <h2><?php echo $stats['total_orders']; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-cart-check fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-uppercase">Active Containers</h6>
                                        <h2><?php echo $stats['active_containers']; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-box-seam fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Financial Summary -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-cash-stack"></i> Financial Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Total Revenue:</strong><br>
                                        <span class="text-success fs-4"><?php echo formatCurrency($financial['total_revenue']); ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Total Paid:</strong><br>
                                        <span class="text-primary fs-4"><?php echo formatCurrency($financial['total_paid']); ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Outstanding:</strong><br>
                                        <span class="text-warning fs-4"><?php echo formatCurrency($financial['outstanding']); ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Profit:</strong><br>
                                        <span class="text-info fs-4"><?php echo formatCurrency($financial['profit']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h5><i class="bi bi-clock-history"></i> Recent Orders</h5>
                                <a href="clients_and_orders.php?tab=orders" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Client</th>
                                                <th>Car</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <?php $modelName = $order['brand'] . ' ' . $order['model'] . ' ' . $order['year'] . ($order['trim'] ? ' ' . $order['trim'] : ''); ?>
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
                                            <tr>
                                                <td><a href="order_details.php?id=<?php echo $order['id']; ?>"><?php echo $order['order_id']; ?></a></td>
                                                <td><?php echo htmlspecialchars($order['client_name']); ?></td>
                                                <td><?php echo htmlspecialchars($modelName); ?></td>
                                                <td><?php echo formatCurrency($order['total_sale_price']); ?></td>
                                                <td><?php echo formatDate($order['order_date']); ?></td>
                                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $order['shipping_status']; ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Overdue Payments -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-exclamation-triangle text-warning"></i> Overdue Payments</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($overdue) > 0): ?>
                                    <div class="list-group">
                                        <?php foreach (array_slice($overdue, 0, 5) as $payment): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($payment['client_name']); ?></h6>
                                                <small><?php echo formatCurrency($payment['remaining_balance']); ?></small>
                                            </div>
                                            <p class="mb-1"><small><?php echo $payment['order_id']; ?></small></p>
                                            <small class="text-danger"><?php echo $payment['days_since_order']; ?> days overdue</small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <a href="payments.php?filter=overdue" class="btn btn-sm btn-warning w-100 mt-2">View All Overdue</a>
                                <?php else: ?>
                                    <p class="text-muted text-center">No overdue payments</p>
                                <?php endif; ?>
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

