<?php
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/permissions.php";
require_once __DIR__ . "/../../models/Inventory.php";
require_once __DIR__ . "/../../models/Receiving.php";

requireLogin();
requireRole(['admin','manager','warehouse_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'warehousing', 'add');
$canEdit = hasPermission($userRole, 'warehousing', 'edit');
$canDelete = hasPermission($userRole, 'warehousing', 'delete');
$canReconcile = $canEdit;

$activeTab = $_GET['tab'] ?? 'inventory';
if (!in_array($activeTab, ['inventory', 'reconciliation'], true)) {
  $activeTab = 'inventory';
}

$inventory = new Inventory($pdo);
$items = $inventory->getAll();
$recv = new Receiving($pdo);
$receivingRows = $recv->getAll();

$receivablePos = $pdo->query("\n  SELECT po.id, po.po_number
  FROM purchase_orders po
  WHERE po.status IN ('Sent')
    AND EXISTS (
      SELECT 1
      FROM purchase_order_items i
      LEFT JOIN (
        SELECT
          po_id,
          item_name,
          SUM(quantity_received) AS received_sum
        FROM receiving
        WHERE qc_status IS NULL OR qc_status = 'PASS'
        GROUP BY po_id, item_name
      ) r
        ON r.po_id = i.po_id
       AND r.item_name = i.item_name
      WHERE i.po_id = po.id
        AND (i.quantity - COALESCE(r.received_sum, 0)) > 0
    )
  ORDER BY po.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$inventoryTotal = count($items);
$inventoryStockTotal = 0;
$inventoryLowStock = 0;
foreach ($items as $invRow) {
  $stock = (int)($invRow['stock'] ?? 0);
  $inventoryStockTotal += $stock;
  if ($stock < 50) $inventoryLowStock++;
}
$receivingLogTotal = count($receivingRows);
$pendingReceipts = count($receivablePos);

// Reconciliation data
$reconItems = $pdo->query("\n  SELECT inv.id, inv.item_name, inv.stock, it.category, it.unit
  FROM inventory inv
  LEFT JOIN items it ON it.id = inv.item_id
  ORDER BY inv.item_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$hasItemId = false;
$hasItemName = false;
$reconCols = $pdo->query("SHOW COLUMNS FROM stock_reconciliation")->fetchAll(PDO::FETCH_ASSOC);
foreach ($reconCols as $c) {
  if (($c['Field'] ?? '') === 'item_id') $hasItemId = true;
  if (($c['Field'] ?? '') === 'item_name') $hasItemName = true;
}

if ($hasItemId) {
  $reconLogs = $pdo->query("\n    SELECT
      r.*,
      inv.item_name AS inventory_item_name,
      it.item_name AS master_item_name,
      it.category AS master_category,
      it.unit AS master_unit
    FROM stock_reconciliation r
    LEFT JOIN inventory inv ON inv.id = r.item_id
    LEFT JOIN items it ON it.id = inv.item_id
    ORDER BY r.reconciled_at DESC
    LIMIT 50
  ")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($hasItemName) {
  $reconLogs = $pdo->query("\n    SELECT
      r.*,
      r.item_name AS inventory_item_name,
      NULL AS master_item_name,
      NULL AS master_category,
      NULL AS master_unit
    FROM stock_reconciliation r
    ORDER BY r.reconciled_at DESC
    LIMIT 50
  ")->fetchAll(PDO::FETCH_ASSOC);
} else {
  $reconLogs = $pdo->query("\n    SELECT
      r.*,
      'Unknown Item' AS inventory_item_name,
      NULL AS master_item_name,
      NULL AS master_category,
      NULL AS master_unit
    FROM stock_reconciliation r
    ORDER BY r.reconciled_at DESC
    LIMIT 50
  ")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include "../layout/header.php"; ?>
<?php include "../layout/sidebar.php"; ?>
<?php include "../layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

    <div class="module-header mb-4">
      <div>
        <h2 class="mb-2">Inventory Management</h2>
        <p class="text-muted mb-0">Track item stocks, receiving, and reconciliation in one area.</p>
      </div>
      <?php if ($activeTab === 'inventory' && $canEdit): ?>
      <button class="btn btn-primary" data-modal-form="addReceivingForm" data-modal-title="Record Receiving">
        <i class="bi bi-plus-lg"></i> New Receiving
      </button>
      <?php endif; ?>
    </div>

    <div class="module-kpi-grid mb-4">
      <div class="module-kpi-card">
        <div class="module-kpi-label">Inventory Items</div>
        <div class="module-kpi-value"><?= $inventoryTotal ?></div>
        <div class="module-kpi-detail">Tracked records</div>
        <div class="module-kpi-icon"><i class="fas fa-box-open"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">Total Stock</div>
        <div class="module-kpi-value"><?= $inventoryStockTotal ?></div>
        <div class="module-kpi-detail">Units on hand</div>
        <div class="module-kpi-icon"><i class="fas fa-cubes"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">Low Stock Items</div>
        <div class="module-kpi-value"><?= $inventoryLowStock ?></div>
        <div class="module-kpi-detail">Below threshold (50)</div>
        <div class="module-kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">Pending Receipts</div>
        <div class="module-kpi-value"><?= $pendingReceipts ?></div>
        <div class="module-kpi-detail">POs awaiting receipt</div>
        <div class="module-kpi-icon"><i class="fas fa-truck-loading"></i></div>
      </div>
    </div>

    <div class="tab-navigation mb-4">
      <a href="?tab=inventory" class="tab-btn <?= $activeTab === 'inventory' ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Inventory & Receiving
      </a>
      <a href="?tab=reconciliation" class="tab-btn <?= $activeTab === 'reconciliation' ? 'active' : '' ?>">
        <i class="bi bi-check2-square"></i> Stock Reconciliation
      </a>
    </div>

    <?php if ($activeTab === 'inventory'): ?>
      <?php if ($canEdit): ?>
      <div id="addReceivingForm" style="display:none;">
        <form method="POST" action="../../controllers/ReceivingController.php" class="row g-3 receiving-modal-form">
          <div class="col-md-4">
            <label class="form-label">Purchase Order Reference</label>
            <select name="po_id" class="form-select receiving-po-select" required>
              <option value="">Select</option>
              <?php foreach($receivablePos as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['po_number']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Quality Check</label>
            <select name="qc_status" class="form-select receiving-qc-status" required>
              <option value="PASS">PASS</option>
              <option value="FAIL">FAIL</option>
            </select>
          </div>

          <div class="col-md-5">
            <label class="form-label">QC Notes (required if FAIL)</label>
            <input name="qc_notes" class="form-control receiving-qc-notes" placeholder="Damaged, wrong item, missing parts, etc.">
          </div>

          <div class="col-12">
            <table class="table table-bordered align-middle receiving-po-items-table">
              <thead class="table-light">
                <tr>
                  <th style="width: 45%;">Item</th>
                  <th style="width: 15%;">PO Qty</th>
                  <th style="width: 15%;">Remaining</th>
                  <th style="width: 25%;">Qty Received</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="4" class="text-muted text-center">Select a PO to load items</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1 receiving-save-btn" name="receive" disabled>
              <i class="bi bi-box-arrow-in-down"></i> Save Receiving
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($canAdd): ?>
      <div id="addInventoryForm" style="display:none;">
        <form method="POST" action="../../controllers/InventoryController.php" class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Item Name</label>
            <input type="text" name="item_name" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Stock</label>
            <input type="number" name="stock" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" required>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" name="add" class="btn btn-primary flex-grow-1"><i class="bi bi-plus-circle"></i> Add Item</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <div class="table-card mb-4">
        <h5 class="mb-3">Inventory List</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Item</th>
                <th>Stock</th>
                <th>Location</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $i): ?>
              <tr>
                <td><?= htmlspecialchars($i['item_name']) ?></td>
                <td><?= (int)$i['stock'] ?></td>
                <td><?= htmlspecialchars($i['location']) ?></td>
                <td class="text-end">
                  <?php if ($canEdit): ?>
                    <button class="btn btn-sm btn-outline-secondary" data-modal-form="editInventoryForm<?= (int)$i['id'] ?>" data-modal-title="Edit Inventory Item"><i class="bi bi-pencil"></i></button>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                    <a href="../../controllers/InventoryController.php?delete=<?= (int)$i['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this item?')"><i class="bi bi-trash"></i></a>
                  <?php endif; ?>
                  <?php if (!$canEdit && !$canDelete): ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Receiving Logs</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>PO</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Quality</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($receivingRows)): ?>
                <tr><td colspan="6" class="text-center text-muted">No receiving records yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($receivingRows as $r): ?>
                <tr>
                  <td class="text-muted small"><?= htmlspecialchars($r['received_at']) ?></td>
                  <td><?= htmlspecialchars($r['po_number'] ?? ('PO #' . ($r['po_id'] ?? '-'))) ?></td>
                  <td><?= htmlspecialchars($r['item_name']) ?></td>
                  <td><?= (int)$r['quantity_received'] ?></td>
                  <td><span class="badge <?= ($r['qc_status'] ?? 'PASS') === 'PASS' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($r['qc_status'] ?? 'PASS') ?></span></td>
                  <td class="text-muted"><?= htmlspecialchars($r['qc_notes'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($canEdit): ?>
        <?php foreach ($items as $i): ?>
        <div id="editInventoryForm<?= (int)$i['id'] ?>" style="display:none;">
          <form method="POST" action="../../controllers/InventoryController.php" class="row g-3 modal-two-col">
            <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
            <div class="col-md-6"><label class="form-label">Item Name</label><input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($i['item_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Stock</label><input type="number" name="stock" class="form-control" value="<?= (int)$i['stock'] ?>" required></div>
            <div class="col-md-6"><label class="form-label">Location</label><input type="text" name="location" class="form-control" value="<?= htmlspecialchars($i['location']) ?>" required></div>
            <div class="col-12 d-flex gap-2">
              <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
              <button type="submit" name="update" class="btn btn-primary flex-grow-1"><i class="bi bi-save"></i> Update Item</button>
            </div>
          </form>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php else: ?>
      <div class="form-card mb-4">
        <h5 class="mb-3">Reconcile Item</h5>
        <?php if ($canReconcile): ?>
        <form method="POST" action="../../controllers/ReconciliationController.php" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Item</label>
            <select name="item_id" class="form-select" required>
              <?php foreach($reconItems as $it): ?>
                <option value="<?= (int)$it['id'] ?>">
                  <?= htmlspecialchars($it['item_name']) ?>
                  <?= !empty($it['unit']) ? ' [' . htmlspecialchars($it['unit']) . ']' : '' ?>
                  <?= !empty($it['category']) ? ' - ' . htmlspecialchars($it['category']) : '' ?>
                  (System: <?= (int)$it['stock'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Physical Stock</label>
            <input type="number" name="physical_stock" class="form-control" min="0" required>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100" name="reconcile"><i class="bi bi-check2-square"></i> Reconcile</button>
          </div>
        </form>
        <?php else: ?>
          <div class="text-muted small">Read-only access.</div>
        <?php endif; ?>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Recent Reconciliations</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr><th>Date</th><th>Item</th><th>Unit</th><th>System</th><th>Physical</th><th>Variance</th></tr>
            </thead>
            <tbody>
              <?php foreach($reconLogs as $l): ?>
              <?php
                $displayItem = trim((string)($l['master_item_name'] ?? ''));
                if ($displayItem === '') {
                  $displayItem = (string)($l['inventory_item_name'] ?? $l['item_name'] ?? 'Unknown Item');
                }
                $displayUnit = (string)($l['master_unit'] ?? '-');
              ?>
              <tr>
                <td class="text-muted small"><?= htmlspecialchars((string)($l['reconciled_at'] ?? '')) ?></td>
                <td>
                  <?= htmlspecialchars($displayItem) ?>
                  <?php if (!empty($l['master_category'])): ?>
                    <div class="text-muted small"><?= htmlspecialchars((string)$l['master_category']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($displayUnit !== '' ? $displayUnit : '-') ?></td>
                <td><?= (int)($l['system_stock'] ?? 0) ?></td>
                <td><?= (int)($l['physical_stock'] ?? 0) ?></td>
                <td class="<?= (int)($l['variance'] ?? 0) !== 0 ? 'fw-bold text-danger' : 'text-muted' ?>">
                  <?= (int)($l['variance'] ?? 0) ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($reconLogs)): ?>
                <tr><td colspan="6" class="text-center text-muted">No reconciliation logs yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php include "../layout/footer.php"; ?>
