<?php
/**
 * Edit Client
 */

require_once '../config.php';
require_once '../functions.php';

requireLogin();
requireRole(['admin', 'sales_agent']);

$error = '';

// Get client ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: clients.php');
    exit;
}

// Get client data
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    setAlert('danger', 'Client not found');
    header('Location: clients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $passport_number = sanitize($_POST['passport_number'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($phone)) {
        $error = 'Name and phone are required';
    } else {
        $pdo->beginTransaction();
        try {
            // Update client
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE clients 
                    SET name = ?, phone = ?, email = ?, passport_number = ?, address = ?, password = ?
                    WHERE id = ?
                ");
                $result = $stmt->execute([$name, $phone, $email, $passport_number, $address, $hashed_password, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE clients 
                    SET name = ?, phone = ?, email = ?, passport_number = ?, address = ?
                    WHERE id = ?
                ");
                $result = $stmt->execute([$name, $phone, $email, $passport_number, $address, $id]);
            }
            
            if ($result) {
                $pdo->commit();
                setAlert('success', 'Client updated successfully');
                header('Location: clients.php');
                exit;
            } else {
                throw new Exception('Error updating client');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating client: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2"><i class="bi bi-person-plus"></i> Edit Client</h1>
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
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="passport_number" class="form-label">Passport Number</label>
                                    <input type="text" class="form-control" id="passport_number" name="passport_number" value="<?php echo htmlspecialchars($client['passport_number'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5><i class="bi bi-key"></i> Change Password</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">New Password</label>
                                        <input type="text" class="form-control" id="password" name="password">
                                        <div class="form-text">Leave blank to keep current password</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Client
                                </button>
                                <a href="clients.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>