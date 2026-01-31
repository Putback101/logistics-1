<?php
class Approval {
  private $pdo;

  public function __construct($pdo){
    $this->pdo = $pdo;
  }

  private function cols(): array {
    $cols = [];
    $rows = $this->pdo->query("SHOW COLUMNS FROM approvals")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $cols[] = $r['Field'];
    return $cols;
  }

  public function request($module, $recordId, $userId): void {
    $cols = $this->cols();

    // If the table has requested_by, store who requested
    if (in_array('requested_by', $cols)) {
      $stmt = $this->pdo->prepare("
        INSERT INTO approvals (module, record_id, status, requested_by)
        VALUES (?,?, 'Pending', ?)
      ");
      $stmt->execute([$module, $recordId, $userId]);
      return;
    }

    // Fallback: basic approvals table (no requested_by column)
    $stmt = $this->pdo->prepare("
      INSERT INTO approvals (module, record_id, status)
      VALUES (?,?, 'Pending')
    ");
    $stmt->execute([$module, $recordId]);
  }

  public function act($module, $recordId, $status, $actedBy, $remarks=''): void {
    $cols = $this->cols();

    // Build update query depending on available columns
    $sets = ["status=?"];
    $params = [$status];

    if (in_array('acted_by', $cols)) { $sets[] = "acted_by=?"; $params[] = $actedBy; }
    if (in_array('acted_at', $cols)) { $sets[] = "acted_at=NOW()"; }
    if (in_array('remarks', $cols))  { $sets[] = "remarks=?";  $params[] = $remarks; }

    $params[] = $module;
    $params[] = $recordId;

    $sql = "UPDATE approvals SET " . implode(", ", $sets) . " WHERE module=? AND record_id=? ORDER BY id DESC LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
  }

  public function getLatest($module, $recordId) {
    $stmt = $this->pdo->prepare("SELECT * FROM approvals WHERE module=? AND record_id=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$module, $recordId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function listPending(string $module = 'purchase_orders'): array {
  $cols = $this->cols();
  $hasRequestedBy = in_array('requested_by', $cols, true);
  $hasActedBy     = in_array('acted_by', $cols, true);
  $hasActedAt     = in_array('acted_at', $cols, true);
  $hasRemarks     = in_array('remarks', $cols, true);

  $sel = "ap.*";
  // (ap.* already includes these if present, but keeping logic future-proof)

  $sql = "
    SELECT ap.*
    FROM approvals ap
    WHERE ap.module = ?
      AND ap.status = 'Pending'
    ORDER BY ap.id DESC
  ";
  $stmt = $this->pdo->prepare($sql);
  $stmt->execute([$module]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

}
