<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Paths;
use App\Core\Permissions;
use App\Core\Session;

require_once __DIR__ . '/Core/Paths.php';
require_once __DIR__ . '/Core/Session.php';
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/Flash.php';
require_once __DIR__ . '/Core/Auth.php';
require_once __DIR__ . '/Core/Permissions.php';

Session::start();

if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
    $GLOBALS['pdo'] = Database::connection();
}
$pdo = $GLOBALS['pdo'];

if (!function_exists('base_url')) {
    function base_url(): string
    {
        return Paths::baseUrl();
    }
}

if (!function_exists('app_base_url')) {
    function app_base_url(): string
    {
        return Paths::baseUrl();
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(): void
    {
        Auth::requireLogin();
    }
}

if (!function_exists('requireRole')) {
    /** @param array<int,string>|string $roles */
    function requireRole(array|string $roles): void
    {
        Auth::requireRole($roles);
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        Flash::set($type, $message);
    }
}

if (!function_exists('get_flash')) {
    function get_flash(string $type): ?string
    {
        return Flash::get($type);
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission(string $role, string $module, string $action): bool
    {
        return Permissions::has($role, $module, $action);
    }
}

if (!function_exists('getModulePermissions')) {
    function getModulePermissions(string $role, string $module): array
    {
        return Permissions::modulePermissions($role, $module);
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(string $role, string $module, string $action): void
    {
        if (!Permissions::has($role, $module, $action)) {
            http_response_code(403);
            exit("Access denied: You don't have permission to {$action} in {$module} module");
        }
    }
}

$permissions = Permissions::matrix();