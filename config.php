<?php
/**
 * Database Configuration File
 * Car Dealership Client and Logistics Management System
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'car_dealership');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'Car Dealership Management System');
define('BASE_URL', 'http://localhost/CARS2');
define('ADMIN_URL', BASE_URL . '/Admin');

// File upload configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 52428800); // 50MB for media files
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx']);
define('MEDIA_FILE_TYPES', ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov', 'avi', 'mkv']);
define('MAX_MEDIA_FILE_SIZE', 104857600); // 100MB for large video files
define('MEDIA_UPLOAD_DIR', UPLOAD_DIR . 'media/');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Set to 1 in production with HTTPS

// Timezone
date_default_timezone_set('Africa/Algiers');

// Error reporting (disable in production)
error_reporting(0);
ini_set('display_errors', 0);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    mkdir(UPLOAD_DIR . 'clients/', 0755, true);
    mkdir(UPLOAD_DIR . 'cars/', 0755, true);
    mkdir(UPLOAD_DIR . 'orders/', 0755, true);
    mkdir(UPLOAD_DIR . 'containers/', 0755, true);
    mkdir(UPLOAD_DIR . 'messages/', 0755, true);
    mkdir(UPLOAD_DIR . 'media/orders/', 0755, true);
    mkdir(UPLOAD_DIR . 'media/orders/thumbs/', 0755, true);
}
?>