<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";

requireLogin();
requireRole(['admin','manager','warehouse']);


if (isset($_POST['reconcile'])) {
  $itemId = $_POST['item_id'] ?? '';
  $physical = (int)($_POST['physical_stock'] ?? -1);

  if (!ctype_digit((string)$itemId) || $physical < 0) {
    set_flash('error', 'Invalid reconciliation data.');
    header("Location: ../views/stock_reconciliation.php"); exit;
  }

  $stmt = $pdo->prepare("SELECT stock FROM inventory WHERE id=?");
  $stmt->execute([(int)$itemId]);
  $system = (int)$stmt->fetchColumn();

  $variance = $physical - $system;

  $pdo->prepare("INSERT INTO stock_reconciliation (item_id, system_stock, physical_stock, variance, reconciled_by) VALUES (?,?,?,?,?)")
      ->execute([(int)$itemId, $system, $physical, $variance, $_SESSION['user']['id']]);

  // update inventory to physical count
  $pdo->prepare("UPDATE inventory SET stock=? WHERE id=?")->execute([$physical, (int)$itemId]);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Reconciled inventory item ID $itemId (variance $variance)"]);

  set_flash('success', 'Stock reconciled successfully.');
  header("Location: ../views/stock_reconciliation.php"); exit;
}

header("Location: ../views/stock_reconciliation.php"); exit;
