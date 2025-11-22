<?php
/**
 * Payments Management
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
    if ($stmt->execute([$id])) {
        setAlert('success', 'Payment deleted successfully');
    } else {
        setAlert('danger', 'Error deleting payment');
    }
    header('Location: payments.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';

$where = "1=1";
$params = [];

if ($filter === 'overdue') {
    // Get overdue payments
    $overdue = getOverduePayments(30);
} else {
    if (!empty($search)) {
        $where .= " AND (p.payment_id LIKE ? OR o.order_id LIKE ? OR c.name LIKE ?)";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $searchParam];
    }
    
    // Get all payments
    $stmt = $pdo->prepare("
        SELECT p.*, o.order_id, o.total_sale_price, c.name as client_name,
               (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = o.id) as total_paid
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        JOIN clients c ON o.client_id = c.id
        WHERE $where
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-cash-stack"></i> Payments Management</h1>
                    <a href="add_payment.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Payment
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" placeholder="Search by payment ID, order ID, or client name..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="filter">
                                    <option value="">All Payments</option>
                                    <option value="overdue" <?php echo $filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Payments Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <?php if ($filter === 'overdue'): ?>
                                Overdue Payments (<?php echo count($overdue ?? []); ?>)
                            <?php else: ?>
                                All Payments (<?php echo count($payments ?? []); ?>)
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Order ID</th>
                                        <th>Client</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Type</th>
                                        <th>Reference</th>
                                        <?php if ($filter === 'overdue'): ?>
                                            <th>Days Overdue</th>
                                            <th>Remaining Balance</th>
                                        <?php else: ?>
                                            <th>Order Total</th>
                                            <th>Total Paid</th>
                                            <th>Balance</th>
                                        <?php endif; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $data = $filter === 'overdue' ? ($overdue ?? []) : ($payments ?? []);
                                    if (empty($data)): ?>
                                        <tr>
                                            <td colspan="<?php echo $filter === 'overdue' ? '10' : '12'; ?>" class="text-center text-muted">No payments found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($data as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['payment_id'] ?? $payment['order_id']); ?></td>
                                            <td>
                                                <a href="order_details.php?id=<?php echo $payment['order_id'] ?? $payment['id']; ?>">
                                                    <?php echo htmlspecialchars($payment['order_id']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                            <td><?php echo formatDate($payment['payment_date'] ?? $payment['order_date']); ?></td>
                                            <td>
                                                <?php if (isset($payment['amount_paid'])): ?>
                                                    <strong class="text-success"><?php echo formatCurrency($payment['amount_paid']); ?></strong>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $payment['payment_method'] ?? '-'; ?></td>
                                            <td><span class="badge bg-info"><?php echo $payment['payment_type'] ?? '-'; ?></span></td>
                                            <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                                            <?php if ($filter === 'overdue'): ?>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?php echo $payment['days_since_order']; ?> days
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-warning">
                                                        <?php echo formatCurrency($payment['remaining_balance']); ?>
                                                    </strong>
                                                </td>
                                            <?php else: ?>
                                                <td><?php echo formatCurrency($payment['total_sale_price']); ?></td>
                                                <td><?php echo formatCurrency($payment['total_paid']); ?></td>
                                                <td>
                                                    <?php 
                                                    $balance = $payment['total_sale_price'] - $payment['total_paid'];
                                                    ?>
                                                    <span class="<?php echo $balance > 0 ? 'text-warning fw-bold' : 'text-success'; ?>">
                                                        <?php echo formatCurrency($balance); ?>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <?php if (isset($payment['id']) && !isset($payment['days_since_order'])): ?>
                                                    <a href="?delete=1&id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this payment?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

