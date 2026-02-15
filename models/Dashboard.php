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

    public function getRecentActivities($limit = 5, $userRole = null) {
        try {
            $query = "
                SELECT
                    CONCAT(
                        a.action,
                        COALESCE(CONCAT(' - ', a.entity_type), ''),
                        COALESCE(CONCAT(' #', a.entity_id), '')
                    ) AS description,
                    a.log_time AS created_at,
                    a.action,
                    a.entity_type
                FROM audit_logs a
                ORDER BY a.log_time DESC
                LIMIT ?
            ";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
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


