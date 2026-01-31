<?php
class ProjectResource {
  private $pdo;
  public function __construct($pdo){ $this->pdo = $pdo; }

  public function getByProject(int $projectId): array {
    $stmt = $this->pdo->prepare("
      SELECT r.*,
        u.fullname AS user_name,
        f.vehicle_name AS vehicle_name, f.plate_number AS plate_number,
        a.asset_tag AS asset_tag, a.asset_name AS asset_name
      FROM project_resources r
      LEFT JOIN users  u ON (r.resource_type='User'  AND u.id=r.resource_id)
      LEFT JOIN fleet  f ON (r.resource_type='Fleet' AND f.id=r.resource_id)
      LEFT JOIN assets a ON (r.resource_type='Asset' AND a.id=r.resource_id)
      WHERE r.project_id=?
      ORDER BY r.created_at DESC
    ");
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
  }

  public function create($projectId,$type,$resourceId,$roleLabel,$from,$to,$notes): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO project_resources
        (project_id,resource_type,resource_id,role_label,allocated_from,allocated_to,notes)
      VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->execute([$projectId,$type,$resourceId,$roleLabel,$from ?: null,$to ?: null,$notes]);
  }

  public function delete(int $id): void {
    $stmt = $this->pdo->prepare("DELETE FROM project_resources WHERE id=?");
    $stmt->execute([$id]);
  }
}
