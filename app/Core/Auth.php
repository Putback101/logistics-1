<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    /** @return array<string, array<int, string>> */
    private static function roleAliases(): array
    {
        // Legacy -> SQL role mapping.
        return [
            'procurement' => ['procurement_staff'],
            'project' => ['project_staff'],
            'mro' => ['mro_staff'],
            'warehouse' => ['warehouse_staff'],
        ];
    }

    /** @param array<int,string> $allowedRoles
     *  @return array<int,string>
     */
    private static function expandAllowedRoles(array $allowedRoles): array
    {
        $aliases = self::roleAliases();
        $expanded = $allowedRoles;

        foreach ($allowedRoles as $role) {
            if (!isset($aliases[$role])) {
                continue;
            }

            foreach ($aliases[$role] as $alias) {
                $expanded[] = $alias;
            }
        }

        return array_values(array_unique($expanded));
    }

    public static function requireLogin(): void
    {
        Session::start();
        if (!isset($_SESSION['user'])) {
            Paths::redirect('/auth/login.php');
        }
    }

    /** @param array<int,string>|string $roles */
    public static function requireRole(array|string $roles): void
    {
        self::requireLogin();
        $allowed = self::expandAllowedRoles((array) $roles);
        $role = $_SESSION['user']['role'] ?? '';

        if ($role === 'admin') {
            return;
        }

        if (!in_array($role, $allowed, true)) {
            http_response_code(403);
            exit;
        }
    }
}
