<?php
/**
 * Add New User
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'sales_agent');
    $full_name = sanitize($_POST['full_name'] ?? '');
    
    // Validate role - only allow admin roles, not client
    if (!in_array($role, ['admin', 'sales_agent', 'moderator'])) {
        $error = 'Invalid role selected';
    } else if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill all required fields';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO admin_users (username, email, password, role, full_name)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $email, $hashed_password, $role, $full_name])) {
                setAlert('success', 'User added successfully');
                header('Location: users.php');
                exit;
            } else {
                $error = 'Error adding user';
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
    <title>Add User - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-person-plus"></i> Add New User</h1>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Users
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
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="sales_agent">Sales Agent</option>
                                        <option value="moderator">Moderator</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save User
                            </button>
                            <a href="users.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

