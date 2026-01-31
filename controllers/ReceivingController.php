<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/flash.php";
require_once __DIR__ . "/../config/workflow.php";
require_once __DIR__ . "/../config/policy.php";

require_once __DIR__ . "/../models/Receiving.php";
require_once __DIR__ . "/../models/PurchaseOrder.php";

requireLogin();
requireRole(['admin','manager','warehouse']);

$recv = new Receiving($pdo);
$poModel = new PurchaseOrder($pdo);

if (!isset($_POST['receive'])) {
  header("Location: ../views/receiving.php");
  exit;
}

$poId = $_POST['po_id'] ?? '';
$items = $_POST['items'] ?? [];

$qcStatus = strtoupper(trim($_POST['qc_status'] ?? 'PASS'));
$qcNotes  = trim($_POST['qc_notes'] ?? '');

if (!ctype_digit((string)$poId)) {
  set_flash('error', 'Please select a Purchase Order.');
  header("Location: ../views/receiving.php"); exit;
}
$poId = (int)$poId;

if (!in_array($qcStatus, ['PASS','FAIL'], true)) $qcStatus = 'PASS';
if ($qcStatus === 'FAIL' && $qcNotes === '') {
  set_flash('error', 'QC Notes is required when QC Status is FAIL.');
  header("Location: ../views/receiving.php"); exit;
}
if ($qcNotes === '') $qcNotes = null;

if (!is_array($items) || count($items) === 0) {
  set_flash('error', 'Please select a PO with items to receive.');
  header("Location: ../views/receiving.php"); exit;
}

// fetch PO for workflow validation
$poRow = $poModel->getById($poId);
if (!$poRow) {
  set_flash('error', 'PO not found.');
  header("Location: ../views/receiving.php"); exit;
}

// Only allow receiving if PO is Sent or Returned (based on your dropdown rule)
if (!in_array($poRow['status'], ['Sent','Returned'], true)) {
  set_flash('error', 'This PO is not available for receiving.');
  header("Location: ../views/receiving.php"); exit;
}

$pdo->beginTransaction();

try {
  $validCount = 0;

  foreach ($items as $it) {
    $name = trim($it['name'] ?? '');
    $qty  = (int)($it['qty'] ?? 0);

    if ($name === '' || $qty <= 0) continue;
    $validCount++;

    // Record receiving (with QC)
    $recv->create($poId, $name, $qty, (int)$_SESSION['user']['id'], $qcStatus, $qcNotes);

    // Update inventory ONLY if QC PASS
    if ($qcStatus === 'PASS') {
      $stmt = $pdo->prepare("SELECT id FROM inventory WHERE item_name=? LIMIT 1");
      $stmt->execute([$name]);
      $inv = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($inv) {
        $pdo->prepare("UPDATE inventory SET stock = stock + ? WHERE id=?")
            ->execute([$qty, (int)$inv['id']]);
      } else {
        $pdo->prepare("INSERT INTO inventory (item_name, stock, location) VALUES (?,?,?)")
            ->execute([$name, $qty, 'Main Warehouse']);
      }
    } else {
      // Optional: store supplier return item if you created supplier_returns table
      // If you didn't create the table, comment this block out.
      $pdo->prepare("
        INSERT INTO supplier_returns (po_id, item_name, quantity, reason, created_by)
        VALUES (?,?,?,?,?)
      ")->execute([$poId, $name, $qty, ($qcNotes ?? 'QC failed'), (int)$_SESSION['user']['id']]);
    }
  }

  if ($validCount === 0) {
    $pdo->rollBack();
    set_flash('error', 'No valid receiving quantities were submitted.');
    header("Location: ../views/receiving.php"); exit;
  }

  // Update PO status:
  // - If FAIL → Sent/Returned -> Returned
  // - If PASS → set Received only when ALL items are fully received
  if ($qcStatus === 'FAIL') {
    if (wf_po_can_transition($poRow['status'], 'Returned')) {
      $poModel->setStatus($poId, 'Returned');
      audit_log($pdo, "Receiving QC FAIL: PO marked Returned", "purchase_orders", $poId, ['qc'=>'FAIL']);
    }
  } else {
    // PASS: check if fully received
    $stmt = $pdo->prepare("
      SELECT COUNT(*) AS remaining_lines
      FROM purchase_order_items i
      LEFT JOIN (
        SELECT po_id, item_name, SUM(quantity_received) AS received_sum
        FROM receiving
        WHERE po_id = ?
          AND (qc_status IS NULL OR qc_status = 'PASS')
        GROUP BY po_id, item_name
      ) r
        ON r.po_id = i.po_id AND r.item_name = i.item_name
      WHERE i.po_id = ?
        AND (i.quantity - COALESCE(r.received_sum, 0)) > 0
    ");
    $stmt->execute([$poId, $poId]);
    $remaining = (int)($stmt->fetch(PDO::FETCH_ASSOC)['remaining_lines'] ?? 0);

    if ($remaining === 0) {
      if (wf_po_can_transition($poRow['status'], 'Received')) {
        $poModel->setStatus($poId, 'Received');
        audit_log($pdo, "Receiving complete: PO marked Received", "purchase_orders", $poId, ['qc'=>'PASS']);
      }
    } else {
      audit_log($pdo, "Receiving partial: PO still has remaining items", "purchase_orders", $poId, ['qc'=>'PASS','remaining_lines'=>$remaining]);
    }
  }

  audit_log($pdo, "Receiving batch saved", "receiving", null, [
    'po_id' => $poId,
    'qc' => $qcStatus,
    'items_count' => $validCount
  ]);

  $pdo->commit();

  set_flash('success', $qcStatus === 'PASS'
    ? 'Receiving saved. Inventory updated for PASS items.'
    : 'Receiving saved. QC FAIL: PO marked Returned and inventory not updated.'
  );

  header("Location: ../views/receiving.php");
  exit;

} catch (Throwable $e) {
  $pdo->rollBack();
  set_flash('error', "Failed: " . $e->getMessage());
  header("Location: ../views/receiving.php");
  exit;
}
