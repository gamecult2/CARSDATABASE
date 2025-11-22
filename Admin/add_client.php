<?php
/**
 * Add New Client
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole(['admin', 'sales_agent']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $passport_number = sanitize($_POST['passport_number'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $create_user_account = isset($_POST['create_user_account']);
    $user_password = $_POST['user_password'] ?? '';
    
    if (empty($name) || empty($phone)) {
        $error = 'Name and phone are required';
    } elseif ($create_user_account && empty($user_password)) {
        $error = 'Password is required when creating user account';
    } else {
        $pdo->beginTransaction();
        try {
            $client_id = generateUniqueID('CLI', 'clients', 'client_id');
            
            // Create client
            $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO clients (client_id, name, phone, email, passport_number, address, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$client_id, $name, $phone, $email, $passport_number, $address, $hashed_password])) {
                $pdo->commit();
                setAlert('success', 'Client added successfully with portal access (Password is the Passport Number)');
                header('Location: clients.php');
                exit;
            } else {
                throw new Exception('Error adding client');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error adding client: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Client - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-person-plus"></i> Add New Client</h1>
                    <a href="clients.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Clients
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
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="phone" name="phone" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="passport_number" class="form-label">Passport Number</label>
                                    <input type="text" class="form-control" id="passport_number" name="passport_number">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                            </div>
                            
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5><i class="bi bi-key"></i> Client Portal Access</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="create_user_account" name="create_user_account" checked>
                                        <label class="form-check-label" for="create_user_account">
                                            Create client portal account
                                        </label>
                                    </div>
                                    <div id="password_fields">
                                        <div class="mb-3">
                                            <label for="user_password" class="form-label">Password <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="user_password" name="user_password" value="<?php echo htmlspecialchars($passport_number); ?>">
                                            <div class="form-text">By default, the passport number will be used as the password</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Client
                                </button>
                                <a href="clients.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const createUserCheckbox = document.getElementById('create_user_account');
            const passwordFields = document.getElementById('password_fields');
            
            createUserCheckbox.addEventListener('change', function() {
                passwordFields.style.display = this.checked ? 'block' : 'none';
            });
            
            // Initialize
            passwordFields.style.display = createUserCheckbox.checked ? 'block' : 'none';
        });
    </script>
</body>
</html>