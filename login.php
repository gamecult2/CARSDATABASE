<?php
/**
 * Login Page
 * Car Dealership Client and Logistics Management System
 */

require_once 'config.php';
require_once 'functions.php';

// Cleanup expired MFA codes periodically (1 in 100 requests)
if (rand(1, 100) == 1) {
    cleanupExpiredMFACodes();
}

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect = hasRole('admin') || hasRole('sales_agent') || hasRole('moderator') ? 'Admin/index.php' : 'client_portal.php';
    header('Location: ' . BASE_URL . '/' . $redirect);
    exit;
}

$error = '';
$login_type = $_GET['type'] ?? 'admin'; // 'admin' or 'client'
$mfa_required = false;
$mfa_user_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = sanitize($_POST['login_type'] ?? 'admin');
    $username = sanitize($_POST['username'] ?? '');
    $passport_number = sanitize($_POST['passport_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $mfa_code = sanitize($_POST['mfa_code'] ?? '');
    
    // Handle MFA verification
    if (isset($_POST['verify_mfa']) && !empty($mfa_code)) {
        $user_id = (int)($_SESSION['mfa_user_id'] ?? 0);
        if ($user_id) {
            if ($login_type === 'client') {
                // For clients, check in clients table
                $stmt = $pdo->prepare("SELECT * FROM mfa_codes WHERE user_id = ? AND code = ? AND used = 0 AND expires_at > NOW()");
                $stmt->execute([$user_id, $mfa_code]);
                $mfa_record = $stmt->fetch();
                
                if ($mfa_record) {
                    // Mark MFA code as used
                    $stmt = $pdo->prepare("UPDATE mfa_codes SET used = 1 WHERE id = ?");
                    $stmt->execute([$mfa_record['id']]);
                    
                    // Get client and complete login
                    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $client = $stmt->fetch();
                    
                    if ($client) {
                        $_SESSION['user_id'] = $client['id'];
                        $_SESSION['username'] = $client['passport_number'];
                        $_SESSION['role'] = 'client';
                        $_SESSION['full_name'] = $client['name'];
                        $_SESSION['client_id'] = $client['id'];
                        unset($_SESSION['mfa_user_id']);
                        
                        $stmt = $pdo->prepare("UPDATE clients SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$client['id']]);
                        
                        header('Location: ' . BASE_URL . '/client_portal.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid or expired verification code';
                }
            } else {
                // For admin users, check in admin_users table
                $stmt = $pdo->prepare("SELECT * FROM mfa_codes WHERE user_id = ? AND code = ? AND used = 0 AND expires_at > NOW()");
                $stmt->execute([$user_id, $mfa_code]);
                $mfa_record = $stmt->fetch();
                
                if ($mfa_record) {
                    // Mark MFA code as used
                    $stmt = $pdo->prepare("UPDATE mfa_codes SET used = 1 WHERE id = ?");
                    $stmt->execute([$mfa_record['id']]);
                    
                    // Get user and complete login
                    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['full_name'] = $user['full_name'];
                        unset($_SESSION['mfa_user_id']);
                        
                        $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        header('Location: ' . BASE_URL . '/Admin/index.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid or expired verification code';
                }
            }
        }
    } else {
        // Regular login
        if ($login_type === 'client') {
            // Client login with National ID
            if (empty($passport_number) || empty($password)) {
                $error = 'Please enter Passport Number and password';
            } else {
                // Find client by National ID
                $stmt = $pdo->prepare("SELECT * FROM clients WHERE passport_number = ? AND is_active = 1");
                $stmt->execute([$passport_number]);
                $client = $stmt->fetch();
                
                if ($client && password_verify($password, $client['password'])) {
                    // Check if MFA is enabled
                    if ($client['mfa_enabled']) {
                        // Generate and send MFA code
                        $mfa_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        $stmt = $pdo->prepare("INSERT INTO mfa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$client['id'], $mfa_code, $expires_at]);
                        
                        // In production, send via SMS/Email. For now, we'll show it (remove in production!)
                        $_SESSION['mfa_user_id'] = $client['id'];
                        $_SESSION['mfa_code_display'] = $mfa_code; // Remove in production!
                        $mfa_required = true;
                    } else {
                        // No MFA, login directly
                        $_SESSION['user_id'] = $client['id'];
                        $_SESSION['username'] = $client['passport_number'];
                        $_SESSION['role'] = 'client';
                        $_SESSION['full_name'] = $client['name'];
                        $_SESSION['client_id'] = $client['id'];
                        
                        $stmt = $pdo->prepare("UPDATE clients SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$client['id']]);
                        
                        header('Location: ' . BASE_URL . '/client_portal.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid Passport Number or password';
                }
            }
        } else {
            // Admin/Staff login
            if (empty($username) || empty($password)) {
                $error = 'Please enter both username and password';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // Check MFA
                    if ($user['mfa_enabled']) {
                        $mfa_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        $stmt = $pdo->prepare("INSERT INTO mfa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$user['id'], $mfa_code, $expires_at]);
                        
                        $_SESSION['mfa_user_id'] = $user['id'];
                        $_SESSION['mfa_code_display'] = $mfa_code; // Remove in production!
                        $mfa_required = true;
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['full_name'] = $user['full_name'];
                        
                        $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        header('Location: ' . BASE_URL . '/Admin/index.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid username or password';
                }
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
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="bi bi-car-front"></i> Car Dealership</h2>
                <p class="mb-0">Management System</p>
            </div>
            <div class="card-body p-4">
                <h4 class="mb-4">Login</h4>
                
                <!-- Login Type Tabs -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link <?php echo $login_type === 'admin' ? 'active' : ''; ?>" 
                                onclick="document.getElementById('login_type').value='admin'; document.getElementById('adminForm').style.display='block'; document.getElementById('clientForm').style.display='none'; this.classList.add('active'); document.querySelector('.nav-link:last-child').classList.remove('active');">
                            Admin/Staff
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo $login_type === 'client' ? 'active' : ''; ?>" 
                                onclick="document.getElementById('login_type').value='client'; document.getElementById('clientForm').style.display='block'; document.getElementById('adminForm').style.display='none'; this.classList.add('active'); document.querySelector('.nav-link:first-child').classList.remove('active');">
                            Client Portal
                        </button>
                    </li>
                </ul>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($mfa_required): ?>
                    <!-- MFA Verification Form -->
                    <div class="alert alert-info">
                        <i class="bi bi-shield-check"></i> <strong>Two-Factor Authentication Required</strong><br>
                        <?php if (isset($_SESSION['mfa_code_display'])): ?>
                            <small>Verification code: <strong><?php echo $_SESSION['mfa_code_display']; ?></strong> (Remove in production!)</small>
                        <?php else: ?>
                            <small>A verification code has been sent to your registered contact method.</small>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="login_type" value="<?php echo htmlspecialchars($login_type); ?>">
                        <input type="hidden" name="verify_mfa" value="1">
                        <div class="mb-3">
                            <label for="mfa_code" class="form-label">Verification Code</label>
                            <input type="text" class="form-control" id="mfa_code" name="mfa_code" maxlength="6" required autofocus>
                            <small class="text-muted">Enter the 6-digit code</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-shield-check"></i> Verify
                        </button>
                        <a href="login.php" class="btn btn-secondary w-100">Cancel</a>
                    </form>
                <?php else: ?>
                    <!-- Admin/Staff Login Form -->
                    <form method="POST" action="" id="adminForm" style="display: <?php echo $login_type === 'admin' ? 'block' : 'none'; ?>;">
                        <input type="hidden" name="login_type" value="admin" id="login_type">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    </form>
                    
                    <!-- Client Login Form -->
                    <form method="POST" action="" id="clientForm" style="display: <?php echo $login_type === 'client' ? 'block' : 'none'; ?>;">
                        <input type="hidden" name="login_type" value="client">
                        <div class="mb-3">
                            <label for="passport_number" class="form-label">Passport Number</label>
                            <input type="text" class="form-control" id="passport_number" name="passport_number" required autofocus>
                            <small class="text-muted">Enter your Passport Number</small>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right"></i> Login to Client Portal
                        </button>
                    </form>
                    
                    <div class="text-center text-muted">
                        <small>Admin: admin / admin123</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>