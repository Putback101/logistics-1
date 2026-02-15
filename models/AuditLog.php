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
      INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta)
      VALUES (?, ?, ?, ?, ?)
    ");

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $meta = [
      'old_values' => $oldValues,
      'new_values' => $newValues,
      'ip_address' => $ip,
      'user_agent' => $ua
    ];

    $stmt->execute([
      $actorUserId,
      $action,
      $entityType,
      $entityId,
      json_encode($meta)
    ]);
  }
}
