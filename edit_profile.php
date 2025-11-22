<?php
/**
 * Client Profile Edit
 * Car Dealership Client and Logistics Management System
 */

require_once 'config.php';
require_once 'functions.php';

requireLogin();

// Only clients can access
if ($_SESSION['role'] !== 'client') {
    header('Location: Admin/index.php');
    exit;
}

// Get client ID - directly from session for clients
$client_id = $_SESSION['user_id'];

// Get client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    setAlert('danger', 'Client profile not found');
    header('Location: logout.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($phone)) {
        setAlert('danger', 'Name and phone are required');
    } else {
        // Update client info
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET name = ?, phone = ?, email = ?, address = ? 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $phone, $email, $address, $client_id])) {
            setAlert('success', 'Profile updated successfully');
            // Refresh client data
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();
        } else {
            setAlert('danger', 'Error updating profile');
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar navbar-light bg-white mb-4 rounded shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="client_portal.php">
                    <i class="bi bi-car-front"></i> <?php echo APP_NAME; ?>
                </a>
                <a href="client_portal.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Portal
                </a>
            </div>
        </nav>
        
        <?php displayAlert(); ?>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-person"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="client_id" class="form-label">Client ID</label>
                                <input type="text" class="form-control" id="client_id" value="<?php echo htmlspecialchars($client['client_id']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="passport_number" class="form-label">Passport Number</label>
                                <input type="text" class="form-control" id="passport_number" value="<?php echo htmlspecialchars($client['passport_number']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone *</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="client_portal.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>