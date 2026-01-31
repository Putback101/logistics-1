<?php
// logistics-1/config/policy.php

function role(): string {
  return (string)($_SESSION['user']['role'] ?? '');
}

function user_id(): int {
  return (int)($_SESSION['user']['id'] ?? 0);
}

/**
 * Staff should be view-only for PO.
 * Procurement/Manager can work on PO before it’s locked.
 * Admin can do anything.
 */
function can_edit_po(array $po): bool {
  $r = role();

  if ($r === 'admin') return true;

  // lock blocks all non-admin edits
  if (!empty($po['is_locked'])) return false;

  // manager/procurement can edit only while not Approved/Sent/Received
  if (in_array($r, ['manager','procurement'], true)) {
    return in_array($po['status'], ['Draft','Pending Approval','Rejected'], true);
  }

  return false;
}

/**
 * Who can perform workflow actions.
 */
function can_po_action(string $action, array $po): bool {
  $r = role();

  if ($r === 'admin') return true;

  if (in_array($r, ['manager','procurement'], true)) {
    // they can request approval and mark sent after approved
    return in_array($action, ['request_approval','send_to_supplier'], true);
  }

  if ($r === 'warehouse') {
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
  $stmt = $pdo->prepare("
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta)
    VALUES (?,?,?,?,?)
  ");
  $stmt->execute([
    user_id(),
    $action,
    $entityType,
    $entityId,
    $meta ? json_encode($meta) : null
  ]);
}
