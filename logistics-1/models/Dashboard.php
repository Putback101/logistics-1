<?php
class Dashboard {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function countFleet() {
        return $this->pdo->query("SELECT COUNT(*) FROM fleet")->fetchColumn();
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

    // Role-based dashboard stats
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
        // Try to fetch from audit_logs first, fall back to union if it doesn't have required columns
        try {
            $query = "
                SELECT 
                    CONCAT(a.action, ' - ', a.entity_type, ' #', a.entity_id) as description,
                    COALESCE(a.created_at, a.timestamp, NOW()) as created_at,
                    a.action,
                    a.entity_type
                FROM audit_logs a
                ORDER BY COALESCE(a.created_at, a.timestamp, NOW()) DESC
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If we got results, return them
            if (!empty($results)) {
                return $results;
            }
        } catch (Exception $e) {
            // Fall through to backup query
        }
        
        // Fallback: fetch from individual tables
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
        $query = "SELECT 'Approve PO' as task, po_number as reference, created_at as due_date FROM purchase_orders WHERE status='Approved'";
        
        if ($role === 'procurement') {
            $query = "SELECT 'Review Procurement' as task, item_name, created_at FROM procurement WHERE status='Pending'";
        } elseif ($role === 'warehouse') {
            $query = "SELECT 'Receive Stock' as task, item_name, created_at FROM purchase_orders WHERE status='Sent'";
        } elseif ($role === 'project') {
            $query = "SELECT 'Start Task' as task, title, start_date FROM project_tasks WHERE status='Pending'";
        }

        $stmt = $this->pdo->query($query . " LIMIT 5");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
