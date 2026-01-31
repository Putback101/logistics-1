<?php
class DashboardCharts {
  private $pdo;

  public function __construct($pdo) {
    $this->pdo = $pdo;
  }

  private function countByStatus(string $table, string $statusCol = "status"): array {
    $stmt = $this->pdo->query("SELECT $statusCol AS status, COUNT(*) AS total FROM $table GROUP BY $statusCol");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
      $out[$r['status']] = (int)$r['total'];
    }
    return $out;
  }

  public function fleetStatus(): array {
    return $this->countByStatus("fleet");
  }

  public function procurementStatus(): array {
    return $this->countByStatus("procurement");
  }

  // ✅ ADD THIS (Low Stock < threshold, Top 5)
  public function lowStockTop5(int $threshold = 50): array {
    $stmt = $this->pdo->prepare("
      SELECT item_name, stock
      FROM inventory
      WHERE stock < ?
      ORDER BY stock ASC, item_name ASC
      LIMIT 5
    ");
    $stmt->execute([$threshold]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
