<nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['clients.php', 'add_client.php', 'edit_client.php', 'client_details.php', 'orders.php', 'add_order.php', 'add_client_and_order.php', 'order_details.php', 'clients_and_orders.php']) ? 'active' : ''; ?>" href="clients_and_orders.php">
                    <i class="bi bi-people-fill"></i> Clients & Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['cars.php', 'add_car.php', 'edit_car.php', 'car_details.php']) ? 'active' : ''; ?>" href="cars.php">
                    <i class="bi bi-car-front"></i> Cars Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['containers.php', 'add_container.php', 'container_details.php']) ? 'active' : ''; ?>" href="containers.php">
                    <i class="bi bi-box-seam"></i> Containers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                    <i class="bi bi-cash-stack"></i> Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'financial_dashboard.php' ? 'active' : ''; ?>" href="financial_dashboard.php">
                    <i class="bi bi-graph-up"></i> Financial Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                    <i class="bi bi-envelope"></i> Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-person-gear"></i> Users
                </a>
            </li>
        </ul>
    </div>
</nav>