<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";

require "../config/workflow.php";
require "../config/policy.php";

require "../models/PurchaseOrder.php";
require "../models/Approval.php";
require "../models/Budget.php";
require "../models/Supplier.php";

requireLogin();
requireRole(['admin','manager','procurement']);

$po = new PurchaseOrder($pdo);
$approval = new Approval($pdo);
$budget = new Budget($pdo);
$supplierModel = new Supplier($pdo);

/* CREATE PO */
if (isset($_POST['add_po'])) {
  $poNumber = $po->nextNumber();

  $supplierId = $_POST['supplier_id'] ?? '';
  $supplierId = ctype_digit((string)$supplierId) ? (int)$supplierId : 0;

  $supplierName = '';
  if ($supplierId > 0) {
    $supplierName = $supplierModel->getNameById($supplierId) ?? '';
    if ($supplierName === '') {
      set_flash('error', 'Selected supplier not found.');
      header("Location: ../views/purchase_orders.php"); exit;
    }
  } else {
    $supplierName = trim($_POST['supplier'] ?? '');
    if ($supplierName === '') {
      set_flash('error','Supplier required');
      header("Location: ../views/purchase_orders.php"); exit;
    }
  }

  $newId = $po->create($poNumber, $supplierId, $supplierName, (int)$_SESSION['user']['id']);
  // Clear any stale approvals for this record id (e.g., if IDs were reused)
  $pdo->prepare("DELETE FROM approvals WHERE module='purchase_orders' AND record_id=?")->execute([(int)$newId]);
  // Force Draft + unlocked to avoid mismatched defaults
  try {
    $pdo->prepare("UPDATE purchase_orders SET status='Draft', is_locked=0 WHERE id=?")->execute([(int)$newId]);
  } catch (Exception $e) {
    $pdo->prepare("UPDATE purchase_orders SET status='Draft' WHERE id=?")->execute([(int)$newId]);
  }
  audit_log($pdo, "Created purchase order ($poNumber)", "purchase_orders", (int)$newId);

  set_flash('success', 'Purchase order created.');
  $return = $_POST['return'] ?? '';
  $safeReturn = '../views/purchase_orders.php';
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (str_starts_with($return, 'procurement.php') || str_starts_with($return, 'purchase_orders.php')) {
      $safeReturn = '../views/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* ADD ITEM (blocked if locked / not allowed) */
if (isset($_POST['add_item'])) {
  $poId = $_POST['po_id'] ?? '';
  $item = trim($_POST['item_name'] ?? '');
  $qty  = (int)($_POST['quantity'] ?? 0);
  $unit = (float)($_POST['unit_cost'] ?? 0);

  if (!ctype_digit((string)$poId) || $item === '' || $qty <= 0 || $unit < 0) {
    set_flash('error', 'Invalid item data.');
    header("Location: ../views/purchase_orders.php"); exit;
  }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error', 'PO not found.');
    header("Location: ../views/purchase_orders.php"); exit;
  }

  if (!can_edit_po($row)) {
    set_flash('error', 'This PO is locked or you are not allowed to edit it.');
    header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
  }

  $po->addItem((int)$poId, $item, $qty, $unit);
  audit_log($pdo, "Added PO item ($item) qty=$qty unit=$unit", "purchase_orders", (int)$poId);

  set_flash('success', 'Item added successfully.');
  $return = $_POST['return'] ?? '';
  $safeReturn = "../views/purchase_order_view.php?id=$poId";
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (
      str_starts_with($return, 'procurement.php') ||
      str_starts_with($return, 'purchase_orders.php') ||
      str_starts_with($return, 'purchase_order_edit.php') ||
      str_starts_with($return, 'purchase_order_view.php')
    ) {
      $safeReturn = '../views/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* REQUEST APPROVAL -> Status becomes Pending Approval */
if (isset($_POST['request_approval'])) {
  $poId = $_POST['po_id'] ?? '';
  if (!ctype_digit((string)$poId)) { header("Location: ../views/purchase_orders.php"); exit; }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error','PO not found');
    header("Location: ../views/purchase_orders.php"); exit;
  }

  if (!can_po_action('request_approval', $row)) {
    http_response_code(403);
    exit;
  }

  $next = wf_po_next('request_approval');
  if (!$next || !wf_po_can_transition($row['status'], $next)) {
    set_flash('error', "Invalid workflow: {$row['status']} → $next");
    header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
  }

  $approval->request('purchase_orders', (int)$poId, $_SESSION['user']['id']);
  $po->setStatus((int)$poId, $next);

  audit_log($pdo, "Requested approval (moved to Pending Approval)", "purchase_orders", (int)$poId);

  set_flash('success', 'Approval request sent.');
  $return = $_POST['return'] ?? '';
  $safeReturn = "../views/purchase_order_view.php?id=$poId";
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (
      str_starts_with($return, 'procurement.php') ||
      str_starts_with($return, 'purchase_orders.php') ||
      str_starts_with($return, 'purchase_order_edit.php') ||
      str_starts_with($return, 'purchase_order_view.php')
    ) {
      $safeReturn = '../views/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* UPDATE PO (Supplier only) */
if (isset($_POST['update_po'])) {
  $poId = $_POST['po_id'] ?? '';
  $supplierId = $_POST['supplier_id'] ?? '';
  $supplierId = ctype_digit((string)$supplierId) ? (int)$supplierId : 0;

  if (!ctype_digit((string)$poId) || $supplierId <= 0) {
    set_flash('error', 'Invalid PO update data.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error', 'PO not found.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  if (!in_array($row['status'] ?? '', ['Draft','Pending Approval'], true)) {
    set_flash('error', 'PO can only be edited while Draft or Pending Approval.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  $supplierName = $supplierModel->getNameById($supplierId) ?? '';
  if ($supplierName === '') {
    set_flash('error', 'Selected supplier not found.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  $po->updateSupplier((int)$poId, $supplierId, $supplierName);
  audit_log($pdo, "Updated PO supplier", "purchase_orders", (int)$poId);

  set_flash('success', 'Purchase order updated.');
  $return = $_POST['return'] ?? '';
  $safeReturn = '../views/procurement.php?tab=purchase-orders';
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (str_starts_with($return, 'procurement.php') || str_starts_with($return, 'purchase_orders.php')) {
      $safeReturn = '../views/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* UPDATE PO + ITEMS (Draft / Pending Approval only) */
if (isset($_POST['update_po_full'])) {
  $poId = $_POST['po_id'] ?? '';
  $supplierId = $_POST['supplier_id'] ?? '';
  $status = $_POST['status'] ?? '';
  $items = $_POST['items'] ?? [];

  $supplierId = ctype_digit((string)$supplierId) ? (int)$supplierId : 0;
  if (!ctype_digit((string)$poId) || $supplierId <= 0) {
    set_flash('error', 'Invalid PO update data.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error', 'PO not found.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  if (!in_array($row['status'] ?? '', ['Draft','Pending Approval'], true)) {
    set_flash('error', 'PO can only be edited while Draft or Pending Approval.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  if (!in_array($status, ['Draft','Pending Approval'], true)) {
    set_flash('error', 'Invalid status change.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  $supplierName = $supplierModel->getNameById($supplierId) ?? '';
  if ($supplierName === '') {
    set_flash('error', 'Selected supplier not found.');
    header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
  }

  $po->updateSupplier((int)$poId, $supplierId, $supplierName);

  if (is_array($items)) {
    foreach ($items as $itemId => $data) {
      if (!ctype_digit((string)$itemId)) continue;
      $itemName = trim($data['item_name'] ?? '');
      $qty = (int)($data['quantity'] ?? 0);
      $unit = (float)($data['unit_cost'] ?? 0);

      if ($itemName === '' || $qty <= 0 || $unit < 0) {
        set_flash('error', 'Invalid item data.');
        header("Location: ../views/procurement.php?tab=purchase-orders"); exit;
      }

      $po->updateItem((int)$itemId, $itemName, $qty, $unit);
    }
  }

  $po->recomputeTotal((int)$poId);
  $po->setStatus((int)$poId, $status);

  audit_log($pdo, "Updated PO details", "purchase_orders", (int)$poId);

  set_flash('success', 'Purchase order updated.');
  $return = $_POST['return'] ?? '';
  $safeReturn = '../views/procurement.php?tab=purchase-orders';
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (str_starts_with($return, 'procurement.php') || str_starts_with($return, 'purchase_orders.php')) {
      $safeReturn = '../views/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* SEND TO SUPPLIER -> Approved -> Sent */
if (isset($_POST['send_to_supplier'])) {
  $poId = $_POST['po_id'] ?? '';
  if (!ctype_digit((string)$poId)) { header("Location: ../views/purchase_orders.php"); exit; }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error','PO not found');
    header("Location: ../views/purchase_orders.php"); exit;
  }

  if (!can_po_action('send_to_supplier', $row)) {
    http_response_code(403);
    exit;
  }

  $next = wf_po_next('send_to_supplier'); // Sent
  if (!$next || !wf_po_can_transition($row['status'], $next)) {
    set_flash('error', "Invalid workflow: {$row['status']} → $next (Must be Approved first)");
    header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
  }

  $po->setStatus((int)$poId, $next);
  audit_log($pdo, "PO sent to supplier", "purchase_orders", (int)$poId);

  set_flash('success', 'PO marked as Sent.');
  $return = $_POST['return'] ?? '';
  $safeReturn = "../views/purchase_order_view.php?id=$poId";
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (
      str_starts_with($return, 'procurement.php') ||
      str_starts_with($return, 'purchase_orders.php') ||
      str_starts_with($return, 'purchase_order_edit.php') ||
      str_starts_with($return, 'purchase_order_view.php')
    ) {
      $safeReturn = '../views/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* APPROVE / REJECT (ADMIN ONLY) with lock + transition */
if (isset($_POST['approve']) || isset($_POST['reject'])) {
  requireRole(['admin']);

  $poId = $_POST['po_id'] ?? '';
  $remarks = trim($_POST['remarks'] ?? '');
  if (!ctype_digit((string)$poId)) { header("Location: ../views/purchase_orders.php"); exit; }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error','PO not found');
    header("Location: ../views/purchase_orders.php"); exit;
  }

  $status = isset($_POST['approve']) ? 'Approved' : 'Rejected';
  $approval->act('purchase_orders', (int)$poId, $status, $_SESSION['user']['id'], $remarks);

  if ($status === 'Approved') {
    if (!wf_po_can_transition($row['status'], 'Approved')) {
      set_flash('error', "Invalid workflow: {$row['status']} → Approved");
      header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
    }

    $po->setStatus((int)$poId, 'Approved');
    $pdo->prepare("UPDATE purchase_orders SET is_locked=1 WHERE id=?")->execute([(int)$poId]);

    $year = (int)date('Y');
    $b = $budget->getByYear($year);
    if ($b) $budget->addSpent($year, (float)$row['total_amount']);

    audit_log($pdo, "Approved PO and locked", "purchase_orders", (int)$poId);
  }

  if ($status === 'Rejected') {
    if (!wf_po_can_transition($row['status'], 'Rejected')) {
      set_flash('error', "Invalid workflow: {$row['status']} → Rejected");
      header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
    }

    $po->setStatus((int)$poId, 'Rejected');
    $pdo->prepare("UPDATE purchase_orders SET is_locked=0 WHERE id=?")->execute([(int)$poId]);

    audit_log($pdo, "Rejected PO (unlocked)", "purchase_orders", (int)$poId);
  }

  set_flash('success', "PO $status successfully.");
  $return = $_POST['return'] ?? '';
  $safeReturn = "../views/purchase_order_view.php?id=$poId";
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (
      str_starts_with($return, 'procurement.php') ||
      str_starts_with($return, 'purchase_orders.php') ||
      str_starts_with($return, 'purchase_order_edit.php') ||
      str_starts_with($return, 'purchase_order_view.php')
    ) {
      $safeReturn = '../views/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* DELETE PO */
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  if (!ctype_digit((string)$id)) { header("Location: ../views/purchase_orders.php"); exit; }

  $po->delete((int)$id);
  audit_log($pdo, "Deleted purchase order ID $id", "purchase_orders", (int)$id);

  set_flash('success', 'Purchase order deleted successfully.');
  header("Location: ../views/purchase_orders.php"); exit;
}

header("Location: ../views/purchase_orders.php"); exit;
