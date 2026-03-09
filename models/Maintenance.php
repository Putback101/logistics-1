<?php
class Maintenance {
  private $pdo;
  private array $columnCache = [];
  public function __construct($pdo){ $this->pdo = $pdo; }

  private function hasColumn(string $column): bool {
    if (array_key_exists($column, $this->columnCache)) {
      return $this->columnCache[$column];
    }

    try {
      $stmt = $this->pdo->prepare("
        SELECT COUNT(*) c
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'maintenance_logs'
          AND column_name = ?
      ");
      $stmt->execute([$column]);
      $this->columnCache[$column] = ((int)($stmt->fetch()['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
      $this->columnCache[$column] = false;
    }

    return $this->columnCache[$column];
  }

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
  public function createFleet(
    int $fleetId,
    string $type,
    string $desc,
    float $cost,
    ?string $date,
    ?int $userId,
    ?string $requestRef = null,
    ?string $priority = null,
    ?string $sourceModule = null,
    ?string $sourceSystem = null,
    ?string $sourceReference = null,
    ?string $sourcePayload = null
  ): void {
    $columns = ['fleet_id', 'asset_id', 'type', 'description', 'cost', 'performed_at', 'recorded_by'];
    $values = [$fleetId, null, $type, $desc, $cost, $date ?: null, $userId];

    if ($this->hasColumn('request_ref')) {
      $columns[] = 'request_ref';
      $values[] = $requestRef;
    }
    if ($this->hasColumn('priority')) {
      $columns[] = 'priority';
      $values[] = $priority ?: 'Normal';
    }
    if ($this->hasColumn('source_module')) {
      $columns[] = 'source_module';
      $values[] = $sourceModule;
    }
    if ($this->hasColumn('source_system')) {
      $columns[] = 'source_system';
      $values[] = $sourceSystem;
    }
    if ($this->hasColumn('source_reference')) {
      $columns[] = 'source_reference';
      $values[] = $sourceReference;
    }
    if ($this->hasColumn('source_payload')) {
      $columns[] = 'source_payload';
      $values[] = $sourcePayload;
    }

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $stmt = $this->pdo->prepare("INSERT INTO maintenance_logs (" . implode(',', $columns) . ") VALUES ($placeholders)");
    $stmt->execute($values);
  }

  // NEW: create for Asset (auto request)
  public function createAsset(
    int $assetId,
    string $type,
    string $desc,
    float $cost,
    ?string $date,
    ?int $userId,
    ?string $requestRef = null,
    ?string $priority = null,
    ?string $sourceModule = null,
    ?string $sourceSystem = null,
    ?string $sourceReference = null,
    ?string $sourcePayload = null
  ): void {
    $columns = ['fleet_id', 'asset_id', 'type', 'description', 'cost', 'performed_at', 'recorded_by'];
    $values = [null, $assetId, $type, $desc, $cost, $date ?: null, $userId];

    if ($this->hasColumn('request_ref')) {
      $columns[] = 'request_ref';
      $values[] = $requestRef;
    }
    if ($this->hasColumn('priority')) {
      $columns[] = 'priority';
      $values[] = $priority ?: 'Normal';
    }
    if ($this->hasColumn('source_module')) {
      $columns[] = 'source_module';
      $values[] = $sourceModule;
    }
    if ($this->hasColumn('source_system')) {
      $columns[] = 'source_system';
      $values[] = $sourceSystem;
    }
    if ($this->hasColumn('source_reference')) {
      $columns[] = 'source_reference';
      $values[] = $sourceReference;
    }
    if ($this->hasColumn('source_payload')) {
      $columns[] = 'source_payload';
      $values[] = $sourcePayload;
    }

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $stmt = $this->pdo->prepare("INSERT INTO maintenance_logs (" . implode(',', $columns) . ") VALUES ($placeholders)");
    $stmt->execute($values);
  }

  public function findBySource(string $sourceModule, string $sourceReference): array {
    if (!$this->hasColumn('source_module') || !$this->hasColumn('source_reference')) {
      return [];
    }
    $stmt = $this->pdo->prepare("
      SELECT *
      FROM maintenance_logs
      WHERE source_module = ?
        AND source_reference = ?
      ORDER BY id ASC
    ");
    $stmt->execute([$sourceModule, $sourceReference]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
