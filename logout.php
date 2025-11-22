<?php
/**
 * Logout Page
 */
session_start();
session_destroy();
header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'login.php'));
exit;

