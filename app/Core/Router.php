<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    public static function dispatchHome(): void
    {
        // Views are included from inside this method scope, so expose legacy globals.
        global $pdo, $permissions;

        $page = $_GET['page'] ?? 'dashboard';

        switch ($page) {
            case 'dashboard':
                require Paths::basePath('views/dashboard.php');
                return;
            default:
                http_response_code(404);
                echo 'Page not found';
                return;
        }
    }
}
