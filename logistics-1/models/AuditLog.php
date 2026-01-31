<?php
// /models/AuditLog.php

class AuditLog {
  private PDO $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function log(
    int $actorUserId,
    string $action,
    string $entityType,
    int $entityId,
    ?array $oldValues,
    ?array $newValues
  ): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt->execute([
      $actorUserId,
      $action,
      $entityType,
      $entityId,
      $oldValues ? json_encode($oldValues) : null,
      $newValues ? json_encode($newValues) : null,
      $ip,
      $ua
    ]);
  }
}
