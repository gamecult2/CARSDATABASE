<?php
/**
 * Users Management
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Don't allow deleting yourself
    if ($id == $_SESSION['user_id']) {
        setAlert('danger', 'You cannot delete your own account');
    } else {
        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        if ($stmt->execute([$id])) {
            setAlert('success', 'User deleted successfully');
        } else {
            setAlert('danger', 'Error deleting user');
        }
    }
    header('Location: users.php');
    exit;
}

// Get users
$stmt = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM clients WHERE user_id = u.id) as client_count
    FROM admin_users u
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-person-gear"></i> Users Management</h1>
                    <a href="add_user.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New User
                    </a>
                </div>
                
                <?php displayAlert(); ?>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Users (<?php echo count($users); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Full Name</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Client Profile</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No users found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'sales_agent' ? 'primary' : 'info'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></td>
                                            <td>
                                                <?php if ($user['client_count'] > 0): ?>
                                                    <span class="badge bg-info">Linked</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="?delete=1&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Current User</span>
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