<?php
class Dashboard {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function countFleet() {
        return $this->pdo->query("SELECT COUNT(*) FROM fleet")->fetchColumn();
    }

    public function countAssets() {
        return $this->pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
    }

    public function countProcurement() {
        return $this->pdo->query("SELECT COUNT(*) FROM procurement")->fetchColumn();
    }

    public function countInventory() {
        return $this->pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    }

    public function countUsers() {
        return $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public function countProjects(): int {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    }

    public function getFleetStats() {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM fleet GROUP BY status");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getProcurementStats() {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM procurement GROUP BY status");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getPurchaseOrderStats() {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM purchase_orders GROUP BY status");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getInventoryStats() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN stock < 50 THEN 1 ELSE 0 END) as low_stock FROM inventory");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getProjectStats() {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM projects GROUP BY status");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getMaintenanceStats() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM maintenance_logs WHERE performed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getRecentActivities($limit = 5, $userRole = null, $userId = null) {
        try {
            $role = (string)($userRole ?? '');
            $uid = (int)($userId ?? 0);

            $query = "
                SELECT
                    CONCAT(
                        a.action,
                        COALESCE(CONCAT(' - ', a.entity_type), ''),
                        COALESCE(CONCAT(' #', a.entity_id), '')
                    ) AS description,
                    a.log_time AS created_at,
                    a.action,
                    a.entity_type,
                    a.user_id
                FROM audit_logs a
            ";

            $whereParts = [];
            $params = [];

            $roleFilters = [
                'procurement_staff' => [
                    'entity' => ['procurement', 'purchase_orders', 'purchase_order_items', 'suppliers', 'budgets', 'approvals'],
                    'action_like' => ['procurement', 'purchase', 'supplier', 'budget', 'approval', 'po-'],
                ],
                'project_staff' => [
                    'entity' => ['projects', 'project_tasks', 'project_resources', 'fleet'],
                    'action_like' => ['project', 'task', 'resource', 'fleet expansion', 'timeline', 'schedule'],
                ],
                'asset' => [
                    'entity' => ['assets', 'asset_monitor_logs', 'asset_movements'],
                    'action_like' => ['asset', 'tracking', 'monitor', 'registry', 'tag'],
                ],
                'mro_staff' => [
                    'entity' => ['maintenance_logs'],
                    'action_like' => ['maintenance', 'repair', 'mro', 'service'],
                ],
                'warehouse_staff' => [
                    'entity' => ['inventory', 'receiving', 'stock_reconciliation'],
                    'action_like' => ['warehouse', 'inventory', 'receiv', 'stock', 'reconcil'],
                ],
            ];

            // Admin and manager see all activity.
            if (!in_array($role, ['admin', 'manager'], true) && isset($roleFilters[$role])) {
                $cfg = $roleFilters[$role];
                $moduleParts = [];

                if (!empty($cfg['entity'])) {
                    $entityPlaceholders = implode(',', array_fill(0, count($cfg['entity']), '?'));
                    $moduleParts[] = "a.entity_type IN ($entityPlaceholders)";
                    foreach ($cfg['entity'] as $entityType) {
                        $params[] = $entityType;
                    }
                }

                if (!empty($cfg['action_like'])) {
                    foreach ($cfg['action_like'] as $needle) {
                        $moduleParts[] = "LOWER(a.action) LIKE ?";
                        $params[] = '%' . strtolower($needle) . '%';
                    }
                }

                // Always include own actions to avoid empty feed when entity_type is missing.
                if ($uid > 0) {
                    $moduleParts[] = "a.user_id = ?";
                    $params[] = $uid;
                }

                if (!empty($moduleParts)) {
                    $whereParts[] = '(' . implode(' OR ', $moduleParts) . ')';
                }
            } elseif (!in_array($role, ['admin', 'manager'], true) && $uid > 0) {
                // Unknown role: safest behavior is personal activity only.
                $whereParts[] = 'a.user_id = ?';
                $params[] = $uid;
            }

            if (!empty($whereParts)) {
                $query .= ' WHERE ' . implode(' AND ', $whereParts);
            }

            $query .= ' ORDER BY a.log_time DESC LIMIT ?';

            $stmt = $this->pdo->prepare($query);
            $index = 1;
            foreach ($params as $value) {
                $stmt->bindValue($index++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue($index, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($results)) {
                return $results;
            }
        } catch (Exception $e) {
            // Fallback below
        }

        $stmt = $this->pdo->prepare("
            SELECT 'Fleet' as type, vehicle_name as description, created_at FROM fleet
            UNION ALL
            SELECT 'Procurement', item_name, created_at FROM procurement
            UNION ALL
            SELECT 'PurchaseOrder', po_number, created_at FROM purchase_orders
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingTasks($role = null) {
        $query = "SELECT 'Approve Request' as task, CONCAT(item_name, ' (', quantity, ')') as reference, created_at as due_date, id as ref_id, 'procurement' as ref_type FROM procurement WHERE status='Pending'";

        if (in_array($role, ['procurement_staff'], true)) {
            $query = "SELECT 'Review Procurement' as task, item_name AS reference, created_at as due_date, id as ref_id, 'procurement' as ref_type FROM procurement WHERE status='Pending'";
        } elseif (in_array($role, ['warehouse_staff'], true)) {
            $query = "SELECT 'Receive Stock' as task, po_number AS reference, created_at as due_date, id as ref_id, 'purchase_order' as ref_type FROM purchase_orders WHERE status='Sent'";
        } elseif (in_array($role, ['project_staff'], true)) {
            $query = "SELECT 'Start Task' as task, title AS reference, COALESCE(due_date, start_date, DATE(created_at)) AS due_date, id as ref_id, 'project_task' as ref_type FROM project_tasks WHERE status='Todo'";
        } elseif (in_array($role, ['mro_staff'], true)) {
            $query = "SELECT 'Perform Maintenance' as task, type AS reference, COALESCE(performed_at, DATE(created_at)) AS due_date, id as ref_id, 'maintenance' as ref_type FROM maintenance_logs ORDER BY created_at DESC";
        }

        $stmt = $this->pdo->query($query . " LIMIT 5");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}