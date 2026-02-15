<?php
class Procurement {
    private PDO $pdo;
    private array $columnCache = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function hasColumn(string $column): bool {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) c
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'procurement'
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$column]);
            $this->columnCache[$column] = ((int)($stmt->fetch()['c'] ?? 0)) > 0;
        } catch (Throwable $e) {
            $this->columnCache[$column] = false;
        }

        return $this->columnCache[$column];
    }

    private function supportsPoNumber(): bool {
        return $this->hasColumn('po_number');
    }

    public function getAll(): array {
        if ($this->supportsPoNumber()) {
            $sql = "
                SELECT p.*,
                       po.id AS linked_po_id,
                       i.item_name AS master_item_name,
                       i.category AS item_category,
                       i.unit AS item_unit
                FROM procurement p
                LEFT JOIN purchase_orders po ON po.po_number = p.po_number
                LEFT JOIN items i ON i.id = p.item_id
                ORDER BY p.id DESC
            ";
            return $this->pdo->query($sql)->fetchAll();
        }

        return $this->pdo->query("
            SELECT p.*, NULL AS po_number, NULL AS linked_po_id,
                   i.item_name AS master_item_name,
                   i.category AS item_category,
                   i.unit AS item_unit
            FROM procurement p
            LEFT JOIN items i ON i.id = p.item_id
            ORDER BY p.id DESC
        ")->fetchAll();
    }

    public function create(
        string $item,
        int $qty,
        string $supplier,
        string $status = 'Pending',
        ?int $budgetYear = null,
        float $estimatedAmount = 0,
        ?string $requestRef = null,
        ?int $itemId = null
    ): bool {
        $columns = ['item_name', 'quantity', 'supplier', 'status'];
        $values = [$item, $qty, $supplier, 'Pending'];

        if ($this->hasColumn('item_id')) {
            $columns[] = 'item_id';
            $values[] = $itemId;
        }
        if ($this->hasColumn('budget_year')) {
            $columns[] = 'budget_year';
            $values[] = $budgetYear;
        }
        if ($this->hasColumn('estimated_amount')) {
            $columns[] = 'estimated_amount';
            $values[] = $estimatedAmount;
        }
        if ($this->hasColumn('request_ref')) {
            $columns[] = 'request_ref';
            $values[] = $requestRef;
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO procurement (" . implode(',', $columns) . ") VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function getById(int $id) {
        $stmt = $this->pdo->prepare("SELECT * FROM procurement WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update(
        int $id,
        string $item,
        int $qty,
        string $supplier,
        string $status,
        ?int $budgetYear = null,
        float $estimatedAmount = 0,
        ?int $itemId = null
    ): bool {
        $allowed = ['Pending', 'Approved'];
        if (!in_array($status, $allowed, true)) {
            $status = 'Pending';
        }

        $sets = ['item_name=?', 'quantity=?', 'supplier=?', 'status=?'];
        $values = [$item, $qty, $supplier, $status];

        if ($this->hasColumn('item_id')) {
            $sets[] = 'item_id=?';
            $values[] = $itemId;
        }
        if ($this->hasColumn('budget_year')) {
            $sets[] = 'budget_year=?';
            $values[] = $budgetYear;
        }
        if ($this->hasColumn('estimated_amount')) {
            $sets[] = 'estimated_amount=?';
            $values[] = $estimatedAmount;
        }

        $values[] = $id;
        $sql = "UPDATE procurement SET " . implode(', ', $sets) . " WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM procurement WHERE id=?");
        return $stmt->execute([$id]);
    }

    public function setStatus(int $id, string $status): bool {
        $allowed = ['Pending', 'Approved'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $stmt = $this->pdo->prepare("UPDATE procurement SET status=? WHERE id=?");
        return $stmt->execute([$status, $id]);
    }

    public function setPoNumber(int $id, string $poNumber): bool {
        if (!$this->supportsPoNumber()) {
            return false;
        }
        $stmt = $this->pdo->prepare("UPDATE procurement SET po_number=? WHERE id=?");
        return $stmt->execute([$poNumber, $id]);
    }

    public function getBudgetRequestSummary(): array {
        if (!$this->hasColumn('budget_year') || !$this->hasColumn('estimated_amount')) {
            return [];
        }

        $stmt = $this->pdo->query("
            SELECT
              budget_year AS year,
              SUM(CASE WHEN status = 'Pending' THEN estimated_amount ELSE 0 END) AS pending_amount,
              SUM(CASE WHEN status = 'Approved' THEN estimated_amount ELSE 0 END) AS approved_amount,
              SUM(estimated_amount) AS total_requested
            FROM procurement
            WHERE budget_year IS NOT NULL
            GROUP BY budget_year
            ORDER BY budget_year DESC
        ");
        return $stmt->fetchAll();
    }
    public function getYearRequestedTotal(int $year, array $statuses = ['Pending', 'Approved'], ?int $excludeId = null): float {
        if (!$this->hasColumn('budget_year') || !$this->hasColumn('estimated_amount')) {
            return 0.0;
        }

        if (empty($statuses)) {
            return 0.0;
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT COALESCE(SUM(estimated_amount),0) AS total FROM procurement WHERE budget_year = ? AND status IN ($placeholders)";
        $params = [$year, ...$statuses];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (float)($stmt->fetch()['total'] ?? 0);
    }
}
