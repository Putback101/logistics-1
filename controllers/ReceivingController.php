<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/flash.php";
require_once __DIR__ . "/../config/permissions.php";
require_once __DIR__ . "/../config/workflow.php";
require_once __DIR__ . "/../config/policy.php";

require_once __DIR__ . "/../models/Receiving.php";
require_once __DIR__ . "/../models/PurchaseOrder.php";
require_once __DIR__ . "/../models/Procurement.php";
require_once __DIR__ . "/../models/Budget.php";

requireLogin();
requireRole(['admin','manager','warehouse_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canEdit = hasPermission($userRole, 'warehousing', 'edit');
if (!$canEdit) {
  http_response_code(403);
  set_flash('error', 'You are not allowed to receive goods.');
  header("Location: ../views/warehousing/inventory.php?tab=inventory");
  exit;
}

$recv = new Receiving($pdo);
$poModel = new PurchaseOrder($pdo);
$procurementModel = new Procurement($pdo);
$budgetModel = new Budget($pdo);

if (!isset($_POST['receive'])) {
  header("Location: ../views/warehousing/inventory.php?tab=inventory");
  exit;
}

$poId = $_POST['po_id'] ?? '';
$items = $_POST['items'] ?? [];

$qcStatus = strtoupper(trim($_POST['qc_status'] ?? 'PASS'));
$qcNotes  = trim($_POST['qc_notes'] ?? '');

if (!ctype_digit((string)$poId)) {
  set_flash('error', 'Please select a Purchase Order.');
  header("Location: ../views/warehousing/inventory.php?tab=inventory"); exit;
}
$poId = (int)$poId;

if (!in_array($qcStatus, ['PASS','FAIL'], true)) $qcStatus = 'PASS';
if ($qcStatus === 'FAIL' && $qcNotes === '') {
  set_flash('error', 'QC Notes is required when QC Status is FAIL.');
  header("Location: ../views/warehousing/inventory.php?tab=inventory"); exit;
}
if ($qcNotes === '') $qcNotes = null;

if (!is_array($items) || count($items) === 0) {
  set_flash('error', 'Please select a PO with items to receive.');
  header("Location: ../views/warehousing/inventory.php?tab=inventory"); exit;
}

// fetch PO for workflow validation
$poRow = $poModel->getById($poId);
if (!$poRow) {
  set_flash('error', 'PO not found.');
  header("Location: ../views/warehousing/inventory.php?tab=inventory"); exit;
}

// Only allow receiving when PO is Sent (workflow-enforced).
if (!in_array($poRow['status'], ['Sent'], true)) {
  set_flash('error', 'This PO is not available for receiving.');
  header("Location: ../views/warehousing/inventory.php?tab=inventory"); exit;
}

$pdo->beginTransaction();

try {
  $validCount = 0;

  foreach ($items as $it) {
    $itemIdRaw = $it['item_id'] ?? '';
    $itemId = ctype_digit((string)$itemIdRaw) ? (int)$itemIdRaw : 0;

    $name = trim((string)($it['name'] ?? ''));
    $qty  = (int)($it['qty'] ?? 0);

    if ($name === '' && $itemId > 0) {
      $nameStmt = $pdo->prepare("SELECT item_name FROM items WHERE id = ? LIMIT 1");
      $nameStmt->execute([$itemId]);
      $name = trim((string)($nameStmt->fetch(PDO::FETCH_ASSOC)['item_name'] ?? ''));
    }

    if ($name === '' || $qty <= 0) continue;
    $validCount++;

    // Record receiving (with QC)
    $recv->create($poId, $name, $qty, (int)$_SESSION['user']['id'], $qcStatus, $qcNotes);

    // Update inventory ONLY if QC PASS
    if ($qcStatus === 'PASS') {
      $inv = null;
      if ($itemId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM inventory WHERE item_id = ? LIMIT 1");
        $stmt->execute([$itemId]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
      }

      if (!$inv) {
        $stmt = $pdo->prepare("SELECT id FROM inventory WHERE item_name = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$name]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
      }

      if ($inv) {
        if ($itemId > 0) {
          $pdo->prepare("UPDATE inventory SET stock = stock + ?, item_id = COALESCE(item_id, ?), item_name = ? WHERE id = ?")
              ->execute([$qty, $itemId, $name, (int)$inv['id']]);
        } else {
          $pdo->prepare("UPDATE inventory SET stock = stock + ? WHERE id = ?")
              ->execute([$qty, (int)$inv['id']]);
        }
      } else {
        if ($itemId > 0) {
          $pdo->prepare("INSERT INTO inventory (item_name, stock, location, item_id) VALUES (?,?,?,?)")
              ->execute([$name, $qty, 'Main Warehouse', $itemId]);
        } else {
          $pdo->prepare("INSERT INTO inventory (item_name, stock, location) VALUES (?,?,?)")
              ->execute([$name, $qty, 'Main Warehouse']);
        }
      }
    } else {
      $pdo->prepare("\n        INSERT INTO supplier_returns (po_id, item_name, quantity, reason, created_by)\n        VALUES (?,?,?,?,?)\n      ")->execute([$poId, $name, $qty, ($qcNotes ?? 'QC failed'), (int)$_SESSION['user']['id']]);
    }
  }

  if ($validCount === 0) {
    $pdo->rollBack();
    set_flash('error', 'No valid receiving quantities were submitted.');
    header("Location: ../views/warehousing/inventory.php?tab=inventory"); exit;
  }

  if ($qcStatus === 'FAIL') {
    if (wf_po_can_transition($poRow['status'], 'Returned')) {
      $poModel->setStatus($poId, 'Returned');
      audit_log($pdo, "Receiving QC FAIL: PO marked Returned", "purchase_orders", $poId, ['qc'=>'FAIL']);
    }
  } else {
    $stmt = $pdo->prepare("\n      SELECT COUNT(*) AS remaining_lines\n      FROM purchase_order_items i\n      LEFT JOIN (\n        SELECT po_id, item_name, SUM(quantity_received) AS received_sum\n        FROM receiving\n        WHERE po_id = ?\n          AND (qc_status IS NULL OR qc_status = 'PASS')\n        GROUP BY po_id, item_name\n      ) r\n        ON r.po_id = i.po_id AND r.item_name = i.item_name\n      WHERE i.po_id = ?\n        AND (i.quantity - COALESCE(r.received_sum, 0)) > 0\n    ");
    $stmt->execute([$poId, $poId]);
    $remaining = (int)($stmt->fetch(PDO::FETCH_ASSOC)['remaining_lines'] ?? 0);

    if ($remaining === 0) {
      if (wf_po_can_transition($poRow['status'], 'Received')) {
        $poModel->setStatus($poId, 'Received');

        // Post budget spent once when PO is fully received.
        $alreadyPostedStmt = $pdo->prepare("\n          SELECT COUNT(*) c\n          FROM audit_logs\n          WHERE entity_type = 'purchase_orders'\n            AND entity_id = ?\n            AND action LIKE 'Budget spent posted for PO%'\n        ");
        $alreadyPostedStmt->execute([$poId]);
        $alreadyPosted = ((int)($alreadyPostedStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;

        if (!$alreadyPosted) {
          $budgetYear = 0;

          if (!empty($poRow['procurement_id']) && ctype_digit((string)$poRow['procurement_id'])) {
            $procRow = $procurementModel->getById((int)$poRow['procurement_id']);
            $budgetYear = (int)($procRow['budget_year'] ?? 0);
          }

          if ($budgetYear <= 0 && !empty($poRow['po_number'])) {
            $q = $pdo->prepare("SELECT budget_year FROM procurement WHERE po_number = ? ORDER BY id DESC LIMIT 1");
            $q->execute([(string)$poRow['po_number']]);
            $budgetYear = (int)($q->fetch(PDO::FETCH_ASSOC)['budget_year'] ?? 0);
          }

          $poTotal = (float)($poRow['total_amount'] ?? 0);
          if ($budgetYear > 0 && $poTotal > 0) {
            $budgetModel->addSpent($budgetYear, $poTotal);
            audit_log($pdo, "Budget spent posted for PO ID $poId (Year $budgetYear, Amount PHP " . number_format($poTotal, 2) . ")", "purchase_orders", $poId, [
              'budget_year' => $budgetYear,
              'amount' => $poTotal
            ]);
          }
        }

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

  header("Location: ../views/warehousing/inventory.php?tab=inventory");
  exit;

} catch (Throwable $e) {
  $pdo->rollBack();
  set_flash('error', "Failed: " . $e->getMessage());
  header("Location: ../views/warehousing/inventory.php?tab=inventory");
  exit;
}




