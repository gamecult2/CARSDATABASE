<?php
/**
 * Financial Dashboard
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

// Get financial summary
$financial = getFinancialSummary();

// Get overdue payments
$overdue = getOverduePayments(30);

// Get monthly revenue (last 12 months)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m') as month,
        COUNT(*) as order_count,
        SUM(total_sale_price) as revenue,
        SUM((SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = orders.id)) as paid
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month DESC
");
$monthly_data = $stmt->fetchAll();

// Get top clients by revenue
$stmt = $pdo->query("
    SELECT c.name, c.client_id,
           COUNT(o.id) as order_count,
           SUM(o.total_sale_price) as total_revenue
    FROM clients c
    JOIN orders o ON c.id = o.client_id
    GROUP BY c.id
    ORDER BY total_revenue DESC
    LIMIT 10
");
$top_clients = $stmt->fetchAll();

// Get profitability by car model
$stmt = $pdo->prepare("
    SELECT 
        car.brand,
        car.model,
        COUNT(o.id) as units_sold,
        SUM(o.total_sale_price) as total_revenue,
        SUM(car.purchase_price_usd) as total_cost,
        SUM(COALESCE(cont.container_shipping_cost / NULLIF(
            (SELECT COUNT(*) FROM container_cars cc JOIN orders o2 ON cc.order_id = o2.id WHERE cc.container_id = o.container_id), 0), 0)) as shipping_cost,
        SUM(o.total_sale_price - car.purchase_price_usd - 
            COALESCE(cont.container_shipping_cost / NULLIF(
                (SELECT COUNT(*) FROM container_cars cc JOIN orders o2 ON cc.order_id = o2.id WHERE cc.container_id = o.container_id), 0), 0)) as profit
    FROM orders o
    JOIN cars car ON o.car_id = car.id
    LEFT JOIN containers cont ON o.container_id = cont.id
    GROUP BY car.brand, car.model
    ORDER BY profit DESC
    LIMIT 10
");
$profitability = $stmt->fetchAll();

// Define missing variables
$total_orders = array_sum(array_column($monthly_data, 'order_count'));
$total_revenue = array_sum(array_column($monthly_data, 'revenue'));
$total_shipping_costs = 0;

// Calculate profit per order
$profit_per_order = $total_orders > 0 ? ($total_revenue - $total_shipping_costs) / $total_orders : 0;

// Get orders with container info for detailed view
$stmt = $pdo->prepare("
    SELECT o.*, c.name as client_name, cont.container_id as container_code,
           COALESCE(payments.total_paid, 0) as total_paid,
           (o.total_sale_price - COALESCE(payments.total_paid, 0)) as remaining_balance,
           CASE 
               WHEN getContainerCarCount(o.container_id) > 0 
               THEN cont.container_shipping_cost / getContainerCarCount(o.container_id)
               ELSE 0 
           END as shipping_cost_per_order
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    LEFT JOIN containers cont ON o.container_id = cont.id
    LEFT JOIN (
        SELECT order_id, SUM(amount_paid) as total_paid
        FROM payments
        GROUP BY order_id
    ) payments ON o.id = payments.order_id
    ORDER BY o.order_date DESC
    LIMIT 10
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php require_once 'includes/header.php'; ?>
        
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <h2><i class="bi bi-graph-up"></i> Financial Dashboard</h2>
                    <p class="text-muted">Overview of financial performance and key metrics</p>
                </div>
            </div>
            
            <?php displayAlert(); ?>
            
            <!-- Financial Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-uppercase">Total Revenue</h6>
                                    <h2><?php echo formatCurrency($financial['total_revenue']); ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-currency-dollar fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-uppercase">Total Paid</h6>
                                    <h2><?php echo formatCurrency($financial['total_paid']); ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-wallet fs-1"></i>
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
                                    <h6 class="text-uppercase">Outstanding</h6>
                                    <h2><?php echo formatCurrency($financial['outstanding']); ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-clock-history fs-1"></i>
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
                                    <h6 class="text-uppercase">Profit</h6>
                                    <h2><?php echo formatCurrency($financial['profit']); ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-graph-up-arrow fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Data Sections -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-bar-chart"></i> Monthly Revenue (Last 12 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-exclamation-triangle"></i> Overdue Payments (30+ days)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($overdue)): ?>
                                <p class="text-center text-muted">No overdue payments</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Client</th>
                                                <th>Order ID</th>
                                                <th>Balance</th>
                                                <th>Days Overdue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($overdue as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['client_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['order_id']); ?></td>
                                                    <td><?php echo formatCurrency($item['remaining_balance']); ?></td>
                                                    <td><?php echo $item['days_overdue']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-people"></i> Top Clients by Revenue</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($top_clients)): ?>
                                <p class="text-center text-muted">No client data available</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Client</th>
                                                <th>Orders</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_clients as $client): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                                    <td><?php echo $client['order_count']; ?></td>
                                                    <td><?php echo formatCurrency($client['total_revenue']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-car-front"></i> Profitability by Car Model</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($profitability)): ?>
                                <p class="text-center text-muted">No car data available</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Model</th>
                                                <th>Sold</th>
                                                <th>Profit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($profitability as $car): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></td>
                                                    <td><?php echo $car['units_sold']; ?></td>
                                                    <td><?php echo formatCurrency($car['profit']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Chart
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('revenueChart').getContext('2d');
            var months = <?php echo json_encode(array_column($monthly_data, 'month')); ?>;
            var revenues = <?php echo json_encode(array_column($monthly_data, 'revenue')); ?>;
            var paid = <?php echo json_encode(array_column($monthly_data, 'paid')); ?>;
            
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Total Revenue',
                        data: revenues,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Payments Received',
                        data: paid,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>