-- Order Media table (photos and videos for vehicle orders)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Media logs table (auto-created)
CREATE TABLE IF NOT EXISTS `media_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `media_id` INT NOT NULL,
  `user_id` INT,
  `action` VARCHAR(50),
  `ip_address` VARCHAR(50),
  `accessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (media_id) REFERENCES order_media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
