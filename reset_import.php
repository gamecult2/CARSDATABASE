<?php
require_once 'config.php';

try {
    $pdo->beginTransaction();
    
    // Delete in correct order to handle foreign key constraints
    $stmt = $pdo->prepare("DELETE FROM container_cars");
    $stmt->execute();
    
    $stmt = $pdo->prepare("DELETE FROM payments");
    $stmt->execute();
    
    $stmt = $pdo->prepare("DELETE FROM orders");
    $stmt->execute();
    
    $stmt = $pdo->prepare("DELETE FROM clients WHERE client_id LIKE 'CLI%'");
    $stmt->execute();
    
    $stmt = $pdo->prepare("DELETE FROM cars WHERE brand IN ('Geely Coolray', 'Livan X3Pro', 'VW T-Roc', 'GAC GS3')");
    $stmt->execute();
    
    $stmt = $pdo->prepare("DELETE FROM containers WHERE container_id LIKE 'CONT%'");
    $stmt->execute();
    
    $pdo->commit();
    
    echo "Data reset successfully.\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error resetting data: " . $e->getMessage() . "\n";
}
?>