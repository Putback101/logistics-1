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
  $poNumber = trim($_POST['po_number'] ?? '');
  if ($poNumber === '') {
    set_flash('error', 'PO Number is required.');
    header("Location: ../views/purchase_orders.php"); exit;
  }

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
  audit_log($pdo, "Created purchase order ($poNumber)", "purchase_orders", (int)$newId);

  set_flash('success', 'Purchase order created.');
  header("Location: ../views/purchase_order_view.php?id=$newId"); exit;
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
  header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
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
  header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
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
  header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
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
  header("Location: ../views/purchase_order_view.php?id=$poId"); exit;
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
