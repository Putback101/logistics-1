<?php
// logistics-1/config/policy.php

require_once __DIR__ . '/workflow.php';

function role(): string {
  return (string)($_SESSION['user']['role'] ?? '');
}

function user_id(): int {
  return (int)($_SESSION['user']['id'] ?? 0);
}

/**
 * Procurement staff are add/view only for PO flow.
 * Manager can edit while PO is still editable and unlocked.
 * Admin can do anything.
 */
function can_edit_po(array $po): bool {
  $r = role();

  if ($r === 'admin') return true;

  if (!empty($po['is_locked'])) return false;

  if ($r === 'manager') {
    return in_array($po['status'] ?? '', ['Draft','Approved','Rejected'], true);
  }

  return false;
}

/**
 * Workflow action permissions.
 */
function can_po_action(string $action, array $po): bool {
  $r = role();
  $status = (string)($po['status'] ?? '');
  $target = wf_po_next($action);

  if ($target === null || !wf_po_can_transition($status, $target)) {
    return false;
  }

  if ($r === 'admin') return true;

  if ($r === 'manager') {
    return in_array($action, ['request_approval', 'approve', 'reject', 'send_to_supplier'], true);
  }

  if ($r === 'warehouse_staff') {
    return $action === 'receive_goods';
  }

  return false;
}

/**
 * Backwards-compatible audit log:
 * still writes user_id + action like your project,
 * but also fills entity fields if available.
 */
function audit_log(PDO $pdo, string $action, ?string $entityType=null, ?int $entityId=null, ?array $meta=null): void {
  $stmt = $pdo->prepare("\n    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta)\n    VALUES (?,?,?,?,?)\n  ");
  $stmt->execute([
    user_id(),
    $action,
    $entityType,
    $entityId,
    $meta ? json_encode($meta) : null
  ]);
}
