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

    private function supportsExternalSource(): bool {
        return $this->hasColumn('source_module')
            && $this->hasColumn('source_system')
            && $this->hasColumn('source_reference')
            && $this->hasColumn('source_payload');
    }

    public function getAll(): array {
        if ($this->supportsPoNumber()) {
            $sql = "
                SELECT p.*,
                       po.id AS linked_po_id,
                       po.total_amount AS linked_po_total,
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
            SELECT p.*, NULL AS po_number, NULL AS linked_po_id, NULL AS linked_po_total,
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
        ?int $itemId = null,
        ?int $requestedBy = null,
        ?string $sourceModule = null,
        ?string $sourceSystem = null,
        ?string $sourceReference = null,
        ?string $sourcePayload = null,
        ?int $projectId = null
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
        if ($this->hasColumn('requested_by')) {
            $columns[] = 'requested_by';
            $values[] = $requestedBy;
        }
        if ($this->supportsExternalSource()) {
            $columns[] = 'source_module';
            $values[] = $sourceModule;
            $columns[] = 'source_system';
            $values[] = $sourceSystem;
            $columns[] = 'source_reference';
            $values[] = $sourceReference;
            $columns[] = 'source_payload';
            $values[] = $sourcePayload;
        }
        if ($this->hasColumn('project_id')) {
            $columns[] = 'project_id';
            $values[] = $projectId;
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

        // Use linked PO totals for approved requests whenever a PO exists.
        // Pending requests remain estimate-based.
        if ($this->supportsPoNumber()) {
            $stmt = $this->pdo->query("
                SELECT
                  y.year,
                  COALESCE(p.pending_amount, 0) AS pending_amount,
                  COALESCE(a.approved_amount, 0) AS approved_amount,
                  COALESCE(p.pending_amount, 0) + COALESCE(a.approved_amount, 0) AS total_requested
                FROM (
                  SELECT DISTINCT budget_year AS year
                  FROM procurement
                  WHERE budget_year IS NOT NULL
                ) y
                LEFT JOIN (
                  SELECT budget_year AS year, SUM(estimated_amount) AS pending_amount
                  FROM procurement
                  WHERE budget_year IS NOT NULL
                    AND status = 'Pending'
                  GROUP BY budget_year
                ) p ON p.year = y.year
                LEFT JOIN (
                  SELECT t.year, SUM(t.amount) AS approved_amount
                  FROM (
                    SELECT
                      p.budget_year AS year,
                      p.id AS uniq_key,
                      p.estimated_amount AS amount
                    FROM procurement p
                    WHERE p.budget_year IS NOT NULL
                      AND p.status = 'Approved'
                      AND (p.po_number IS NULL OR p.po_number = '')

                    UNION ALL

                    SELECT
                      p.budget_year AS year,
                      po.id AS uniq_key,
                      po.total_amount AS amount
                    FROM procurement p
                    INNER JOIN purchase_orders po ON po.po_number = p.po_number
                    WHERE p.budget_year IS NOT NULL
                      AND p.status = 'Approved'
                      AND p.po_number IS NOT NULL
                      AND p.po_number <> ''
                    GROUP BY p.budget_year, po.id, po.total_amount
                  ) t
                  GROUP BY t.year
                ) a ON a.year = y.year
                ORDER BY y.year DESC
            ");
            return $stmt->fetchAll();
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

    public function findBySource(string $sourceModule, string $sourceReference): array {
        if (!$this->supportsExternalSource()) {
            return [];
        }
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM procurement
            WHERE source_module = ?
              AND source_reference = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$sourceModule, $sourceReference]);
        return $stmt->fetchAll();
    }
}

