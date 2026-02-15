<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";

require "../config/workflow.php";
require "../config/policy.php";

require "../models/PurchaseOrder.php";
require "../models/Supplier.php";
require "../models/Procurement.php";

requireLogin();
requireRole(['admin','manager','procurement_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'procurement', 'add');
$canEdit = hasPermission($userRole, 'procurement', 'edit');
$canDelete = hasPermission($userRole, 'procurement', 'delete');

$po = new PurchaseOrder($pdo);
$supplierModel = new Supplier($pdo);
$procurementModel = new Procurement($pdo);

/* GENERATE PO FROM APPROVED PROCUREMENT REQUEST */
if (isset($_POST['generate_po_from_procurement'])) {
  if (!$canAdd) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to create purchase orders.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $requestId = $_POST['procurement_id'] ?? '';
  if (!ctype_digit((string)$requestId)) {
    set_flash('error', 'Invalid procurement request.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $request = $procurementModel->getById((int)$requestId);
  if (!$request) {
    set_flash('error', 'Procurement request not found.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  if (($request['status'] ?? '') !== 'Approved') {
    set_flash('error', 'Only Approved procurement requests can generate a PO.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  if (!empty($request['po_number'])) {
    set_flash('error', 'This procurement request is already linked to a PO.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }
  $existingLinkedPo = $po->getByProcurementId((int)$requestId);
  if (!empty($existingLinkedPo)) {
    set_flash('error', 'This procurement request is already linked to a PO.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $supplierName = trim((string)($request['supplier'] ?? ''));
  $supplierId = 0;
  if ($supplierName !== '') {
    foreach ($supplierModel->getAll() as $s) {
      if (strcasecmp((string)$s['name'], $supplierName) === 0) {
        $supplierId = (int)$s['id'];
        break;
      }
    }
  }

  $poNumber = $po->nextNumber();
  $newId = $po->createFromProcurement(
    $poNumber,
    $supplierId,
    $supplierName,
    (int)$_SESSION['user']['id'],
    (int)$requestId
  );
  $po->addItem($newId, (string)$request['item_name'], (int)$request['quantity'], 0, isset($request['item_id']) && ctype_digit((string)$request['item_id']) ? (int)$request['item_id'] : null);
  $procurementModel->setPoNumber((int)$requestId, $poNumber);

  audit_log($pdo, "Generated PO ($poNumber) from procurement request ID $requestId", "purchase_orders", (int)$newId);
  set_flash('success', 'Purchase order generated from approved request.');
  header("Location: ../views/procurement/procurement.php?tab=purchase-orders&po_id=$newId&mode=edit"); exit;
}

/* CREATE PO */
if (isset($_POST['add_po'])) {
  set_flash('error', 'Direct PO creation is disabled. Create and approve a procurement request first.');
  header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
}

/* ADD ITEM (blocked if locked / not allowed) */
if (isset($_POST['add_item'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to edit purchase orders.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $poId = $_POST['po_id'] ?? '';
  $item = trim($_POST['item_name'] ?? '');
  $qty  = (int)($_POST['quantity'] ?? 0);
  $unit = (float)($_POST['unit_cost'] ?? 0);

  if (!ctype_digit((string)$poId) || $item === '' || $qty <= 0 || $unit < 0) {
    set_flash('error', 'Invalid item data.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error', 'PO not found.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  if (!can_edit_po($row)) {
    set_flash('error', 'This PO is locked or you are not allowed to edit it.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders?id=$poId&mode=view"); exit;
  }

  $po->addItem((int)$poId, $item, $qty, $unit);
  audit_log($pdo, "Added PO item ($item) qty=$qty unit=$unit", "purchase_orders", (int)$poId);

  set_flash('success', 'Item added successfully.');
  $return = $_POST['return'] ?? '';
  $safeReturn = "../views/procurement/procurement.php?tab=purchase-orders?id=$poId&mode=view";
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (
      str_starts_with($return, 'procurement.php') ||
      str_starts_with($return, 'purchase_orders.php')
    ) {
      $safeReturn = '../views/procurement/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* UPDATE PO (Supplier only) */
if (isset($_POST['update_po'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to edit purchase orders.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $poId = $_POST['po_id'] ?? '';
  $supplierId = $_POST['supplier_id'] ?? '';
  $supplierId = ctype_digit((string)$supplierId) ? (int)$supplierId : 0;

  if (!ctype_digit((string)$poId) || $supplierId <= 0) {
    set_flash('error', 'Invalid PO update data.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error', 'PO not found.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  if (!in_array($row['status'] ?? '', ['Draft','Approved'], true)) {
    set_flash('error', 'PO can only be edited while Draft or Approved.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $supplierName = $supplierModel->getNameById($supplierId) ?? '';
  if ($supplierName === '') {
    set_flash('error', 'Selected supplier not found.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $po->updateSupplier((int)$poId, $supplierId, $supplierName);
  audit_log($pdo, "Updated PO supplier", "purchase_orders", (int)$poId);

  set_flash('success', 'Purchase order updated.');
  $return = $_POST['return'] ?? '';
  $safeReturn = '../views/procurement/procurement.php?tab=purchase-orders';
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (str_starts_with($return, 'procurement.php') || str_starts_with($return, 'purchase_orders.php')) {
      $safeReturn = '../views/procurement/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* UPDATE PO + ITEMS (Draft / Approved only) */
if (isset($_POST['update_po_full'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to edit purchase orders.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $poId = $_POST['po_id'] ?? '';
  $supplierId = $_POST['supplier_id'] ?? '';
  $status = $_POST['status'] ?? '';
  $items = $_POST['items'] ?? [];

  $supplierId = ctype_digit((string)$supplierId) ? (int)$supplierId : 0;
  if (!ctype_digit((string)$poId) || $supplierId <= 0) {
    set_flash('error', 'Invalid PO update data.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error', 'PO not found.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  if (!in_array($row['status'] ?? '', ['Draft','Approved'], true)) {
    set_flash('error', 'PO can only be edited while Draft or Approved.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  if (!in_array($status, ['Draft','Approved'], true)) {
    set_flash('error', 'Invalid status change.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $supplierName = $supplierModel->getNameById($supplierId) ?? '';
  if ($supplierName === '') {
    set_flash('error', 'Selected supplier not found.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
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
        header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
      }

      $po->updateItem((int)$itemId, $itemName, $qty, $unit);
    }
  }

  $po->recomputeTotal((int)$poId);
  $po->setStatus((int)$poId, $status);

  audit_log($pdo, "Updated PO details", "purchase_orders", (int)$poId);

  set_flash('success', 'Purchase order updated.');
  $return = $_POST['return'] ?? '';
  $safeReturn = '../views/procurement/procurement.php?tab=purchase-orders';
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (str_starts_with($return, 'procurement.php') || str_starts_with($return, 'purchase_orders.php')) {
      $safeReturn = '../views/procurement/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}


/* REQUEST APPROVAL: Draft/Rejected -> Pending Approval */
if (isset($_POST['request_approval'])) {
  $poId = $_POST['po_id'] ?? '';
  if (!ctype_digit((string)$poId)) { header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit; }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error','PO not found');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  if (!can_po_action('request_approval', $row)) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to request approval for this PO.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders&id=$poId&mode=view"); exit;
  }

  $next = wf_po_next('request_approval');
  if (!$next || !wf_po_can_transition((string)$row['status'], $next)) {
    set_flash('error', "Invalid workflow: {$row['status']} to $next");
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders&id=$poId&mode=view"); exit;
  }

  $po->setStatus((int)$poId, $next);
  audit_log($pdo, "PO moved to Pending Approval", "purchase_orders", (int)$poId);
  set_flash('success', 'PO submitted for approval.');

  $return = $_POST['return'] ?? '';
  $safeReturn = "../views/procurement/procurement.php?tab=purchase-orders&id=$poId&mode=view";
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (str_starts_with($return, 'procurement.php') || str_starts_with($return, 'purchase_orders.php')) {
      $safeReturn = '../views/procurement/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* APPROVE / REJECT: Pending Approval -> Approved/Rejected */
if (isset($_POST['approve']) || isset($_POST['reject'])) {
  $poId = $_POST['po_id'] ?? '';
  if (!ctype_digit((string)$poId)) { header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit; }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error','PO not found');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $action = isset($_POST['approve']) ? 'approve' : 'reject';
  if (!can_po_action($action, $row)) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to approve/reject this PO.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders&id=$poId&mode=view"); exit;
  }

  $next = wf_po_next($action);
  if (!$next || !wf_po_can_transition((string)$row['status'], $next)) {
    set_flash('error', "Invalid workflow: {$row['status']} to $next");
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders&id=$poId&mode=view"); exit;
  }

  $po->setStatus((int)$poId, $next);
  $remarks = trim((string)($_POST['remarks'] ?? ''));
  audit_log($pdo, strtoupper($action) . " PO", "purchase_orders", (int)$poId, ['remarks' => $remarks]);
  set_flash('success', $action === 'approve' ? 'PO approved.' : 'PO rejected.');

  $return = $_POST['return'] ?? '';
  $safeReturn = "../views/procurement/procurement.php?tab=purchase-orders&id=$poId&mode=view";
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (str_starts_with($return, 'procurement.php') || str_starts_with($return, 'purchase_orders.php')) {
      $safeReturn = '../views/procurement/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}
/* SEND TO SUPPLIER -> Approved -> Sent */
if (isset($_POST['send_to_supplier'])) {
  $poId = $_POST['po_id'] ?? '';
  if (!ctype_digit((string)$poId)) { header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit; }

  $row = $po->getById((int)$poId);
  if (!$row) {
    set_flash('error','PO not found');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  if (!can_po_action('send_to_supplier', $row)) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to send this PO to supplier.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders?id=$poId&mode=view"); exit;
  }

  $next = wf_po_next('send_to_supplier'); // Sent
  if (!$next || !wf_po_can_transition($row['status'], $next)) {
    set_flash('error', "Invalid workflow: {$row['status']} to $next");
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders?id=$poId&mode=view"); exit;
  }

  $po->setStatus((int)$poId, $next);
  audit_log($pdo, "PO sent to supplier", "purchase_orders", (int)$poId);

  set_flash('success', 'PO marked as Sent.');
  $return = $_POST['return'] ?? '';
  $safeReturn = "../views/procurement/procurement.php?tab=purchase-orders?id=$poId&mode=view";
  if (is_string($return)) {
    $return = ltrim($return, '/');
    if (str_starts_with($return, 'views/')) {
      $return = substr($return, 6);
    }
    if (
      str_starts_with($return, 'procurement.php') ||
      str_starts_with($return, 'purchase_orders.php')
    ) {
      $safeReturn = '../views/procurement/' . $return;
    }
  }
  header("Location: $safeReturn"); exit;
}

/* DELETE PO */
if (isset($_GET['delete'])) {
  if (!$canDelete) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to delete purchase orders.');
    header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
  }

  $id = $_GET['delete'];
  if (!ctype_digit((string)$id)) { header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit; }

  $po->delete((int)$id);
  audit_log($pdo, "Deleted purchase order ID $id", "purchase_orders", (int)$id);

  set_flash('success', 'Purchase order deleted successfully.');
  header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;
}

header("Location: ../views/procurement/procurement.php?tab=purchase-orders"); exit;



