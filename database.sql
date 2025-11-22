-- Car Dealership Client and Logistics Management System
-- Database Schema

-- Drop existing tables if they exist (for fresh installation)
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `container_cars`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `containers`;
DROP TABLE IF EXISTS `cars`;
DROP TABLE IF EXISTS `clients`;
-- First drop foreign key dependent tables
DROP TABLE IF EXISTS `mfa_codes`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `order_media`;
-- Then drop users table
DROP TABLE IF EXISTS `users`;

-- Administrative Users table (for admin and staff authentication)
CREATE TABLE `admin_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'sales_agent', 'moderator') DEFAULT 'sales_agent',
  `full_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `mfa_enabled` TINYINT(1) DEFAULT 0,
  `mfa_secret` VARCHAR(32) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clients table
CREATE TABLE `clients` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100),
  `passport_number` VARCHAR(50),
  `address` TEXT,
  `user_id` INT(11) NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) DEFAULT 1,
  `mfa_enabled` TINYINT(1) DEFAULT 0,
  `mfa_secret` VARCHAR(32) NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_client_id` (`client_id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_phone` (`phone`),
  INDEX `idx_passport` (`passport_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cars table (Inventory/Catalog) - Model-based only, no VINs
CREATE TABLE `cars` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `brand` VARCHAR(50) NOT NULL,
  `model` VARCHAR(100) NOT NULL,
  `year` INT(4) NOT NULL,
  `trim` VARCHAR(100),
  `purchase_price_usd` DECIMAL(12,2) NOT NULL,
  `sale_price_usd` DECIMAL(12,2) NOT NULL,
  `status` ENUM('Available', 'In Production', 'In Transit', 'Customs Cleared', 'Delivered', 'Sold', 'Reserved') DEFAULT 'Available',
  `color` VARCHAR(50),
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_model` (`brand`, `model`, `year`, `trim`),
  INDEX `idx_brand` (`brand`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Containers table (Shipping Container/Voyage Management)
CREATE TABLE `containers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `container_id` VARCHAR(50) NOT NULL UNIQUE,
  `container_number` VARCHAR(50) NULL,
  `max_capacity` INT(2) DEFAULT 4,
  `departure_port` VARCHAR(100) NOT NULL,
  `arrival_port` VARCHAR(100) NOT NULL DEFAULT 'Algeria',
  `departure_date` DATE,
  `estimated_arrival_date` DATE,
  `actual_arrival_date` DATE NULL,
  `container_shipping_cost` DECIMAL(12,2) NOT NULL,
  `status` ENUM('Scheduled', 'En Route', 'Arrived', 'Unloaded', 'Completed') DEFAULT 'Scheduled',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_container_id` (`container_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders table (Client Order/Transaction) - VIN is order-specific
CREATE TABLE `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` VARCHAR(20) NOT NULL UNIQUE,
  `client_id` INT(11) NOT NULL,
  `car_id` INT(11) NOT NULL,
  `vin` VARCHAR(17) NULL,
  `container_id` INT(11) NULL,
  `order_date` DATE NOT NULL,
  `total_sale_price` DECIMAL(12,2) NOT NULL,
  `shipping_status` ENUM('Awaiting Container', 'Shipped on Container', 'Arrived in Algeria', 'Customs Cleared', 'Ready for Pickup', 'Delivered') DEFAULT 'Awaiting Container',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`car_id`) REFERENCES `cars`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`container_id`) REFERENCES `containers`(`id`) ON DELETE SET NULL,
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_client_id` (`client_id`),
  INDEX `idx_car_id` (`car_id`),
  INDEX `idx_vin` (`vin`),
  INDEX `idx_shipping_status` (`shipping_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Container Cars (Many-to-Many relationship for container allocation)
CREATE TABLE `container_cars` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `container_id` INT(11) NOT NULL,
  `car_id` INT(11) NOT NULL,
  `order_id` INT(11) NULL,
  `loaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`container_id`) REFERENCES `containers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`car_id`) REFERENCES `cars`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_container_car` (`container_id`, `car_id`),
  INDEX `idx_container_id` (`container_id`),
  INDEX `idx_car_id` (`car_id`),
  INDEX `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments table
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `payment_id` VARCHAR(20) NOT NULL UNIQUE,
  `order_id` INT(11) NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount_paid` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('Bank Transfer', 'Cash', 'Check', 'Credit Card', 'Other') DEFAULT 'Bank Transfer',
  `payment_type` ENUM('Deposit', 'Installment', 'Full Payment') NOT NULL,
  `reference_number` VARCHAR(100),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_payment_id` (`payment_id`),
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents table (for file uploads)
CREATE TABLE `documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('client', 'car', 'order', 'container', 'message') NOT NULL,
  `entity_id` INT(11) NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_type` VARCHAR(50),
  `file_size` INT(11),
  `uploaded_by` INT(11) NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`uploaded_by`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Messages table (for client-admin communication)
CREATE TABLE `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `message_id` VARCHAR(20) NOT NULL UNIQUE,
  `order_id` INT(11) NULL,
  `client_id` INT(11) NOT NULL,
  `sender_id` INT(11) NULL,
  `sender_type` ENUM('client', 'admin', 'system') NOT NULL,
  `recipient_id` INT(11) NULL,
  `recipient_type` ENUM('client', 'admin', 'system') NOT NULL,
  `subject` VARCHAR(255),
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`recipient_id`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_message_id` (`message_id`),
  INDEX `idx_client_id` (`client_id`),
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order Media table (photos and videos for vehicle orders)
CREATE TABLE `order_media` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `media_type` ENUM('photo', 'video') NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT(11) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `thumbnail_path` VARCHAR(500) NULL,
  `uploaded_by` INT(11) NOT NULL,
  `visibility` ENUM('admin', 'client', 'public') DEFAULT 'client',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `admin_users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_media_type` (`media_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123 - change this in production!)
-- Password hash for 'admin123' - run fix_admin_password.php if login fails
INSERT INTO `admin_users` (`username`, `email`, `password`, `role`, `full_name`) VALUES
('admin', 'admin@dealership.com', '', 'admin', 'System Administrator');

-- Sample data for testing
INSERT INTO `clients` (`client_id`, `name`, `phone`, `email`, `passport_number`, `address`, `password`) VALUES
('CLI001', 'Ahmed Benali', '+213555123456', 'ahmed.benali@email.com', '1234567890123', '123 Rue Didouche Mourad, Algiers', ''),
('CLI002', 'Fatima Zohra', '+213555234567', 'fatima.zohra@email.com', '9876543210987', '456 Avenue de la République, Oran', ''),
('CLI003', 'Mohamed Amine', '+213555345678', 'mohamed.amine@email.com', '4567890123456', '789 Boulevard Mohamed V, Constantine', ''),

-- Additional clients imported from provided list
('CLI004', 'ABDELLATIF SAMI', '', NULL, '314360369', NULL, ''),
('CLI005', 'LAADJ ABDELHAK', '', NULL, '305531859', NULL, ''),
('CLI006', 'MREZIGUE EL ALIA', '', NULL, '314740447', NULL, ''),
('CLI007', 'SAIFI HANI', '', NULL, '168926916', NULL, ''),

('CLI008', 'BAHAR SAMI', '', NULL, '187258999', NULL, ''),
('CLI009', 'HAMDI MABROUK', '', NULL, '308665082', NULL, ''),
('CLI010', 'BENSAID MEHDI', '', NULL, '192066236', NULL, ''),
('CLI011', 'MESSAADIA AMAR', '', NULL, '303241961', NULL, ''),

('CLI012', 'LAOUAMRI YACINE', '', NULL, '308973674', NULL, ''),
('CLI013', 'BOUSEFSAF NOUR EL ISLAM', '', NULL, '314931020', NULL, ''),
('CLI014', 'DEKKOUMI SEIF EDDINE', '', NULL, '306762021', NULL, ''),
('CLI015', 'HACHELEF ABDELOUADOUD', '', NULL, '197551180', NULL, ''),

('CLI016', 'ABDESSAMIA SAMIA', '', NULL, '176676280', NULL, ''),
('CLI017', 'KRACHE SARRA', '', NULL, '168742086', NULL, ''),
('CLI018', 'KRIDISSE LYDIA', '', NULL, '314240496', NULL, '');

-- Pre-populated car inventory with popular models (Global best-sellers + UAE favorites)
INSERT INTO `cars` (`brand`, `model`, `year`, `trim`, `purchase_price_usd`, `sale_price_usd`, `status`, `description`) VALUES
-- Global Best-Sellers
('Toyota', 'Corolla', 2023, 'LE', 18000.00, 28000.00, 'Available', 'Compact sedan - Global best-seller'),
('Toyota', 'Corolla', 2024, 'LE', 18500.00, 28500.00, 'Available', 'Compact sedan - Global best-seller'),
('Toyota', 'RAV4', 2023, 'XLE', 28000.00, 42000.00, 'Available', 'Compact SUV - Global best-seller'),
('Toyota', 'RAV4', 2024, 'XLE', 28500.00, 43000.00, 'Available', 'Compact SUV - Global best-seller'),
('Honda', 'CR-V', 2023, 'EX', 27000.00, 40000.00, 'Available', 'Compact SUV - Global best-seller'),
('Honda', 'CR-V', 2024, 'EX', 27500.00, 41000.00, 'Available', 'Compact SUV - Global best-seller'),
('Ford', 'F-150', 2023, 'XLT', 35000.00, 52000.00, 'Available', 'Full-size pickup - Global best-seller'),
('Ford', 'F-150', 2024, 'XLT', 36000.00, 53000.00, 'Available', 'Full-size pickup - Global best-seller'),
('Tesla', 'Model Y', 2023, 'Long Range', 45000.00, 65000.00, 'Available', 'Electric SUV - Global best-seller'),
('Tesla', 'Model Y', 2024, 'Long Range', 46000.00, 66000.00, 'Available', 'Electric SUV - Global best-seller'),
-- UAE Market Favorites
('Toyota', 'Land Cruiser', 2023, 'GR Sport', 65000.00, 95000.00, 'Available', 'Full-size SUV - UAE favorite'),
('Toyota', 'Land Cruiser', 2024, 'GR Sport', 67000.00, 98000.00, 'Available', 'Full-size SUV - UAE favorite'),
('Nissan', 'Patrol', 2023, 'Platinum', 55000.00, 82000.00, 'Available', 'Full-size SUV - UAE favorite'),
('Nissan', 'Patrol', 2024, 'Platinum', 57000.00, 85000.00, 'Available', 'Full-size SUV - UAE favorite'),
('Lexus', 'GX', 2023, 'Luxury', 58000.00, 88000.00, 'Available', 'Luxury SUV - UAE favorite'),
('Lexus', 'GX', 2024, 'Luxury', 59000.00, 90000.00, 'Available', 'Luxury SUV - UAE favorite'),
('Lexus', 'RX', 2023, 'F Sport', 52000.00, 78000.00, 'Available', 'Luxury SUV - UAE favorite'),
('Lexus', 'RX', 2024, 'F Sport', 53000.00, 80000.00, 'Available', 'Luxury SUV - UAE favorite'),
('Mercedes-Benz', 'GLE', 2023, 'AMG Line', 65000.00, 98000.00, 'Available', 'Luxury SUV - UAE favorite'),
('Mercedes-Benz', 'GLE', 2024, 'AMG Line', 67000.00, 100000.00, 'Available', 'Luxury SUV - UAE favorite'),
('Mercedes-Benz', 'E-Class', 2023, 'AMG E 53', 72000.00, 108000.00, 'Available', 'Luxury sedan - UAE favorite'),
('Mercedes-Benz', 'E-Class', 2024, 'AMG E 53', 74000.00, 110000.00, 'Available', 'Luxury sedan - UAE favorite'),
('BMW', 'X5', 2023, 'xDrive40i', 62000.00, 94000.00, 'Available', 'Luxury SUV - UAE favorite'),
('BMW', 'X5', 2024, 'xDrive40i', 64000.00, 96000.00, 'Available', 'Luxury SUV - UAE favorite'),
('BMW', '5 Series', 2023, '530i', 55000.00, 83000.00, 'Available', 'Luxury sedan - UAE favorite'),
('BMW', '5 Series', 2024, '530i', 56000.00, 85000.00, 'Available', 'Luxury sedan - UAE favorite'),
('Land Rover', 'Range Rover Sport', 2023, 'HSE', 75000.00, 115000.00, 'Available', 'Luxury SUV - UAE favorite'),
('Land Rover', 'Range Rover Sport', 2024, 'HSE', 77000.00, 118000.00, 'Available', 'Luxury SUV - UAE favorite');

INSERT INTO `containers` (`container_id`, `container_number`, `departure_port`, `arrival_port`, `departure_date`, `estimated_arrival_date`, `container_shipping_cost`, `status`) VALUES
('CONT001', 'SHAF62369400', 'Shanghai Port', 'Algeria Port', '2024-01-15', '2024-02-20', 8000.00, 'En Route'),
('CONT002', NULL, 'Ningbo Port', 'Algeria Port', '2024-02-01', '2024-03-10', 8500.00, 'Scheduled'),
('CONT003', 'SHAF62369401', 'Shanghai Port', 'Algeria Port', '2024-01-10', '2024-02-15', 7500.00, 'Arrived');

INSERT INTO `orders` (`order_id`, `client_id`, `car_id`, `vin`, `container_id`, `order_date`, `total_sale_price`, `shipping_status`) VALUES
('ORD001', 1, 1, '1HGBH41JXMN109186', 1, '2024-01-10', 28000.00, 'Shipped on Container'),
('ORD002', 2, 2, '2HGBH41JXMN109187', 1, '2024-01-12', 28500.00, 'Shipped on Container'),
('ORD003', 3, 3, '3HGBH41JXMN109188', 3, '2024-01-05', 40000.00, 'Arrived in Algeria');

INSERT INTO `container_cars` (`container_id`, `car_id`, `order_id`) VALUES
(1, 1, 1),
(1, 2, 2),
(3, 4, 3);

INSERT INTO `payments` (`payment_id`, `order_id`, `payment_date`, `amount_paid`, `payment_method`, `payment_type`, `reference_number`) VALUES
('PAY001', 1, '2024-01-10', 5000.00, 'Bank Transfer', 'Deposit', 'TRF001'),
('PAY002', 1, '2024-01-25', 10000.00, 'Bank Transfer', 'Installment', 'TRF002'),
('PAY003', 2, '2024-01-12', 8000.00, 'Cash', 'Deposit', 'CASH001'),
('PAY004', 3, '2024-01-05', 40000.00, 'Bank Transfer', 'Full Payment', 'TRF003');