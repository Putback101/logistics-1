<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";

requireLogin();
requireRole(['admin','manager','warehouse_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canEdit = hasPermission($userRole, 'warehousing', 'edit');

if (isset($_POST['reconcile'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to reconcile stock.');
    header("Location: ../views/warehousing/inventory.php?tab=reconciliation"); exit;
  }

  $itemId = $_POST['item_id'] ?? '';
  $physical = (int)($_POST['physical_stock'] ?? -1);

  if (!ctype_digit((string)$itemId) || $physical < 0) {
    set_flash('error', 'Invalid reconciliation data.');
    header("Location: ../views/warehousing/inventory.php?tab=reconciliation"); exit;
  }

  $itemId = (int)$itemId;

  $stmt = $pdo->prepare("SELECT item_name, stock FROM inventory WHERE id=? LIMIT 1");
  $stmt->execute([$itemId]);
  $invRow = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$invRow) {
    set_flash('error', 'Inventory item not found.');
    header("Location: ../views/warehousing/inventory.php?tab=reconciliation"); exit;
  }

  $itemName = (string)($invRow['item_name'] ?? 'Unknown Item');
  $system = (int)($invRow['stock'] ?? 0);
  $variance = $physical - $system;

  $cols = $pdo->query("SHOW COLUMNS FROM stock_reconciliation")->fetchAll(PDO::FETCH_ASSOC);
  $hasItemId = false;
  $hasItemName = false;
  foreach ($cols as $col) {
    if (($col['Field'] ?? '') === 'item_id') $hasItemId = true;
    if (($col['Field'] ?? '') === 'item_name') $hasItemName = true;
  }

  if ($hasItemId && $hasItemName) {
    $pdo->prepare("INSERT INTO stock_reconciliation (item_id, item_name, system_stock, physical_stock, variance, reconciled_by) VALUES (?,?,?,?,?,?)")
        ->execute([$itemId, $itemName, $system, $physical, $variance, $_SESSION['user']['id']]);
  } elseif ($hasItemId) {
    $pdo->prepare("INSERT INTO stock_reconciliation (item_id, system_stock, physical_stock, variance, reconciled_by) VALUES (?,?,?,?,?)")
        ->execute([$itemId, $system, $physical, $variance, $_SESSION['user']['id']]);
  } elseif ($hasItemName) {
    $pdo->prepare("INSERT INTO stock_reconciliation (item_name, system_stock, physical_stock, variance, reconciled_by) VALUES (?,?,?,?,?)")
        ->execute([$itemName, $system, $physical, $variance, $_SESSION['user']['id']]);
  } else {
    $pdo->prepare("INSERT INTO stock_reconciliation (system_stock, physical_stock, variance, reconciled_by) VALUES (?,?,?,?)")
        ->execute([$system, $physical, $variance, $_SESSION['user']['id']]);
  }

  // update inventory to physical count
  $pdo->prepare("UPDATE inventory SET stock=? WHERE id=?")->execute([$physical, $itemId]);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Reconciled inventory item ID $itemId (variance $variance)"]);

  set_flash('success', 'Stock reconciled successfully.');
  header("Location: ../views/warehousing/inventory.php?tab=reconciliation"); exit;
}

header("Location: ../views/warehousing/inventory.php?tab=reconciliation"); exit;

