<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions_messaging.php';
$admin_unread = 0;
if (function_exists('getUnreadMessageCount')) {
    $admin_unread = (int)getUnreadMessageCount(null, true);
}
?>
<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-car-front"></i> <?php echo APP_NAME; ?>
        </a>
        <div class="d-flex">
            <span class="navbar-text text-white me-3">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
            </span>
            <a href="messages.php" class="btn btn-outline-light btn-sm me-2 position-relative" title="Messages">
                <i class="bi bi-envelope"></i>
                <?php if ($admin_unread > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo $admin_unread; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

