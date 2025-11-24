<?php
/**
 * Import Client, Car and Order Data
 * 
 * This script imports the provided client list with their cars and orders
 */

require_once 'config.php';
require_once 'functions.php';

// Data to import
$data = [
    // Row 1-4
    ['name' => 'ABDELLATIF SAMI', 'car' => 'Geely Coolray', 'vin' => 'LB37622Z5SX620329', 'passport' => '314360369', 'price' => 9750, 'shipping' => 1225],
    ['name' => 'LAADJ ABDELHAK', 'car' => 'Livan X3Pro', 'vin' => 'LLV2C3B22S0021144', 'passport' => '305531859', 'price' => 7500, 'shipping' => 1225],
    ['name' => 'MREZIGUE EL ALIA', 'car' => 'Livan X3Pro', 'vin' => 'LLV2C3B27S0021138', 'passport' => '314740447', 'price' => 7500, 'shipping' => 1225],
    ['name' => 'SAIFI HANI', 'car' => 'Livan X3Pro', 'vin' => 'LLV2C3B21S0021121', 'passport' => '168926916', 'price' => 7500, 'shipping' => 1225],
    
    // Row 5-8
    ['name' => 'BAHAR SAMI', 'car' => 'VW T-Roc', 'vin' => 'LFV2B2A15S5531781', 'passport' => '187258999', 'price' => 17550, 'shipping' => 1375],
    ['name' => 'HAMDI MABROUK', 'car' => 'VW T-Roc', 'vin' => 'LFV2B2A1XS5531954', 'passport' => '308665082', 'price' => 17550, 'shipping' => 1375],
    ['name' => 'BENSAID MEHDI', 'car' => 'Livan X3Pro', 'vin' => 'LLV2C3B26S0016478', 'passport' => '192066236', 'price' => 7500, 'shipping' => 1375],
    ['name' => 'MESSAADIA AMAR', 'car' => 'Livan X3Pro', 'vin' => 'LLV2C3B20S0019893', 'passport' => '303241961', 'price' => 7500, 'shipping' => 1375],
    
    // Row 9-12
    ['name' => 'LAOUAMRI YACINE', 'car' => 'VW T-Roc', 'vin' => '', 'passport' => '308973674', 'price' => 17550, 'shipping' => 1375],
    ['name' => 'BOUSEFSAF NOUR EL ISLAM', 'car' => 'Geely Coolray', 'vin' => '', 'passport' => '314931020', 'price' => 11770, 'shipping' => 1375],
    ['name' => 'DEKKOUMI SEIF EDDINE', 'car' => 'Livan X3Pro', 'vin' => '', 'passport' => '306762021', 'price' => 7500, 'shipping' => 1375],
    ['name' => 'HACHELEF ABDELOUADOUD', 'car' => 'Geely Coolray', 'vin' => '', 'passport' => '197551180', 'price' => 11770, 'shipping' => 1375],
    
    // Row 13-15
    ['name' => 'ABDESSAMIA SAMIA', 'car' => 'GAC GS3', 'vin' => '', 'passport' => '176676280', 'price' => 9735, 'shipping' => 1400],
    ['name' => 'KRACHE SARRA', 'car' => 'GAC GS3', 'vin' => '', 'passport' => '168742086', 'price' => 9735, 'shipping' => 1400],
    ['name' => 'KRIDISSE LYDIA', 'car' => 'Livan X3Pro', 'vin' => '', 'passport' => '314240496', 'price' => 7400, 'shipping' => 1400],
    
    // Row 17-19
    ['name' => 'DJAHNIT ADLEN MAHDI', 'car' => 'Livan X3Pro', 'vin' => '', 'passport' => '305780016', 'price' => 7500, 'shipping' => 1375],
    ['name' => 'HARKATI AHLEM', 'car' => 'Livan X3Pro', 'vin' => '', 'passport' => '168941812', 'price' => 7500, 'shipping' => 1375],
    ['name' => 'BAHAR AHMED', 'car' => 'GAC GS3', 'vin' => '', 'passport' => '177626239', 'price' => 9800, 'shipping' => 1375]
];

// Generate random phone numbers and addresses
function generateRandomPhone() {
    return '0' . rand(10000000, 99999999);
}

function generateRandomAddress() {
    $streets = ['Rue de la Paix', 'Avenue des Champs-Elysées', 'Boulevard Saint-Germain', 'Rue de Rivoli', 'Avenue Montaigne'];
    $cities = ['Alger', 'Oran', 'Constantine', 'Tlemcen', 'Annaba'];
    $street = $streets[array_rand($streets)];
    $city = $cities[array_rand($cities)];
    return "$street, $city";
}

// Process data
try {
    // Create car_expenses table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `car_expenses` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `car_id` INT(11) NOT NULL,
          `expense_type` VARCHAR(100) NOT NULL,
          `amount` DECIMAL(10,2) NOT NULL,
          `expense_date` DATE NOT NULL,
          `description` TEXT,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          FOREIGN KEY (`car_id`) REFERENCES `cars`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->beginTransaction();
    
    // Create containers for each group of 4 cars
    $container_count = ceil(count($data) / 4);
    $container_ids = []; // Store actual database IDs
    $container_shipping_costs = [];
    $container_codes = []; // Store container codes like CONT001
    
    // Insert containers first
    for ($i = 0; $i < $container_count; $i++) {
        $container_code = 'CONT' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
        $container_codes[] = $container_code;
        
        $stmt = $pdo->prepare("INSERT INTO containers (container_id, departure_port, arrival_port, departure_date, estimated_arrival_date, container_shipping_cost, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $container_code,
            'China Port',
            'Algeria Port',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+30 days')),
            0, // Will be updated with actual cost
            'Scheduled'
        ]);
        
        // Store the actual database ID
        $container_ids[] = $pdo->lastInsertId();
        $container_shipping_costs[$container_ids[$i]] = 0;
    }
    
    // Insert clients, cars, orders, and container_cars
    $client_ids = [];
    $car_ids = [];
    $order_ids = [];
    
    foreach ($data as $index => $row) {
        // Create a unique car for each entry by adding a serial number to the model
        $car_brand = $row['car'];
        $car_model = $row['car'] . ' #' . str_pad($index + 1, 3, '0', STR_PAD_LEFT); // Make each car model unique
        
        // Always create a new unique car
        $stmt = $pdo->prepare("INSERT INTO cars (brand, model, year, trim, purchase_price_usd, sale_price_usd, status, color, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $car_brand,
            $car_model, // Unique model name
            2023,
            '', // Empty trim
            0,
            $row['price'],
            'Available',
            '',
            ''
        ]);
        $car_id = $pdo->lastInsertId();
        
        // Get or create client
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE passport_number = ?");
        $stmt->execute([$row['passport']]);
        $client = $stmt->fetch();
        
        if (!$client) {
            // Create new client
            $stmt = $pdo->prepare("INSERT INTO clients (client_id, name, phone, email, passport_number, address, password, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                'CLI' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                $row['name'],
                generateRandomPhone(),
                strtolower(str_replace(' ', '.', $row['name'])) . '@example.com',
                $row['passport'],
                generateRandomAddress(),
                password_hash($row['passport'], PASSWORD_DEFAULT)
            ]);
            $client_id = $pdo->lastInsertId();
        } else {
            $client_id = $client['id'];
        }
        
        // Create order
        $stmt = $pdo->prepare("INSERT INTO orders (order_id, client_id, car_id, vin, total_sale_price, shipping_status, order_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'ORD' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
            $client_id,
            $car_id,
            $row['vin'],
            $row['price'],
            'Awaiting Container',
            date('Y-m-d'),
            ''
        ]);
        $order_id = $pdo->lastInsertId();
        
        // Assign to container
        $container_index = floor($index / 4);
        $container_db_id = $container_ids[$container_index]; // Use actual database ID
        
        // Add shipping cost to container total
        $container_shipping_costs[$container_db_id] += $row['shipping'];
        
        // Add to container (no need to check for duplicates since each car is unique)
        $stmt = $pdo->prepare("INSERT INTO container_cars (container_id, car_id, order_id, loaded_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $container_db_id, // Use actual database ID
            $car_id,
            $order_id
        ]);
        
        // Update order with container_id
        $stmt = $pdo->prepare("UPDATE orders SET container_id = ? WHERE id = ?");
        $stmt->execute([$container_db_id, $order_id]);
        
        // Update order shipping status
        $stmt = $pdo->prepare("UPDATE orders SET shipping_status = 'Shipped on Container' WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // Store IDs for reference
        $client_ids[] = $client_id;
        $car_ids[] = $car_id;
        $order_ids[] = $order_id;
    }
    
    // Update containers with their shipping costs
    foreach ($container_shipping_costs as $container_db_id => $shipping_cost) {
        $stmt = $pdo->prepare("UPDATE containers SET container_shipping_cost = ? WHERE id = ?");
        $stmt->execute([$shipping_cost, $container_db_id]);
    }
    
    $pdo->commit();
    echo "<h2>Data imported successfully!</h2>";
    echo "<p>Imported " . count($data) . " clients, cars, and orders.</p>";
    echo "<p>Created " . count($container_ids) . " containers.</p>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2>Error importing data</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>