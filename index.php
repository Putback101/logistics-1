<?php
require_once 'config/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';

switch ($page) {
    case 'dashboard':
        include 'views/dashboard.php';
        break;

    default:
        http_response_code(404);
        echo "Page not found";
}
