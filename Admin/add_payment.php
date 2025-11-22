<?php
/**
 * Add Payment
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$order_id = (int)($_GET['order_id'] ?? 0);
$error = '';

// Get order if provided
$order = null;
if ($order_id) {
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as client_name, car.brand, car.model
        FROM orders o
        JOIN clients c ON o.client_id = c.id
        JOIN cars car ON o.car_id = car.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $amount_paid = (float)($_POST['amount_paid'] ?? 0);
    $payment_method = sanitize($_POST['payment_method'] ?? 'Bank Transfer');
    $payment_type = sanitize($_POST['payment_type'] ?? 'Installment');
    $reference_number = sanitize($_POST['reference_number'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (!$order_id || $amount_paid <= 0) {
        $error = 'Please fill all required fields';
    } else {
        // Check order exists
        $stmt = $pdo->prepare("SELECT total_sale_price FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order_check = $stmt->fetch();
        
        if (!$order_check) {
            $error = 'Order not found';
        } else {
            // Check if payment exceeds order total
            $remaining = calculateRemainingBalance($order_id);
            if ($amount_paid > $remaining) {
                $error = 'Payment amount exceeds remaining balance: ' . formatCurrency($remaining);
            } else {
                $payment_id = generateUniqueID('PAY', 'payments', 'payment_id');
                
                $stmt = $pdo->prepare("
                    INSERT INTO payments (payment_id, order_id, payment_date, amount_paid, payment_method, payment_type, reference_number, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$payment_id, $order_id, $payment_date, $amount_paid, $payment_method, $payment_type, $reference_number, $notes])) {
                    setAlert('success', 'Payment recorded successfully');
                    header('Location: order_details.php?id=' . $order_id);
                    exit;
                } else {
                    $error = 'Error recording payment';
                }
            }
        }
    }
}

// Get all orders for dropdown
$stmt = $pdo->query("
    SELECT o.id, o.order_id, c.name as client_name, car.brand, car.model,
           o.total_sale_price,
           (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = o.id) as total_paid
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    JOIN cars car ON o.car_id = car.id
    ORDER BY o.order_date DESC
");
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-cash-stack"></i> Add Payment</h1>
                    <a href="payments.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Payments
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
                                <div class="col-md-12 mb-3">
                                    <label for="order_id" class="form-label">Order <span class="text-danger">*</span></label>
                                    <select class="form-select" id="order_id" name="order_id" required>
                                        <option value="">Select Order</option>
                                        <?php foreach ($orders as $ord): ?>
                                            <?php 
                                            $remaining = $ord['total_sale_price'] - $ord['total_paid'];
                                            ?>
                                            <option value="<?php echo $ord['id']; ?>" 
                                                    data-total="<?php echo $ord['total_sale_price']; ?>"
                                                    data-paid="<?php echo $ord['total_paid']; ?>"
                                                    data-remaining="<?php echo $remaining; ?>"
                                                    <?php echo $order_id == $ord['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($ord['order_id'] . ' - ' . $ord['client_name'] . ' - ' . $ord['brand'] . ' ' . $ord['model'] . ' (Remaining: ' . formatCurrency($remaining) . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($order): ?>
                                        <div class="mt-2 p-2 bg-light rounded">
                                            <strong>Order:</strong> <?php echo htmlspecialchars($order['order_id']); ?><br>
                                            <strong>Client:</strong> <?php echo htmlspecialchars($order['client_name']); ?><br>
                                            <strong>Car:</strong> <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?><br>
                                            <strong>Total:</strong> <?php echo formatCurrency($order['total_sale_price']); ?><br>
                                            <strong>Remaining:</strong> 
                                            <span class="text-warning fw-bold">
                                                <?php echo formatCurrency(calculateRemainingBalance($order_id)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="amount_paid" class="form-label">Amount Paid (DZD) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" min="0" required>
                                    <small class="text-muted" id="remaining_balance"></small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method">
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Check">Check</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="payment_type" class="form-label">Payment Type</label>
                                    <select class="form-select" id="payment_type" name="payment_type">
                                        <option value="Deposit">Deposit</option>
                                        <option value="Installment" selected>Installment</option>
                                        <option value="Full Payment">Full Payment</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="reference_number" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="reference_number" name="reference_number" placeholder="Transaction reference">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Record Payment
                            </button>
                            <a href="payments.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update remaining balance when order is selected
        document.getElementById('order_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const remaining = selectedOption.getAttribute('data-remaining');
            if (remaining) {
                document.getElementById('remaining_balance').textContent = 
                    'Remaining balance: ' + parseFloat(remaining).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' DZD';
            }
        });
    </script>
</body>
</html>

