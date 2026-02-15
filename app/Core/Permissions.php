<?php

declare(strict_types=1);

namespace App\Core;

final class Permissions
{
    /** @return array<string,array<string,array<int,string>>> */
    public static function matrix(): array
    {
        return [
            'admin' => [
                'procurement' => ['view', 'add', 'edit', 'delete', 'approve'],
                'projects' => ['view', 'add', 'edit', 'delete', 'manage_team'],
                'assets' => ['view', 'add', 'edit', 'delete'],
                'mro' => ['view', 'add', 'edit', 'delete'],
                'warehousing' => ['view', 'add', 'edit', 'delete'],
                'users' => ['view', 'add', 'edit', 'delete'],
                'audit_logs' => ['view'],
            ],
            'manager' => [
                'procurement' => ['view', 'add', 'edit', 'delete', 'approve'],
                'projects' => ['view', 'add', 'edit', 'delete', 'manage_team'],
                'assets' => ['view', 'add', 'edit', 'delete'],
                'mro' => ['view', 'add', 'edit', 'delete'],
                'warehousing' => ['view', 'add', 'edit', 'delete'],
                'users' => ['view'],
                'audit_logs' => ['view'],
            ],
            'procurement_staff' => [
                'procurement' => ['view', 'add'],
            ],
            'project_staff' => [
                'projects' => ['view', 'add', 'edit'],
            ],
            'asset' => [
                'assets' => ['view', 'add', 'edit', 'delete'],
            ],
            'mro_staff' => [
                'mro' => ['view', 'add', 'edit'],
                'assets' => ['view'],
            ],
            'warehouse_staff' => [
                'warehousing' => ['view', 'add', 'edit'],
                'procurement' => ['view'],
            ],
        ];
    }

    /** @return array<string,array<int,string>> */
    private static function roleAliases(): array
    {
        // Legacy roles map to SQL roles for backward compatibility.
        return [
            'procurement' => ['procurement_staff'],
            'project' => ['project_staff'],
            'warehouse' => ['warehouse_staff'],
            'mro' => ['mro_staff'],
        ];
    }

    /** @return array<int,string> */
    private static function candidateRoles(string $role): array
    {
        $roles = [$role];
        $aliases = self::roleAliases();

        if (isset($aliases[$role])) {
            foreach ($aliases[$role] as $alias) {
                $roles[] = $alias;
            }
        }

        return array_values(array_unique($roles));
    }

    public static function has(string $role, string $module, string $action): bool
    {
        $matrix = self::matrix();
        foreach (self::candidateRoles($role) as $candidateRole) {
            if (isset($matrix[$candidateRole][$module]) && in_array($action, $matrix[$candidateRole][$module], true)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,string> */
    public static function modulePermissions(string $role, string $module): array
    {
        $matrix = self::matrix();
        $merged = [];

        foreach (self::candidateRoles($role) as $candidateRole) {
            $merged = array_merge($merged, $matrix[$candidateRole][$module] ?? []);
        }

        return array_values(array_unique($merged));
    }
}
