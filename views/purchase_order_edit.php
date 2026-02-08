<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/policy.php";
require_once __DIR__ . "/../config/flash.php";

require_once __DIR__ . "/../models/PurchaseOrder.php";
require_once __DIR__ . "/../models/Approval.php";
require_once __DIR__ . "/../models/Supplier.php";

requireLogin();
requireRole(['admin','manager','procurement']);

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) { header("Location: purchase_orders.php"); exit; }

$po = new PurchaseOrder($pdo);
$approval = new Approval($pdo);
$supplierModel = new Supplier($pdo);

$data = $po->getById((int)$_GET['id']);
if (!$data) { header("Location: purchase_orders.php"); exit; }

$items = $po->getItems((int)$data['id']);
$suppliers = $supplierModel->getAll();
$ap = $approval->getLatest('purchase_orders', (int)$data['id']);
$apStatus = $ap['status'] ?? 'Pending';

$return = $_GET['return'] ?? 'purchase_orders.php';
$safeReturn = 'purchase_orders.php';
if (is_string($return)) {
  if (str_starts_with($return, 'procurement.php') || str_starts_with($return, 'purchase_orders.php')) {
    $safeReturn = $return;
  }
}

if (!in_array($data['status'] ?? '', ['Draft','Pending Approval'], true)) {
  set_flash('error', 'PO can only be edited while Draft or Pending Approval.');
  header("Location: $safeReturn"); exit;
}
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>
<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-1">Edit PO: <?= htmlspecialchars($data['po_number']) ?></h2>
        <div class="text-muted small">
          Supplier: <?= htmlspecialchars($data['supplier_name'] ?? $data['supplier'] ?? '') ?>
          • Status: <?= htmlspecialchars($data['status']) ?>
        </div>
      </div>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($safeReturn) ?>"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <div class="form-card mb-3">
      <h5 class="mb-3">PO Details</h5>
      <form method="POST" action="../controllers/PurchaseOrderController.php" class="row g-3">
        <input type="hidden" name="update_po_full" value="1">
        <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars($safeReturn) ?>">

        <div class="col-md-6">
          <label class="form-label">Supplier</label>
          <select name="supplier_id" class="form-control" required>
            <option value="">-- Select Supplier --</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= ((int)($data['supplier_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <?php foreach (['Draft','Pending Approval'] as $st): ?>
              <option value="<?= $st ?>" <?= (($data['status'] ?? '') === $st) ? 'selected' : '' ?>>
                <?= $st ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <div class="table-card">
            <h5 class="mb-3">Items</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Item</th>
                    <th style="width:120px;">Qty</th>
                    <th style="width:160px;">Unit Cost</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it): ?>
                    <tr>
                      <td>
                        <input name="items[<?= (int)$it['id'] ?>][item_name]" class="form-control"
                               value="<?= htmlspecialchars($it['item_name']) ?>" required>
                      </td>
                      <td>
                        <input type="number" name="items[<?= (int)$it['id'] ?>][quantity]" class="form-control"
                               min="1" value="<?= (int)$it['quantity'] ?>" required>
                      </td>
                      <td>
                        <input type="number" step="0.01" name="items[<?= (int)$it['id'] ?>][unit_cost]" class="form-control"
                               min="0" value="<?= htmlspecialchars((string)$it['unit_cost']) ?>" required>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-12 d-flex gap-2 justify-content-end">
          <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($safeReturn) ?>">Cancel</a>
          <button class="btn btn-primary" name="update_po_full">
            <i class="bi bi-save"></i> Save Changes
          </button>
        </div>
      </form>
    </div>

    <div class="form-card mb-3">
      <h5 class="mb-3">Add Item</h5>
      <form method="POST" action="../controllers/PurchaseOrderController.php" class="row g-3">
        <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars($safeReturn) ?>">

        <div class="col-md-6">
          <label class="form-label">Item Name</label>
          <input name="item_name" class="form-control" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Qty</label>
          <input type="number" name="quantity" class="form-control" min="1" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Unit Cost</label>
          <input type="number" step="0.01" name="unit_cost" class="form-control" min="0" required>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-primary" name="add_item">
            <i class="bi bi-plus-circle"></i> Add
          </button>
        </div>
      </form>
    </div>

    <div class="table-card">
      <h5 class="mb-3">Approval</h5>

      <div class="d-flex flex-wrap gap-2 mb-2">
        <?php if (can_po_action('request_approval', $data) && in_array($data['status'], ['Draft','Rejected'], true)): ?>
          <form method="POST" action="../controllers/PurchaseOrderController.php">
            <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">
            <input type="hidden" name="return" value="<?= htmlspecialchars($safeReturn) ?>">
            <button class="btn btn-outline-primary" name="request_approval">
              <i class="bi bi-send"></i> Request Approval
            </button>
          </form>
        <?php endif; ?>

        <?php if (($_SESSION['user']['role'] ?? '') === 'admin' && $data['status'] === 'Pending Approval'): ?>
          <form method="POST" action="../controllers/PurchaseOrderController.php" class="d-flex gap-2">
            <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">
            <input type="hidden" name="return" value="<?= htmlspecialchars($safeReturn) ?>">
            <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
            <button class="btn btn-success" name="approve"><i class="bi bi-check2"></i></button>
            <button class="btn btn-danger" name="reject"><i class="bi bi-x"></i></button>
          </form>
        <?php endif; ?>
      </div>

      <span class="badge <?= $apStatus==='Approved' ? 'bg-success' : ($apStatus==='Rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
        Approval: <?= htmlspecialchars($apStatus) ?>
      </span>
    </div>
  </div>
</main>

<?php require_once __DIR__ . "/layout/footer.php"; ?>
