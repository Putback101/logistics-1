<?php
class Maintenance {
  private $pdo;
  public function __construct($pdo){ $this->pdo = $pdo; }

  public function getAll(): array {
    return $this->pdo->query("
      SELECT 
        m.*,
        f.vehicle_name, f.plate_number,
        a.asset_tag, a.asset_name,
        u.fullname AS recorded_by_name
      FROM maintenance_logs m
      LEFT JOIN fleet f ON f.id = m.fleet_id
      LEFT JOIN assets a ON a.id = m.asset_id
      LEFT JOIN users u ON u.id = m.recorded_by
      ORDER BY COALESCE(m.performed_at, DATE(m.created_at)) DESC, m.id DESC
    ")->fetchAll();
  }

  // Existing fleet create (kept)
  public function createFleet(int $fleetId, string $type, string $desc, float $cost, ?string $date, ?int $userId): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO maintenance_logs (fleet_id, asset_id, type, description, cost, performed_at, recorded_by)
      VALUES (?, NULL, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$fleetId, $type, $desc, $cost, $date ?: null, $userId]);
  }

  // NEW: create for Asset (auto request)
  public function createAsset(int $assetId, string $type, string $desc, float $cost, ?string $date, ?int $userId): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO maintenance_logs (fleet_id, asset_id, type, description, cost, performed_at, recorded_by)
      VALUES (NULL, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$assetId, $type, $desc, $cost, $date ?: null, $userId]);
  }
}
