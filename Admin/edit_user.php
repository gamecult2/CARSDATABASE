<?php
/**
 * Edit User
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: users.php');
    exit;
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setAlert('danger', 'User not found');
    header('Location: users.php');
    exit;
}

// Prevent editing yourself
if ($id == $_SESSION['user_id']) {
    setAlert('danger', 'You cannot edit your own account. Use profile settings instead.');
    header('Location: users.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $role = sanitize($_POST['role'] ?? 'sales_agent');
    $full_name = sanitize($_POST['full_name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate role
    if (!in_array($role, ['admin', 'sales_agent', 'moderator'])) {
        $error = 'Invalid role selected';
    } else if (empty($username) || empty($email) || empty($full_name)) {
        $error = 'Please fill all required fields';
    } else {
        // Check if username or email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $id]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            // Update user - no password update here as it's handled separately
            $stmt = $pdo->prepare("
                UPDATE admin_users 
                SET username = ?, email = ?, role = ?, full_name = ?, is_active = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$username, $email, $role, $full_name, $is_active, $id])) {
                setAlert('success', 'User updated successfully');
                header('Location: users.php');
                exit;
            } else {
                $error = 'Error updating user';
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
    <title>Edit User - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-person-plus"></i> Edit User</h1>
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
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="sales_agent" <?php echo $user['role'] === 'sales_agent' ? 'selected' : ''; ?>>Sales Agent</option>
                                        <option value="moderator" <?php echo $user['role'] === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="is_active" class="form-label">Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update User
                            </button>
                            <a href="users.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="bi bi-shield-lock"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <a href="change_password.php?user_id=<?php echo $user['id']; ?>" class="btn btn-warning">
                            <i class="bi bi-key"></i> Change Password
                        </a>
                        <small class="text-muted d-block mt-2">Only administrators can change user passwords</small>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

