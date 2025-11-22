<?php
require_once 'config.php';

try {
    // Check all cars in the database
    $stmt = $pdo->query("
        SELECT id, brand, model, year, trim 
        FROM cars 
        ORDER BY brand, model
    ");
    
    echo "Cars in database:\n";
    echo "ID\tBrand\t\tModel\t\tYear\tTrim\n";
    echo "--\t-----\t\t-----\t\t----\t----\n";
    while ($row = $stmt->fetch()) {
        echo $row['id'] . "\t" . $row['brand'] . "\t\t" . $row['model'] . "\t\t" . $row['year'] . "\t" . $row['trim'] . "\n";
    }
    
    // Check total number of cars
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cars");
    $count = $stmt->fetch();
    echo "\nTotal cars: " . $count['count'] . "\n";
    
    // Check container_cars with car details
    $stmt = $pdo->query("
        SELECT cc.container_id, c.container_id as container_code, car.brand, car.model, car.trim
        FROM container_cars cc
        JOIN containers c ON cc.container_id = c.id
        JOIN cars car ON cc.car_id = car.id
        ORDER BY c.container_id, car.brand, car.model
    ");
    
    echo "\nCars assigned to containers:\n";
    echo "Container\tCar\n";
    echo "---------\t---\n";
    while ($row = $stmt->fetch()) {
        echo $row['container_code'] . "\t\t" . $row['brand'] . " " . $row['model'] . " (" . $row['trim'] . ")\n";
    }
    
    // Check container details
    $stmt = $pdo->query("
        SELECT c.container_id, COUNT(cc.id) as car_count, c.container_shipping_cost
        FROM containers c 
        LEFT JOIN container_cars cc ON c.id = cc.container_id 
        GROUP BY c.id, c.container_id, c.container_shipping_cost
        ORDER BY c.container_id
    ");
    
    echo "\nContainer details:\n";
    echo "Container\tCars\tShipping Cost\n";
    echo "---------\t----\t-------------\n";
    while ($row = $stmt->fetch()) {
        echo $row['container_id'] . "\t\t" . $row['car_count'] . "\t$" . number_format($row['container_shipping_cost'], 2) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>