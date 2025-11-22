<?php
require_once 'config.php';

try {
    // Check how many containers we have and how many cars in each
    $stmt = $pdo->query("
        SELECT c.container_id, COUNT(cc.id) as car_count 
        FROM containers c 
        LEFT JOIN container_cars cc ON c.id = cc.container_id 
        GROUP BY c.id, c.container_id
        ORDER BY c.container_id
    ");
    
    echo "Container car counts:\n";
    while ($row = $stmt->fetch()) {
        echo $row['container_id'] . ': ' . $row['car_count'] . " cars\n";
    }
    
    // Check total number of containers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM containers");
    $count = $stmt->fetch();
    echo "\nTotal containers: " . $count['count'] . "\n";
    
    // Check total number of container_cars entries
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM container_cars");
    $count = $stmt->fetch();
    echo "Total container_cars entries: " . $count['count'] . "\n";
    
    // Check if there are duplicate cars in containers
    $stmt = $pdo->query("
        SELECT container_id, car_id, COUNT(*) as count 
        FROM container_cars 
        GROUP BY container_id, car_id 
        HAVING COUNT(*) > 1
    ");
    
    $duplicates = $stmt->fetchAll();
    if (count($duplicates) > 0) {
        echo "\nDuplicate car entries found:\n";
        foreach ($duplicates as $dup) {
            echo "Container ID: " . $dup['container_id'] . ", Car ID: " . $dup['car_id'] . ", Count: " . $dup['count'] . "\n";
        }
    } else {
        echo "\nNo duplicate car entries found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>