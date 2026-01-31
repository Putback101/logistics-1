<?php
class ProjectTask {
  private $pdo;
  public function __construct($pdo){ $this->pdo = $pdo; }

  public function getByProject(int $projectId): array {
    $stmt = $this->pdo->prepare("
      SELECT
        t.*,
        u.fullname AS assigned_user,
        f.vehicle_name AS assigned_vehicle,
        f.plate_number AS assigned_plate,
        a.asset_tag AS assigned_asset_tag,
        a.asset_name AS assigned_asset_name
      FROM project_tasks t
      LEFT JOIN users  u ON u.id = t.assigned_user_id
      LEFT JOIN fleet  f ON f.id = t.assigned_fleet_id
      LEFT JOIN assets a ON a.id = t.assigned_asset_id
      WHERE t.project_id=?
      ORDER BY COALESCE(t.due_date,'9999-12-31') ASC, t.id DESC
    ");
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
  }

  public function create(array $d): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO project_tasks
        (project_id, title, description, start_date, due_date, priority, status,
         assigned_user_id, assigned_fleet_id, assigned_asset_id)
      VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
      (int)$d['project_id'],
      trim($d['title'] ?? ''),
      trim($d['description'] ?? ''),
      $d['start_date'] ?: null,
      $d['due_date'] ?: null,
      $d['priority'] ?? 'Medium',
      $d['status'] ?? 'Todo',
      ($d['assigned_user_id'] ?? '') !== '' ? (int)$d['assigned_user_id'] : null,
      ($d['assigned_fleet_id'] ?? '') !== '' ? (int)$d['assigned_fleet_id'] : null,
      ($d['assigned_asset_id'] ?? '') !== '' ? (int)$d['assigned_asset_id'] : null
    ]);
  }

  public function delete(int $id): void {
    $stmt = $this->pdo->prepare("DELETE FROM project_tasks WHERE id=?");
    $stmt->execute([$id]);
  }
}
