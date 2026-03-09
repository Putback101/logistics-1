<?php
class Asset {
  private $pdo;
  public function __construct($pdo){
    $this->pdo = $pdo;
    $this->ensureWarehouseSourceColumn();
  }

  private function ensureWarehouseSourceColumn(): void {
    $stmt = $this->pdo->query("SHOW COLUMNS FROM assets LIKE 'source_inventory_id'");
    $exists = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if (!$exists) {
      $this->pdo->exec("ALTER TABLE assets ADD COLUMN source_inventory_id INT NULL AFTER notes");
      $this->pdo->exec("ALTER TABLE assets ADD INDEX idx_assets_source_inventory_id (source_inventory_id)");
    }
  }

  // -----------------------------
  // REGISTRY
  // -----------------------------
  public function getAll(): array {
    $sql = "SELECT a.*,
              u.fullname AS assigned_name,
              inv.item_name AS source_inventory_name,
              inv.location AS source_inventory_location,
              inv.stock AS source_inventory_stock
            FROM assets a
            LEFT JOIN users u ON u.id = a.assigned_to
            LEFT JOIN inventory inv ON inv.id = a.source_inventory_id
            ORDER BY a.id DESC";
    return $this->pdo->query($sql)->fetchAll();
  }

  public function getById(int $id) {
    $stmt = $this->pdo->prepare("SELECT a.*, inv.item_name AS source_inventory_name, inv.location AS source_inventory_location, inv.stock AS source_inventory_stock FROM assets a LEFT JOIN inventory inv ON inv.id = a.source_inventory_id WHERE a.id=?");
    $stmt->execute([$id]);
    return $stmt->fetch();
  }

  public function create(array $d): int {
    $stmt = $this->pdo->prepare("
      INSERT INTO assets
        (asset_tag, asset_name, asset_category, brand, model, serial_no,
         acquisition_date, purchase_cost, status, location, assigned_to, notes, source_inventory_id)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
      trim($d['asset_tag'] ?? ''),
      trim($d['asset_name'] ?? ''),
      trim($d['asset_category'] ?? ''),
      trim($d['brand'] ?? ''),
      trim($d['model'] ?? ''),
      trim($d['serial_no'] ?? ''),
      $d['acquisition_date'] ?: null,
      (float)($d['purchase_cost'] ?? 0),
      $d['status'] ?? 'Active',
      trim($d['location'] ?? ''),
      $d['assigned_to'] !== '' ? (int)$d['assigned_to'] : null,
      trim($d['notes'] ?? ''),
      isset($d['source_inventory_id']) && ctype_digit((string)$d['source_inventory_id']) ? (int)$d['source_inventory_id'] : null,
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, array $d): void {
    $stmt = $this->pdo->prepare("
      UPDATE assets SET
        asset_tag=?,
        asset_name=?,
        asset_category=?,
        brand=?,
        model=?,
        serial_no=?,
        acquisition_date=?,
        purchase_cost=?,
        status=?,
        location=?,
        assigned_to=?,
        notes=?,
        source_inventory_id=?
      WHERE id=?
    ");
    $stmt->execute([
      trim($d['asset_tag'] ?? ''),
      trim($d['asset_name'] ?? ''),
      trim($d['asset_category'] ?? ''),
      trim($d['brand'] ?? ''),
      trim($d['model'] ?? ''),
      trim($d['serial_no'] ?? ''),
      $d['acquisition_date'] ?: null,
      (float)($d['purchase_cost'] ?? 0),
      $d['status'] ?? 'Active',
      trim($d['location'] ?? ''),
      $d['assigned_to'] !== '' ? (int)$d['assigned_to'] : null,
      trim($d['notes'] ?? ''),
      isset($d['source_inventory_id']) && ctype_digit((string)$d['source_inventory_id']) ? (int)$d['source_inventory_id'] : null,
      $id
    ]);
  }

  public function delete(int $id): void {
    $stmt = $this->pdo->prepare("DELETE FROM assets WHERE id=?");
    $stmt->execute([$id]);
  }

  // -----------------------------
  // TRACKING (MOVEMENTS)
  // -----------------------------
  public function movementLog(int $assetId): array {
    $stmt = $this->pdo->prepare("
      SELECT m.*,
        fu.fullname AS from_user_name,
        tu.fullname AS to_user_name,
        mb.fullname AS moved_by_name
      FROM asset_movements m
      LEFT JOIN users fu ON fu.id = m.from_user
      LEFT JOIN users tu ON tu.id = m.to_user
      LEFT JOIN users mb ON mb.id = m.moved_by
      WHERE m.asset_id = ?
      ORDER BY m.moved_at DESC
    ");
    $stmt->execute([$assetId]);
    return $stmt->fetchAll();
  }

  public function recordMovement(array $d): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO asset_movements
        (asset_id, from_location, to_location, from_user, to_user, moved_by, moved_at, remarks)
      VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
      (int)$d['asset_id'],
      trim($d['from_location'] ?? ''),
      trim($d['to_location'] ?? ''),
      $d['from_user'] !== '' ? (int)$d['from_user'] : null,
      $d['to_user'] !== '' ? (int)$d['to_user'] : null,
      $d['moved_by'] !== '' ? (int)$d['moved_by'] : null,
      $d['moved_at'] ?: date('Y-m-d H:i:s'),
      trim($d['remarks'] ?? '')
    ]);

    $assetId = (int)$d['asset_id'];
    $toLoc = trim($d['to_location'] ?? '');
    $toUser = $d['to_user'] !== '' ? (int)$d['to_user'] : null;

    $u = $this->pdo->prepare("UPDATE assets SET location=?, assigned_to=? WHERE id=?");
    $u->execute([$toLoc, $toUser, $assetId]);
  }

  // -----------------------------
  // MONITORING (CONDITION/USAGE)
  // -----------------------------
  public function monitorLog(int $assetId): array {
    $stmt = $this->pdo->prepare("
      SELECT l.*, u.fullname AS recorded_by_name
      FROM asset_monitor_logs l
      LEFT JOIN users u ON u.id = l.recorded_by
      WHERE l.asset_id = ?
      ORDER BY l.created_at DESC
    ");
    $stmt->execute([$assetId]);
    return $stmt->fetchAll();
  }

  public function addMonitorLog(array $d): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO asset_monitor_logs
        (asset_id, condition_status, usage_hours, last_inspected, next_inspection, recorded_by, remarks)
      VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->execute([
      (int)$d['asset_id'],
      $d['condition_status'] ?? 'Good',
      (float)($d['usage_hours'] ?? 0),
      $d['last_inspected'] ?: null,
      $d['next_inspection'] ?: null,
      $d['recorded_by'] !== '' ? (int)$d['recorded_by'] : null,
      trim($d['remarks'] ?? '')
    ]);

    if (($d['condition_status'] ?? '') === 'Needs Maintenance') {
      $u = $this->pdo->prepare("UPDATE assets SET status='Under Maintenance' WHERE id=?");
      $u->execute([(int)$d['asset_id']]);
    }
  }
}
