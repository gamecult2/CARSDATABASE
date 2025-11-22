<?php
/**
 * Clients Management
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole(['admin', 'sales_agent', 'moderator']);

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
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
    
    header('Location: clients.php');
    exit;
}

// Get clients
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM orders WHERE client_id = c.id) as order_count,
           u.username as linked_user
    FROM clients c
    LEFT JOIN admin_users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
");
$clients = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-people"></i> Clients Management</h1>
                    <a href="add_client.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Client
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Clients Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Clients (<?php echo count($clients); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Client ID</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Passport</th>
                                        <th>Orders</th>
                                        <th>Linked User</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">No clients found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($client['client_id']); ?></td>
                                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                                            <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($client['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($client['passport_number']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $client['order_count'] > 0 ? 'primary' : 'secondary'; ?>">
                                                    <?php echo $client['order_count']; ?> Orders
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($client['linked_user']): ?>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($client['linked_user']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($client['created_at']); ?></td>
                                            <td>
                                                <a href="client_details.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit_client.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?delete=1&id=<?php echo $client['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure? This will delete all client data.')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
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

