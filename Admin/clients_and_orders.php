<?php
/**
 * Clients and Orders Management (Combined View)
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$active_tab = $_GET['tab'] ?? 'clients';

// ============ CLIENTS LOGIC ============
// Handle client delete
if (isset($_GET['delete_client']) && isset($_GET['client_id'])) {
    $id = (int)$_GET['client_id'];
    
    try {
        $pdo->beginTransaction();
        
        // First check if client has any orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ?");
        $stmt->execute([$id]);
        $order_count = $stmt->fetchColumn();
        
        if ($order_count > 0) {
            // Client has orders, cannot delete
            setAlert('danger', 'Cannot delete client because they have ' . $order_count . ' order(s). Please delete their orders first.');
        } else {
            // Client has no orders, safe to delete
            $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            if ($stmt->execute([$id])) {
                setAlert('success', 'Client deleted successfully');
            } else {
                setAlert('danger', 'Error deleting client');
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        setAlert('danger', 'Error deleting client: ' . $e->getMessage());
    }
    
    header('Location: clients_and_orders.php?tab=clients');
    exit;
}

// Handle multiple client deletion
if (isset($_POST['delete_selected_clients']) && isset($_POST['selected_clients'])) {
    $selected_clients = $_POST['selected_clients'];
    $deleted_count = 0;
    $error_count = 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($selected_clients as $client_id) {
            $id = (int)$client_id;
            
            // Check if client has any orders
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ?");
            $stmt->execute([$id]);
            $order_count = $stmt->fetchColumn();
            
            if ($order_count > 0) {
                // Cannot delete client with orders
                $error_count++;
            } else {
                // Delete client
                $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $deleted_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        $pdo->commit();
        
        if ($deleted_count > 0) {
            setAlert('success', "$deleted_count client(s) deleted successfully.");
        }
        
        if ($error_count > 0) {
            setAlert('warning', "$error_count client(s) could not be deleted because they have orders.");
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        setAlert('danger', 'Error deleting clients: ' . $e->getMessage());
    }
    
    header('Location: clients_and_orders.php?tab=clients');
    exit;
}

// Search clients
$client_search = $_GET['client_search'] ?? '';
$client_where = "1=1";
$client_params = [];

if (!empty($client_search)) {
    $client_where .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.client_id LIKE ? OR c.passport_number LIKE ?)";
    $searchParam = "%$client_search%";
    $client_params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
}

// Get clients with order status
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM orders WHERE client_id = c.id) as total_orders,
           (SELECT SUM(total_sale_price) FROM orders WHERE client_id = c.id) as total_spent,
           (SELECT MAX(id) FROM orders WHERE client_id = c.id) as latest_order_id,
           (SELECT MAX(order_date) FROM orders WHERE client_id = c.id) as latest_order_date
    FROM clients c
    WHERE $client_where
    ORDER BY c.created_at DESC
");

// Pagination for clients
$clients_per_page = 20;
$clients_page = max(1, (int)($_GET['clients_page'] ?? 1));
$clients_offset = ($clients_page - 1) * $clients_per_page;

// get total clients count for the current filter
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM clients c WHERE $client_where");
$countStmt->execute($client_params);
$clients_total = (int)$countStmt->fetchColumn();
$clients_total_pages = (int)ceil($clients_total / $clients_per_page);

// fetch page
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM orders WHERE client_id = c.id) as total_orders,
           (SELECT SUM(total_sale_price) FROM orders WHERE client_id = c.id) as total_spent,
           (SELECT MAX(id) FROM orders WHERE client_id = c.id) as latest_order_id,
           (SELECT MAX(order_date) FROM orders WHERE client_id = c.id) as latest_order_date
    FROM clients c
    WHERE $client_where
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$execParams = array_merge($client_params, [$clients_per_page, $clients_offset]);
$stmt->execute($execParams);
$clients = $stmt->fetchAll();

// Get orders for each client (for expandable rows)
$client_orders = [];
foreach ($clients as $client) {
    $stmt = $pdo->prepare("
        SELECT o.*, car.brand, car.model, car.year, car.trim,
               (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = o.id) as total_paid,
               cont.container_id as container_code
        FROM orders o
        JOIN cars car ON o.car_id = car.id
        LEFT JOIN containers cont ON o.container_id = cont.id
        WHERE o.client_id = ?
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$client['id']]);
    $client_orders[$client['id']] = $stmt->fetchAll();
}

// ============ ORDERS LOGIC ============
// Handle order delete
if (isset($_GET['delete_order']) && isset($_GET['order_id'])) {
    $id = (int)$_GET['order_id'];
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    if ($stmt->execute([$id])) {
        setAlert('success', 'Order deleted successfully');
    } else {
        setAlert('danger', 'Error deleting order');
    }
    header('Location: clients_and_orders.php?tab=orders');
    exit;
}

// Handle multiple order deletion
if (isset($_POST['delete_selected_orders']) && isset($_POST['selected_orders'])) {
    $selected_orders = $_POST['selected_orders'];
    $deleted_count = 0;
    $error_count = 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($selected_orders as $order_id) {
            $id = (int)$order_id;
            
            // Delete order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            if ($stmt->execute([$id])) {
                $deleted_count++;
            } else {
                $error_count++;
            }
        }
        
        $pdo->commit();
        
        if ($deleted_count > 0) {
            setAlert('success', "$deleted_count order(s) deleted successfully.");
        }
        
        if ($error_count > 0) {
            setAlert('warning', "$error_count order(s) could not be deleted.");
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        setAlert('danger', 'Error deleting orders: ' . $e->getMessage());
    }
    
    header('Location: clients_and_orders.php?tab=orders');
    exit;
}

// Search and filter orders
$order_search = $_GET['order_search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$order_where = "1=1";
$order_params = [];

if (!empty($order_search)) {
    $order_where .= " AND (o.order_id LIKE ? OR c.name LIKE ? OR o.vin LIKE ? OR car.brand LIKE ?)";
    $searchParam = "%$order_search%";
    $order_params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

if (!empty($status_filter)) {
    $order_where .= " AND o.shipping_status = ?";
    $order_params[] = $status_filter;
}

// Get orders
$stmt = $pdo->prepare("
    SELECT o.*, c.name as client_name, c.phone, car.brand, car.model, car.year, car.trim,
           (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = o.id) as total_paid,
           cont.container_id as container_code
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    JOIN cars car ON o.car_id = car.id
    LEFT JOIN containers cont ON o.container_id = cont.id
    WHERE $order_where
    ORDER BY o.order_date DESC
");

// Pagination for orders
$orders_per_page = 20;
$orders_page = max(1, (int)($_GET['orders_page'] ?? 1));
$orders_offset = ($orders_page - 1) * $orders_per_page;

// get total orders count for the current filter
$ordersCountStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN clients c ON o.client_id = c.id JOIN cars car ON o.car_id = car.id LEFT JOIN containers cont ON o.container_id = cont.id WHERE $order_where");
$ordersCountStmt->execute($order_params);
$orders_total = (int)$ordersCountStmt->fetchColumn();
$orders_total_pages = (int)ceil($orders_total / $orders_per_page);

// fetch page
$stmt = $pdo->prepare("
    SELECT o.*, c.name as client_name, c.phone, car.brand, car.model, car.year, car.trim,
           (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE order_id = o.id) as total_paid,
           cont.container_id as container_code
    FROM orders o
    JOIN clients c ON o.client_id = c.id
    JOIN cars car ON o.car_id = car.id
    LEFT JOIN containers cont ON o.container_id = cont.id
    WHERE $order_where
    ORDER BY o.order_date DESC
    LIMIT ? OFFSET ?
");
$execOrderParams = array_merge($order_params, [$orders_per_page, $orders_offset]);
$stmt->execute($execOrderParams);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients & Orders - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="custom_style.css">
    <style>
        /* Remove vertical scrolling for the clients table specifically (show up to 20 rows without internal scrollbar) */
        .table-responsive.no-scroll {
            max-height: none !important;
            overflow-y: visible !important;
        }
        .client-row {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .client-row:hover {
            background-color: #f8f9fa;
        }
        .client-row.expanded {
            background-color: #e7f3ff;
        }
        .expand-icon {
            display: inline-block;
            transition: transform 0.2s ease;
            width: 20px;
        }
        .expand-icon.expanded {
            transform: rotate(90deg);
        }
        /* Normalize expandable order-details area to a single consistent palette */
        .order-details-row {
            display: none;
        }
        .order-details-row.show {
            display: table-row;
        }
        /* Wrapper cell uses the unified blue background */
        .order-details-row td {
            background-color: #e8f4f8 !important;
            padding: 0.5rem !important;
            border-top: none !important;
        }
        /* Content sits on top of the wrapper background; make inner containers transparent so the wrapper color shows through */
        .order-details-content {
            padding: 0.5rem 0;
            background-color: transparent !important;
            border-left: 4px solid #0d6efd;
            border-radius: 0.25rem;
        }
        .order-details-content .table-responsive,
        .order-details-content .table {
            margin-bottom: 0;
            background-color: transparent !important;
        }
        /* Keep the header slightly darker for contrast */
        .order-details-content .table thead {
            background-color: #d3e8f3 !important;
            border-bottom: 2px solid #0d6efd;
        }
        .order-details-content .table thead th {
            color: #004085;
            font-weight: 600;
            background-color: #d3e8f3 !important;
        }
        /* Make body rows transparent so wrapper blue is visible; hover still slightly darker */
        .order-details-content .table tbody tr {
            border-bottom: 1px solid #c5dce8;
            background-color: transparent !important;
        }
        .order-details-content .table tbody td,
        .order-details-content .table tbody th {
            background-color: transparent !important;
        }
        .order-details-content .table tbody tr:hover {
            background-color: #d3e8f3 !important;
        }
        
        /* Ensure orders table shows at least 20 rows without scrollbar */
        #orders-content .card-body {
            overflow: visible;
        }
        
        #orders-content .table-responsive {
            max-height: none;
            overflow-y: visible;
        }
        
        /* Adjust table row height for better visibility */
        .table th, .table td {
            padding: 0.75rem;
        }
        
        /* Checkbox styling */
        .select-all-checkbox, .select-checkbox {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-people-fill"></i> Clients & Orders</h1>
                    <a href="add_client_and_order.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add Client & Order
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'clients' ? 'active' : ''; ?>" 
                                id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients-content" type="button">
                            <i class="bi bi-people"></i> Clients (<?php echo count($clients); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'orders' ? 'active' : ''; ?>" 
                                id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-content" type="button">
                            <i class="bi bi-cart-check"></i> Orders (<?php echo count($orders); ?>)
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content">
                    
                    <!-- CLIENTS TAB -->
                    <div class="tab-pane fade <?php echo $active_tab === 'clients' ? 'show active' : ''; ?>" id="clients-content">
                        <!-- Clients Search -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <input type="hidden" name="tab" value="clients">
                                    <div class="col-md-10">
                                        <input type="text" class="form-control" name="client_search" placeholder="Search by name, email, phone, client ID, or passport number..." value="<?php echo htmlspecialchars($client_search); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="bi bi-search"></i> Search
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <form method="POST" id="bulk-client-actions">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <button type="submit" name="delete_selected_clients" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete the selected clients? Clients with orders cannot be deleted.')">
                                        <i class="bi bi-trash"></i> Delete Selected
                                    </button>
                                </div>
                            </div>
                        
                        <!-- Clients Table -->
                        <div class="card">
                            <div class="card-header">
                                <h5>All Clients (<?php echo count($clients); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive no-scroll">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>
                                                    <input type="checkbox" class="select-all-checkbox" onclick="toggleAllClients(this)">
                                                </th>
                                                <th>Client ID</th>
                                                <th>Name</th>
                                                <th>Phone</th>
                                                <th>Email</th>
                                                <th>National ID</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($clients)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">No clients found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($clients as $client): ?>
                                                <tr class="client-row" id="client-row-<?php echo $client['id']; ?>">
                                                    <td>
                                                        <input type="checkbox" class="select-checkbox" name="selected_clients[]" value="<?php echo $client['id']; ?>">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($client['client_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                                    <td><?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($client['passport_number']); ?></td>
                                                    <td>
                                                        <a href="edit_client.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="client_details.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($client['total_orders'] == 0): ?>
                                                        <a href="?delete_client=1&client_id=<?php echo $client['id']; ?>&tab=clients" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                        <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" title="Cannot delete - client has orders" disabled>
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <!-- Client Orders (Expandable) -->
                                                <tr class="order-details-row" id="details-<?php echo $client['id']; ?>">
                                                    <td colspan="7">
                                                        <div class="order-details-content">
                                                            <h6>Orders for <?php echo htmlspecialchars($client['name']); ?>:</h6>
                                                            <?php if (empty($client_orders[$client['id']])): ?>
                                                                <p class="text-muted">No orders found for this client.</p>
                                                            <?php else: ?>
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Order ID</th>
                                                                                <th>Car</th>
                                                                                <th>VIN</th>
                                                                                <th>Order Date</th>
                                                                                <th>Total Price</th>
                                                                                <th>Status</th>
                                                                                <th>Container</th>
                                                                                <th>Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($client_orders[$client['id']] as $order): ?>
                                                                            <?php $modelName = $order['brand'] . ' ' . $order['model'] . ' ' . $order['year'] . ($order['trim'] ? ' ' . $order['trim'] : ''); ?>
                                                                            <tr>
                                                                                <td>
                                                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>">
                                                                                        <?php echo htmlspecialchars($order['order_id']); ?>
                                                                                    </a>
                                                                                </td>
                                                                                <td><?php echo htmlspecialchars($modelName); ?></td>
                                                                                <td><code><?php echo htmlspecialchars($order['vin'] ?? '-'); ?></code></td>
                                                                                <td><?php echo formatDate($order['order_date']); ?></td>
                                                                                <td><?php echo formatCurrency($order['total_sale_price']); ?></td>
                                                                                <td>
                                                                                    <span class="badge bg-<?php 
                                                                                        echo $order['shipping_status'] === 'Awaiting Container' ? 'warning text-dark' : 
                                                                                            ($order['shipping_status'] === 'Shipped on Container' ? 'info' : 
                                                                                            ($order['shipping_status'] === 'Arrived in Algeria' ? 'success' : 
                                                                                            ($order['shipping_status'] === 'Customs Cleared' ? 'secondary' : 
                                                                                            ($order['shipping_status'] === 'Ready for Pickup' ? 'primary' : 
                                                                                            ($order['shipping_status'] === 'Delivered' ? 'success' : 'secondary'))))); 
                                                                                    ?>">
                                                                                        <?php echo htmlspecialchars($order['shipping_status']); ?>
                                                                                    </span>
                                                                                </td>
                                                                                <td>
                                                                                    <?php if ($order['container_code']): ?>
                                                                                        <a href="container_details.php?id=<?php echo $order['container_id']; ?>">
                                                                                            <?php echo htmlspecialchars($order['container_code']); ?>
                                                                                        </a>
                                                                                    <?php else: ?>
                                                                                        <span class="text-muted">-</span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                                                        <i class="bi bi-eye"></i>
                                                                                    </a>
                                                                                    <a href="?delete_order=1&order_id=<?php echo $order['id']; ?>&tab=clients" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure?')">
                                                                                        <i class="bi bi-trash"></i>
                                                                                    </a>
                                                                                </td>
                                                                            </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                </form>

                                <?php
                                    $clients_start = $clients_total ? $clients_offset + 1 : 0;
                                    $clients_end = $clients_total ? ($clients_offset + count($clients)) : 0;
                                ?>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">Showing <?php echo $clients_start; ?>–<?php echo $clients_end; ?> of <?php echo $clients_total; ?> clients</small>
                                    <?php if ($clients_total_pages > 1): ?>
                                    <nav aria-label="Clients pagination">
                                        <ul class="pagination mb-0">
                                            <?php
                                                $baseParams = $_GET;
                                                $baseParams['tab'] = 'clients';
                                                for ($p = 1; $p <= $clients_total_pages; $p++):
                                                    $baseParams['clients_page'] = $p;
                                                    $qs = http_build_query($baseParams);
                                            ?>
                                            <li class="page-item <?php echo $p == $clients_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo $qs; ?>"><?php echo $p; ?></a>
                                            </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                    
                    <!-- ORDERS TAB -->
                    <div class="tab-pane fade <?php echo $active_tab === 'orders' ? 'show active' : ''; ?>" id="orders-content">
                        <!-- Orders Filters -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <input type="hidden" name="tab" value="orders">
                                    <div class="col-md-7">
                                        <input type="text" class="form-control" name="order_search" placeholder="Search by order ID, client name, or brand..." value="<?php echo htmlspecialchars($order_search); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="status">
                                            <option value="">All Statuses</option>
                                            <option value="Awaiting Container" <?php echo $status_filter === 'Awaiting Container' ? 'selected' : ''; ?>>Awaiting Container</option>
                                            <option value="Shipped on Container" <?php echo $status_filter === 'Shipped on Container' ? 'selected' : ''; ?>>Shipped on Container</option>
                                            <option value="Arrived in Algeria" <?php echo $status_filter === 'Arrived in Algeria' ? 'selected' : ''; ?>>Arrived in Algeria</option>
                                            <option value="Customs Cleared" <?php echo $status_filter === 'Customs Cleared' ? 'selected' : ''; ?>>Customs Cleared</option>
                                            <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                            <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <form method="POST" id="bulk-order-actions">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <button type="submit" name="delete_selected_orders" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete the selected orders?')">
                                        <i class="bi bi-trash"></i> Delete Selected
                                    </button>
                                </div>
                            </div>
                        
                        <!-- Orders Table -->
                        <div class="card">
                            <div class="card-header">
                                <h5>All Orders (<?php echo count($orders); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>
                                                    <input type="checkbox" class="select-all-checkbox" onclick="toggleAllOrders(this)">
                                                </th>
                                                <th>Order ID</th>
                                                <th>Client</th>
                                                <th>Car Model</th>
                                                <th>VIN</th>
                                                <th>Order Date</th>
                                                <th>Total Price</th>
                                                <th>Paid</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                                <th>Container</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($orders)): ?>
                                                <tr>
                                                    <td colspan="12" class="text-center text-muted">No orders found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($orders as $order): ?>
                                                <?php $balance = $order['total_sale_price'] - $order['total_paid']; ?>
                                                <?php $modelName = $order['brand'] . ' ' . $order['model'] . ' ' . $order['year'] . ($order['trim'] ? ' ' . $order['trim'] : ''); ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="select-checkbox" name="selected_orders[]" value="<?php echo $order['id']; ?>">
                                                    </td>
                                                    <td><a href="order_details.php?id=<?php echo $order['id']; ?>"><?php echo htmlspecialchars($order['order_id']); ?></a></td>
                                                    <td><?php echo htmlspecialchars($order['client_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($modelName); ?></td>
                                                    <td><code><?php echo htmlspecialchars($order['vin'] ?? '-'); ?></code></td>
                                                    <td><?php echo formatDate($order['order_date']); ?></td>
                                                    <td><?php echo formatCurrency($order['total_sale_price']); ?></td>
                                                    <td><?php echo formatCurrency($order['total_paid']); ?></td>
                                                    <td>
                                                        <span class="<?php echo $balance > 0 ? 'text-warning fw-bold' : 'text-success'; ?>">
                                                            <?php echo formatCurrency($balance); ?>
                                                        </span>
                                                    </td>
                                                    <td>
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
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php echo $order['shipping_status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($order['container_code']): ?>
                                                            <a href="container_details.php?id=<?php echo $order['container_id']; ?>">
                                                                <?php echo htmlspecialchars($order['container_code']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="?delete_order=1&order_id=<?php echo $order['id']; ?>&tab=orders" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                </form>
                                    <?php
                                        $orders_start = $orders_total ? $orders_offset + 1 : 0;
                                        $orders_end = $orders_total ? ($orders_offset + count($orders)) : 0;
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">Showing <?php echo $orders_start; ?>–<?php echo $orders_end; ?> of <?php echo $orders_total; ?> orders</small>
                                        <?php if ($orders_total_pages > 1): ?>
                                        <nav aria-label="Orders pagination">
                                            <ul class="pagination mb-0">
                                                <?php
                                                    $baseParams = $_GET;
                                                    $baseParams['tab'] = 'orders';
                                                    for ($p = 1; $p <= $orders_total_pages; $p++):
                                                        $baseParams['orders_page'] = $p;
                                                        $qs = http_build_query($baseParams);
                                                ?>
                                                <li class="page-item <?php echo $p == $orders_page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?<?php echo $qs; ?>"><?php echo $p; ?></a>
                                                </li>
                                                <?php endfor; ?>
                                            </ul>
                                        </nav>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                    </div>
                    
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleClientDetails(clientId) {
            const detailsRow = document.getElementById('details-' + clientId);
            const expandIcon = document.getElementById('expand-' + clientId);
            const clientRow = detailsRow.previousElementSibling;
            
            if (detailsRow.classList.contains('show')) {
                detailsRow.classList.remove('show');
                expandIcon.classList.remove('expanded');
                clientRow.classList.remove('expanded');
            } else {
                detailsRow.classList.add('show');
                expandIcon.classList.add('expanded');
                clientRow.classList.add('expanded');
            }
        }
        
        function toggleAllClients(source) {
            const checkboxes = document.querySelectorAll('.select-checkbox[name="selected_clients[]"]');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
        
        function toggleAllOrders(source) {
            const checkboxes = document.querySelectorAll('.select-checkbox[name="selected_orders[]"]');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</body>
</html>