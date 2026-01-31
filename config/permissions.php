<?php
/**
 * Role-based permission system
 * Defines what each role can do across modules
 */

$permissions = [
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
        'procurement' => ['view', 'add', 'edit', 'approve'],
        'projects' => ['view', 'add', 'edit', 'manage_team'],
        'assets' => ['view', 'add', 'edit'],
        'mro' => ['view', 'add', 'edit'],
        'warehousing' => ['view', 'add', 'edit'],
        'users' => ['view'],
        'audit_logs' => ['view'],
    ],
    
    'procurement' => [
        'procurement' => ['view', 'add', 'edit'],
        'projects' => ['view'],
        'assets' => ['view'],
        'mro' => ['view'],
        'warehousing' => ['view'],
    ],
    
    'procurement_staff' => [
        'procurement' => ['view'],
        'projects' => ['view'],
        'warehousing' => ['view'],
    ],
    
    'project' => [
        'procurement' => ['view'],
        'projects' => ['view', 'add', 'edit'],
        'assets' => ['view'],
        'mro' => ['view'],
    ],
    
    'project_staff' => [
        'projects' => ['view'],
        'assets' => ['view'],
    ],
    
    'warehouse' => [
        'procurement' => ['view'],
        'warehousing' => ['view', 'add', 'edit'],
        'assets' => ['view'],
    ],
    
    'warehouse_staff' => [
        'warehousing' => ['view'],
        'procurement' => ['view'],
    ],
    
    'mro' => [
        'mro' => ['view', 'add', 'edit'],
        'assets' => ['view', 'edit'],
        'procurement' => ['view'],
    ],
    
    'mro_staff' => [
        'mro' => ['view'],
        'assets' => ['view'],
    ],
    
    'staff' => [
        'procurement' => ['view'],
        'projects' => ['view'],
        'assets' => ['view'],
        'mro' => ['view'],
        'warehousing' => ['view'],
    ],
];

/**
 * Check if user has permission for an action on a module
 */
function hasPermission(string $role, string $module, string $action): bool {
    global $permissions;
    
    if (!isset($permissions[$role])) {
        return false;
    }
    
    if (!isset($permissions[$role][$module])) {
        return false;
    }
    
    return in_array($action, $permissions[$role][$module], true);
}

/**
 * Get all permissions for a role and module
 */
function getModulePermissions(string $role, string $module): array {
    global $permissions;
    
    if (!isset($permissions[$role][$module])) {
        return [];
    }
    
    return $permissions[$role][$module];
}

/**
 * Require specific permission - throw error if not permitted
 */
function requirePermission(string $role, string $module, string $action): void {
    if (!hasPermission($role, $module, $action)) {
        http_response_code(403);
        die("Access denied: You don't have permission to $action in $module module");
    }
}
?>
