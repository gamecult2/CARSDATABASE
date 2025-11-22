<?php
/**
 * Reset Admin Password Script
 * Car Dealership Client and Logistics Management System
 */

require_once 'config.php';

// New password (change this to your desired password)
$new_password = 'admin123';

// Hash the password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    // Update admin password
    $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
    $result = $stmt->execute([$hashed_password]);
    
    if ($result) {
        echo "Admin password successfully reset to: " . $new_password . "\n";
        echo "You can now login with username 'admin' and this password.\n";
    } else {
        echo "Error updating password.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>