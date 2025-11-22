<?php
/**
 * Create Media Tables via AJAX
 */

header('Content-Type: application/json');

require_once 'config.php';

try {
    // Create order_media table
    $sql1 = "
    CREATE TABLE IF NOT EXISTS `order_media` (
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
      FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
      INDEX `idx_order_id` (`order_id`),
      INDEX `idx_media_type` (`media_type`),
      INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $pdo->exec($sql1);
    
    // Create media_logs table
    $sql2 = "
    CREATE TABLE IF NOT EXISTS `media_logs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `media_id` INT NOT NULL,
      `user_id` INT,
      `action` VARCHAR(50),
      `ip_address` VARCHAR(50),
      `accessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (media_id) REFERENCES order_media(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $pdo->exec($sql2);
    
    // Verify tables exist
    $result = $pdo->query("SHOW TABLES LIKE 'order_media'")->fetch();
    
    if ($result) {
        // Create directories
        @mkdir(MEDIA_UPLOAD_DIR . 'orders/', 0755, true);
        @mkdir(MEDIA_UPLOAD_DIR . 'orders/thumbs/', 0755, true);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tables created successfully! order_media and media_logs tables are ready.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tables were not created. Please check database permissions.'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
