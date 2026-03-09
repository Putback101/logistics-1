<?php
class Budget {
  private PDO $pdo;

  public function __construct(PDO $pdo){
    $this->pdo = $pdo;
  }

  public function getAll(): array {
    return $this->pdo->query("SELECT * FROM budgets ORDER BY year DESC")->fetchAll();
  }

  public function getByYear(int $year) {
    $stmt = $this->pdo->prepare("SELECT * FROM budgets WHERE year=?");
    $stmt->execute([$year]);
    return $stmt->fetch();
  }

  public function upsert(int $year, float $allocated, ?float $spent = null): void {
    if ($spent === null) {
      $stmt = $this->pdo->prepare("\n        INSERT INTO budgets (year, allocated) VALUES (?,?)\n        ON DUPLICATE KEY UPDATE allocated=VALUES(allocated)\n      ");
      $stmt->execute([$year,$allocated]);
      return;
    }

    $stmt = $this->pdo->prepare("\n      INSERT INTO budgets (year, allocated, spent) VALUES (?,?,?)\n      ON DUPLICATE KEY UPDATE allocated=VALUES(allocated), spent=VALUES(spent)\n    ");
    $stmt->execute([$year,$allocated,$spent]);
  }

  public function addSpent(int $year, float $amount): void {
    $stmt = $this->pdo->prepare("UPDATE budgets SET spent = spent + ? WHERE year=?");
    $stmt->execute([$amount,$year]);
  }

  public function addAllocated(int $year, float $amount): void {
    $stmt = $this->pdo->prepare("UPDATE budgets SET allocated = allocated + ? WHERE year = ?");
    $stmt->execute([$amount, $year]);
  }

  /**
   * Returns linked PO spending per budget year.
   * Linked = procurement.budget_year + procurement.po_number joined to purchase_orders.po_number.
   */
  public function getLinkedSpentByYear(array $statuses = ['Approved','Sent','Received']): array {
    if (empty($statuses)) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($statuses), '?'));

    $sql = "
      SELECT t.year, COALESCE(SUM(t.total_amount), 0) AS linked_spent
      FROM (
        SELECT DISTINCT p.budget_year AS year, po.id AS po_id, po.total_amount
        FROM procurement p
        INNER JOIN purchase_orders po ON po.po_number = p.po_number
        WHERE p.budget_year IS NOT NULL
          AND p.po_number IS NOT NULL
          AND po.status IN ($placeholders)
      ) t
      GROUP BY t.year
      ORDER BY t.year DESC
    ";

    try {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($statuses);

      $map = [];
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['year']] = (float)$row['linked_spent'];
      }

      return $map;
    } catch (Throwable $e) {
      return [];
    }
  }

  public function submitRequest(int $year, float $amount, string $purpose, int $requestedBy): void {
    $existing = $this->getByYear($year);
    if (!$existing) {
      $this->upsert($year, 0.0, null);
      $existing = $this->getByYear($year);
    }

    $budgetId = (int)($existing['id'] ?? 0);
    $payload = json_encode([
      'type' => 'budget_request',
      'year' => $year,
      'amount' => $amount,
      'purpose' => $purpose,
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $this->pdo->prepare("\n      INSERT INTO approvals (module, record_id, status, requested_by, remarks, created_at)\n      VALUES ('budget', ?, 'Pending', ?, ?, NOW())\n    ");
    $stmt->execute([$budgetId, $requestedBy, $payload]);
  }

  public function listRequests(): array {
    $stmt = $this->pdo->query("\n      SELECT a.*,\n             b.year AS budget_year,\n             req.fullname AS requested_by_name,\n             act.fullname AS acted_by_name\n      FROM approvals a\n      LEFT JOIN budgets b ON b.id = a.record_id\n      LEFT JOIN users req ON req.id = a.requested_by\n      LEFT JOIN users act ON act.id = a.acted_by\n      WHERE a.module = 'budget'\n      ORDER BY a.created_at DESC, a.id DESC\n    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      $payload = json_decode((string)($row['remarks'] ?? ''), true);
      if (is_array($payload)) {
        $row['req_year'] = (int)($payload['year'] ?? ($row['budget_year'] ?? 0));
        $row['req_amount'] = (float)($payload['amount'] ?? 0);
        $row['req_purpose'] = (string)($payload['purpose'] ?? '');
      } else {
        $row['req_year'] = (int)($row['budget_year'] ?? 0);
        $row['req_amount'] = 0.0;
        $row['req_purpose'] = (string)($row['remarks'] ?? '');
      }
    }
    unset($row);

    return $rows;
  }

  public function decideRequest(int $approvalId, string $status, int $actedBy): bool {
    if (!in_array($status, ['Approved','Rejected'], true)) {
      return false;
    }

    $stmt = $this->pdo->prepare("\n      SELECT * FROM approvals\n      WHERE id = ? AND module = 'budget' AND status = 'Pending'\n      LIMIT 1\n    ");
    $stmt->execute([$approvalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      return false;
    }

    $payload = json_decode((string)($row['remarks'] ?? ''), true);
    $year = (int)($payload['year'] ?? 0);
    $amount = (float)($payload['amount'] ?? 0);

    $this->pdo->beginTransaction();
    try {
      if ($status === 'Approved' && $year > 0 && $amount > 0) {
        $existing = $this->getByYear($year);
        if (!$existing) {
          $this->upsert($year, 0.0, null);
        }
        $this->addAllocated($year, $amount);
      }

      $u = $this->pdo->prepare("\n        UPDATE approvals\n        SET status = ?, acted_by = ?, acted_at = NOW(),\n            approved_by = CASE WHEN ? = 'Approved' THEN ? ELSE approved_by END,\n            approved_at = CASE WHEN ? = 'Approved' THEN NOW() ELSE approved_at END\n        WHERE id = ? AND module = 'budget' AND status = 'Pending'\n      ");
      $u->execute([$status, $actedBy, $status, $actedBy, $status, $approvalId]);

      if ($u->rowCount() < 1) {
        $this->pdo->rollBack();
        return false;
      }

      $this->pdo->commit();
      return true;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      return false;
    }
  }
}
