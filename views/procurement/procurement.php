<?php
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/permissions.php";
require_once __DIR__ . "/../../config/policy.php";
require_once __DIR__ . "/../../models/Procurement.php";
require_once __DIR__ . "/../../models/Budget.php";
require_once __DIR__ . "/../../models/Supplier.php";
require_once __DIR__ . "/../../models/PurchaseOrder.php";
require_once __DIR__ . "/../../models/Approval.php";
require_once __DIR__ . "/../../models/Item.php";
require_once __DIR__ . "/../helpers/badges.php";

requireLogin();
requireRole(['admin','manager','procurement_staff']);

$procurement = new Procurement($pdo);
$budget = new Budget($pdo);
$supplier = new Supplier($pdo);
$po = new PurchaseOrder($pdo);
$approvalModel = new Approval($pdo);
$itemModel = new Item($pdo);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'procurement', 'add');
$canEdit = hasPermission($userRole, 'procurement', 'edit');
$canDelete = hasPermission($userRole, 'procurement', 'delete');
$isAdmin = ($userRole === 'admin');
$canSupplierEdit = hasPermission($userRole, 'procurement', 'edit');
$canSupplierDelete = hasPermission($userRole, 'procurement', 'delete');
$canApproveReq = in_array($userRole, ['admin','manager'], true);

$requests = $procurement->getAll();
$budgets = $budget->getAll();
$requestBudgetSummary = $procurement->getBudgetRequestSummary();
$suppliers = $supplier->getAll();
$itemsMaster = $itemModel->getAll();
$pos = $po->getAll();
$nextPoNumber = $po->nextNumber();

$pendingRequests = 0;
$approvedRequests = 0;
$totalRequestedAmount = 0.0;
foreach ($requests as $r) {
  $status = $r['status'] ?? 'Pending';
  if ($status === 'Approved') {
    $approvedRequests++;
  } else {
    $pendingRequests++;
  }
  $totalRequestedAmount += (float)($r['estimated_amount'] ?? 0);
}

$openPurchaseOrders = 0;
foreach ($pos as $p) {
  if (($p['status'] ?? 'Draft') !== 'Received') {
    $openPurchaseOrders++;
  }
}

$supplierCount = count($suppliers);

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'purchase-orders';
if (!in_array($activeTab, ['purchase-orders','suppliers','budget','reports','approvals'], true)) {
  $activeTab = 'purchase-orders';
}
$returnTo = 'procurement.php?tab=' . urlencode($activeTab);

$poId = 0;
if (isset($_GET['po_id']) && ctype_digit((string)$_GET['po_id'])) {
  $poId = (int)$_GET['po_id'];
} elseif (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
  $poId = (int)$_GET['id'];
}
$poMode = $_GET['mode'] ?? 'view';
if (!in_array($poMode, ['view','edit'], true)) {
  $poMode = 'view';
}

$editRequestId = (isset($_GET['edit_request_id']) && ctype_digit((string)$_GET['edit_request_id'])) ? (int)$_GET['edit_request_id'] : 0;
$editingRequest = $editRequestId > 0 ? $procurement->getById($editRequestId) : null;
if ($editingRequest && ($editingRequest['status'] ?? '') !== 'Pending') {
  $editingRequest = null;
}

$selectedPo = null;
$selectedPoItems = [];
$selectedPoApproval = 'Pending';
$poCanEdit = false;

if ($poId > 0) {
  $selectedPo = $po->getById($poId);
  if ($selectedPo) {
    $selectedPoItems = $po->getItems((int)$selectedPo['id']);
    $latestApproval = $approvalModel->getLatest('purchase_orders', (int)$selectedPo['id']);
    $selectedPoApproval = $latestApproval['status'] ?? 'Pending';
    $poCanEdit = hasPermission($userRole, 'procurement', 'edit') && can_edit_po($selectedPo);
    if ($poMode === 'edit' && !$poCanEdit) {
      $poMode = 'view';
    }
  }
}
?>
<?php include "../layout/header.php"; ?>
<?php include "../layout/sidebar.php"; ?>
<?php include "../layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area procurement-skin">
    <div class="module-header mb-4">
      <div>
        <h2 class="mb-2">Procurement Management</h2>
        <p class="text-muted mb-0">Manage purchase orders, suppliers, and budget tracking</p>
      </div>
      <?php if ($activeTab === 'suppliers' && $canSupplierEdit): ?>
        <button class="btn btn-primary" data-modal-form="addSupplierForm" data-modal-title="Add Supplier">
          <i class="bi bi-plus-lg"></i> New Supplier
        </button>
      <?php elseif ($activeTab === 'items' && $canEdit): ?>
        <button class="btn btn-primary" data-modal-form="addItemForm" data-modal-title="Add Item">
          <i class="bi bi-plus-lg"></i> New Item
        </button>
      <?php elseif ($activeTab !== 'suppliers' && $activeTab !== 'items' && $canAdd): ?>
        <button class="btn btn-primary" data-modal-form="addProcurementRequestForm" data-modal-title="New Request">
          <i class="bi bi-plus-lg"></i> New Request
        </button>
      <?php endif; ?>
    </div>

    <div class="module-kpi-grid mb-4">
      <div class="module-kpi-card">
        <div class="module-kpi-label">Pending Requests</div>
        <div class="module-kpi-value"><?= $pendingRequests ?></div>
        <div class="module-kpi-detail">Awaiting review/approval</div>
        <div class="module-kpi-icon"><i class="fas fa-hourglass-half"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">Open Purchase Orders</div>
        <div class="module-kpi-value"><?= $openPurchaseOrders ?></div>
        <div class="module-kpi-detail">Not yet marked received</div>
        <div class="module-kpi-icon"><i class="fas fa-shopping-bag"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">Active Suppliers</div>
        <div class="module-kpi-value"><?= $supplierCount ?></div>
        <div class="module-kpi-detail">Available vendor records</div>
        <div class="module-kpi-icon"><i class="fas fa-truck-loading"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">Requested Budget</div>
        <div class="module-kpi-value">â‚±<?= number_format($totalRequestedAmount, 2) ?></div>
        <div class="module-kpi-detail"><?= $approvedRequests ?> approved requests</div>
        <div class="module-kpi-icon"><i class="fas fa-coins"></i></div>
      </div>
    </div>

    <!-- TAB NAVIGATION -->
    <div class="tab-navigation mb-4">
      <a href="?tab=purchase-orders" class="tab-btn <?= $activeTab === 'purchase-orders' ? 'active' : '' ?>">
        <i class="bi bi-bag-check"></i> Purchase Orders
      </a>
      <a href="?tab=suppliers" class="tab-btn <?= $activeTab === 'suppliers' ? 'active' : '' ?>">
        <i class="bi bi-person-vcard"></i> Suppliers
      </a>
      <a href="?tab=items" class="tab-btn <?= $activeTab === 'items' ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Items
      </a>
      <a href="?tab=budget" class="tab-btn <?= $activeTab === 'budget' ? 'active' : '' ?>">
        <i class="bi bi-clock"></i> Budget
      </a>
      <a href="?tab=approvals" class="tab-btn <?= $activeTab === 'approvals' ? 'active' : '' ?>">
        <i class="bi bi-check2-square"></i> Approvals
      </a>
      <a href="?tab=reports" class="tab-btn <?= $activeTab === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Reports
      </a>
    </div>

    <!-- PURCHASE ORDERS TAB -->
    <?php if ($activeTab === 'purchase-orders'): ?>
      <?php if ($canAdd): ?>
      <div id="addProcurementRequestForm" style="display:none;">
        <form method="POST" action="../../controllers/ProcurementController.php" class="row g-3">
<div class="col-md-4">
  <label class="form-label">Budget Year</label>
  <select name="budget_year" class="form-select" required>
    <option value="">-- Select Year --</option>
    <?php foreach($budgets as $b): ?>
      <option value="<?= (int)$b['year'] ?>"><?= (int)$b['year'] ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-12">
  <label class="form-label">Request Lines</label>
  <div class="procurement-lines-body d-grid gap-3">
    <div class="procurement-line-card" data-line-index="0">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0 text-light">Line 1</h6>
        <button type="button" class="btn btn-sm btn-outline-danger request-line-remove" disabled><i class="bi bi-trash"></i></button>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Item</label>
          <select name="items[0][item_id]" class="form-select item-master-select">
            <option value="">-- Select Item --</option>
            <?php foreach($itemsMaster as $im): ?>
              <option value="<?= (int)$im['id'] ?>" data-name="<?= htmlspecialchars($im['item_name'], ENT_QUOTES) ?>">
                <?= htmlspecialchars($im['item_name']) ?><?= !empty($im['unit']) ? ' (' . htmlspecialchars($im['unit']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="items[0][item_name]" class="form-control mt-2 item-master-name" placeholder="or type item name" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Supplier</label>
          <select name="items[0][supplier]" class="form-select" required>
            <option value="">-- Select Supplier --</option>
            <?php foreach($suppliers as $s): ?>
              <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Quantity</label>
          <input type="number" min="1" name="items[0][quantity]" class="form-control" placeholder="Number of units" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Est. Amount</label>
          <input type="number" step="0.01" min="0" name="items[0][estimated_amount]" class="form-control" value="0">
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3">
    <button type="button" class="btn btn-sm btn-outline-secondary request-line-add">
      <i class="bi bi-plus-circle"></i> Add Line
    </button>
  </div>
</div>
<div class="col-12 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" name="add">
              <i class="bi bi-plus-circle"></i> Request
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($editingRequest && $canEdit): ?>
      <div class="form-card mb-4">
        <h5 class="mb-3">Edit Procurement Request</h5>
        <form method="POST" action="../../controllers/ProcurementController.php" class="row g-3">
          <input type="hidden" name="id" value="<?= (int)$editingRequest['id'] ?>">
          <input type="hidden" name="item_id" value="<?= isset($editingRequest['item_id']) ? (int)$editingRequest['item_id'] : 0 ?>">
          <div class="col-md-4"><label class="form-label">Item Name</label><input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($editingRequest['item_name']) ?>" required></div>
          <div class="col-md-2"><label class="form-label">Quantity</label><input type="number" name="quantity" class="form-control" min="1" value="<?= (int)$editingRequest['quantity'] ?>" required></div>
          <div class="col-md-3"><label class="form-label">Supplier</label><input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($editingRequest['supplier']) ?>" required></div>
          <div class="col-md-3"><label class="form-label">Budget Year</label><input type="number" min="2000" name="budget_year" class="form-control" value="<?= htmlspecialchars((string)($editingRequest['budget_year'] ?? date('Y'))) ?>" required></div>
          <div class="col-md-3"><label class="form-label">Estimated Amount</label><input type="number" step="0.01" min="0" name="estimated_amount" class="form-control" value="<?= htmlspecialchars((string)($editingRequest['estimated_amount'] ?? '0')) ?>"></div>
          <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Pending" selected>Pending</option><option value="Approved">Approved</option></select></div>
          <div class="col-12 d-flex gap-2 justify-content-end"><a href="procurement.php?tab=purchase-orders" class="btn btn-outline-secondary">Cancel</a><button type="submit" name="update" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button></div>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($selectedPo): ?>
      <div class="table-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1"><?= $poMode === 'edit' ? 'Edit Purchase Order' : 'Purchase Order Details' ?></h5>
            <div class="text-muted small">
              PO: <?= htmlspecialchars($selectedPo['po_number']) ?>
              | Supplier: <?= htmlspecialchars($selectedPo['supplier_name'] ?? $selectedPo['supplier'] ?? '') ?>
              | Total: &#8369;<?= number_format((float)$selectedPo['total_amount'], 2) ?>
              | Status: <?= htmlspecialchars($selectedPo['status'] ?? 'Draft') ?>
            </div>
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="procurement.php?tab=purchase-orders"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <?php if ($poMode === 'edit' && $poCanEdit): ?>
          <div class="form-card mb-3">
            <h6 class="mb-3">PO Details</h6>
            <form method="POST" action="../../controllers/PurchaseOrderController.php" class="row g-3">
              <input type="hidden" name="po_id" value="<?= (int)$selectedPo['id'] ?>">
              <input type="hidden" name="return" value="procurement.php?tab=purchase-orders&po_id=<?= (int)$selectedPo['id'] ?>&mode=edit">

              <div class="col-md-6">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-select" required>
                  <option value="">-- Select Supplier --</option>
                  <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= ((int)($selectedPo['supplier_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($s['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                  <?php foreach (['Draft','Approved'] as $status): ?>
                    <option value="<?= $status ?>" <?= (($selectedPo['status'] ?? 'Draft') === $status) ? 'selected' : '' ?>>
                      <?= $status ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12">
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
                      <?php foreach ($selectedPoItems as $it): ?>
                        <tr>
                          <td><input name="items[<?= (int)$it['id'] ?>][item_name]" class="form-control" value="<?= htmlspecialchars($it['item_name']) ?>" required></td>
                          <td><input type="number" name="items[<?= (int)$it['id'] ?>][quantity]" class="form-control" min="1" value="<?= (int)$it['quantity'] ?>" required></td>
                          <td><input type="number" step="0.01" name="items[<?= (int)$it['id'] ?>][unit_cost]" class="form-control" min="0" value="<?= htmlspecialchars((string)$it['unit_cost']) ?>" required></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="col-12 d-flex justify-content-end gap-2">
                <a href="procurement.php?tab=purchase-orders" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" name="update_po_full"><i class="bi bi-save"></i> Save Changes</button>
              </div>
            </form>
          </div>

          <div class="form-card mb-3">
            <h6 class="mb-3">Add Item</h6>
            <form method="POST" action="../../controllers/PurchaseOrderController.php" class="row g-3">
              <input type="hidden" name="po_id" value="<?= (int)$selectedPo['id'] ?>">
              <input type="hidden" name="return" value="procurement.php?tab=purchase-orders&po_id=<?= (int)$selectedPo['id'] ?>&mode=edit">
              <div class="col-md-6"><label class="form-label">Item Name</label><input name="item_name" class="form-control" required></div>
              <div class="col-md-2"><label class="form-label">Qty</label><input type="number" name="quantity" class="form-control" min="1" required></div>
              <div class="col-md-4"><label class="form-label">Unit Cost</label><input type="number" step="0.01" name="unit_cost" class="form-control" min="0" required></div>
              <div class="col-12 d-grid"><button class="btn btn-primary" name="add_item"><i class="bi bi-plus-circle"></i> Add Item</button></div>
            </form>
          </div>
        <?php endif; ?>

        <div class="table-card mb-3">
          <h6 class="mb-3">Items</h6>
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
              <thead class="table-light"><tr><th>Item</th><th>Qty</th><th>Unit Cost</th><th>Subtotal</th></tr></thead>
              <tbody>
                <?php foreach ($selectedPoItems as $it): ?>
                  <tr>
                    <td><?= htmlspecialchars($it['item_name']) ?></td>
                    <td><?= (int)$it['quantity'] ?></td>
                    <td>&#8369;<?= number_format((float)$it['unit_cost'], 2) ?></td>
                    <td>&#8369;<?= number_format(((int)$it['quantity']) * ((float)$it['unit_cost']), 2) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($selectedPoItems)): ?>
                  <tr><td colspan="4" class="text-center text-muted">No items yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="table-card">
          <h6 class="mb-3">Actions</h6>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <?php if (can_po_action('request_approval', $selectedPo) && in_array(($selectedPo['status'] ?? ''), ['Draft','Rejected'], true)): ?>
              <form method="POST" action="../../controllers/PurchaseOrderController.php">
                <input type="hidden" name="po_id" value="<?= (int)$selectedPo['id'] ?>">
                <input type="hidden" name="return" value="procurement.php?tab=purchase-orders&po_id=<?= (int)$selectedPo['id'] ?>&mode=view">
                <button class="btn btn-outline-primary" name="request_approval"><i class="bi bi-send"></i> Request Approval</button>
              </form>
            <?php endif; ?>

            <?php if (can_po_action('send_to_supplier', $selectedPo) && in_array(($selectedPo['status'] ?? ''), ['Approved','Returned'], true)): ?>
              <form method="POST" action="../../controllers/PurchaseOrderController.php">
                <input type="hidden" name="po_id" value="<?= (int)$selectedPo['id'] ?>">
                <input type="hidden" name="return" value="procurement.php?tab=purchase-orders&po_id=<?= (int)$selectedPo['id'] ?>&mode=view">
                <button class="btn btn-primary" name="send_to_supplier"><i class="bi bi-truck"></i> Mark as Sent</button>
              </form>
            <?php endif; ?>

            <?php if (can_po_action('approve', $selectedPo) && ($selectedPo['status'] ?? '') === 'Pending Approval'): ?>
              <form method="POST" action="../../controllers/PurchaseOrderController.php" class="d-flex gap-2">
                <input type="hidden" name="po_id" value="<?= (int)$selectedPo['id'] ?>">
                <input type="hidden" name="return" value="procurement.php?tab=purchase-orders&po_id=<?= (int)$selectedPo['id'] ?>&mode=view">
                <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
                <button class="btn btn-success" name="approve"><i class="bi bi-check2"></i></button>
                <button class="btn btn-danger" name="reject"><i class="bi bi-x"></i></button>
              </form>
            <?php endif; ?>
          </div>
          <span class="badge <?= $selectedPoApproval === 'Approved' ? 'bg-success' : ($selectedPoApproval === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
            Approval: <?= htmlspecialchars($selectedPoApproval) ?>
          </span>
        </div>
      </div>
      <?php endif; ?>
<!-- PURCHASE ORDERS TABLE -->
      <div class="table-card mb-4 hr-list-card">
        <h5 class="mb-3">Purchase Orders (Official Buying)</h5>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pos as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['po_number'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['supplier_name'] ?? $p['supplier'] ?? '') ?></td>
                <td>â‚½<?= number_format($p['total_amount'] ?? 0, 2) ?></td>
                <td><span class="badge <?= badge_class($p['status'] ?? 'Draft') ?>">
                  <?= htmlspecialchars($p['status'] ?? 'Draft') ?>
                    </span>
                </td>
                <td class="text-end">
                  <a href="procurement.php?tab=purchase-orders&po_id=<?= (int)$p['id'] ?>&mode=view&return=<?= rawurlencode($returnTo) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php if ($canEdit && in_array(($p['status'] ?? ''), ['Draft','Approved'], true)): ?>
                    <a href="procurement.php?tab=purchase-orders&po_id=<?= (int)$p['id'] ?>&mode=edit&return=procurement.php?tab=purchase-orders"
                       class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                    <a href="../../controllers/PurchaseOrderController.php?delete=<?= $p['id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Delete this purchase order?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php if (empty($pos)): ?>
            <p class="text-muted mt-3">No purchase orders found</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- PROCUREMENT REQUESTS TABLE -->
      <div class="table-card mb-4 hr-list-card">
        <h5 class="mb-3">Procurement Requests</h5>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Request Ref</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Supplier</th>
                <th>Budget Year</th>
                <th>Est. Amount</th>
                <th>Status</th>
                <th>Linked PO</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($requests as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['request_ref'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['item_name'] ?? '') ?></td>
                <td><?= (int)($r['quantity'] ?? 0) ?></td>
                <td><?= htmlspecialchars($r['supplier'] ?? '') ?></td>
                <td><?= htmlspecialchars((string)($r['budget_year'] ?? '-')) ?></td>
                <td>â‚±<?= number_format((float)($r['estimated_amount'] ?? 0), 2) ?></td>
                <td>
                  <span class="badge <?= badge_class($r['status'] ?? 'Pending') ?>">
                    <?= htmlspecialchars($r['status'] ?? 'Pending') ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($r['po_number'])): ?>
                    <?= htmlspecialchars($r['po_number']) ?>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if ($canEdit && ($r['status'] ?? '') === 'Pending'): ?>
                    <a href="procurement.php?tab=purchase-orders&edit_request_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php endif; ?>

                  <?php if ($canApproveReq && ($r['status'] ?? '') === 'Pending'): ?>
                    <form method="POST" action="../../controllers/ProcurementController.php" class="d-inline">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-success" name="approve_request" title="Approve request">
                        <i class="bi bi-check2"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                  <?php if ($canAdd && ($r['status'] ?? '') === 'Approved' && empty($r['po_number'])): ?>
                    <form method="POST" action="../../controllers/PurchaseOrderController.php" class="d-inline">
                      <input type="hidden" name="procurement_id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-primary" name="generate_po_from_procurement" title="Generate PO">
                        <i class="bi bi-bag-check"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                  <?php if ($canDelete && empty($r['po_number'])): ?>
                    <a href="../../controllers/ProcurementController.php?delete=<?= (int)$r['id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Delete this procurement request?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php if (empty($requests)): ?>
            <p class="text-muted mt-3">No procurement requests found</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- SUPPLIERS TAB -->
    <?php if ($activeTab === 'suppliers'): ?>
      <!-- ACTION BAR -->
      <div class="mb-4"></div>

      <div class="table-card">
        <h5 class="mb-3">Supplier List</h5>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Supplier Name</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Phone</th>
                <?php if ($canSupplierEdit || $canSupplierDelete): ?>
                  <th class="text-end">Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($suppliers as $s): ?>
              <tr>
                <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['contact_person'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                <?php if ($canSupplierEdit || $canSupplierDelete): ?>
                  <td class="text-end">
                    <?php if (($activeTab === 'suppliers' && $canSupplierEdit) || ($activeTab !== 'suppliers' && $canAdd)): ?>
                    <button class="btn btn-sm btn-outline-secondary edit-supplier-trigger" data-modal-form="editSupplierForm" data-modal-title="Edit Supplier" data-id="<?= (int)$s['id'] ?>" data-name="<?= htmlspecialchars($s['name'] ?? '', ENT_QUOTES) ?>" data-contact="<?= htmlspecialchars($s['contact_person'] ?? '', ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($s['email'] ?? '', ENT_QUOTES) ?>" data-phone="<?= htmlspecialchars($s['phone'] ?? '', ENT_QUOTES) ?>"><i class="bi bi-pencil"></i></button>
                    <?php endif; ?>
                    <?php if ($canSupplierDelete): ?>
                    <form method="POST" action="../../controllers/SupplierController.php"
                          class="d-inline"
                          onsubmit="return confirm('Delete this supplier?');">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" name="delete_supplier">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if (($activeTab === 'suppliers' && $canSupplierEdit) || ($activeTab !== 'suppliers' && $canAdd)): ?>
      <!-- ADD SUPPLIER FORM (Hidden by default, shown in modal) -->
      <div id="addSupplierForm" style="display: none;">
        <form method="POST" action="../../controllers/SupplierController.php" class="row g-3 modal-two-col">
          <div class="col-md-6"><label class="form-label">Supplier Name</label><input type="text" name="name" class="form-control" required></div>

          <div class="col-md-6">
            <label class="form-label">Contact Person</label>
            <input type="text" name="contact_person" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>

          <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" required></div>

          <div class="col-12 d-flex gap-2">
            <button type="submit" name="add" class="btn btn-primary flex-grow-1">
              <i class="bi bi-plus-circle"></i> Add Supplier
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    <?php endif; ?>


    <!-- EDIT SUPPLIER FORM TEMPLATE -->
    <?php if (($activeTab === 'suppliers' && $canSupplierEdit) || ($activeTab !== 'suppliers' && $canAdd)): ?>
    <div id="editSupplierForm" style="display:none;">
      <form method="POST" action="../../controllers/SupplierController.php" class="row g-3 modal-two-col">
        <input type="hidden" name="id" id="edit_id">
        <div class="col-md-6"><label class="form-label">Supplier Name</label><input name="name" id="edit_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Contact Person</label><input name="contact_person" id="edit_contact_person" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" id="edit_phone" class="form-control"></div>
        <div class="col-12 d-flex gap-2">
          <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
          <button class="btn btn-primary flex-grow-1" name="update_supplier"><i class="bi bi-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
    <?php endif; ?>


    <!-- ITEMS TAB -->
    <?php if ($activeTab === 'items'): ?>
      <div class="table-card">
        <h5 class="mb-3">Item Master</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Item Name</th>
                <th>Category</th>
                <th>Unit</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($itemsMaster)): ?>
                <tr><td colspan="4" class="text-center text-muted">No items found.</td></tr>
              <?php endif; ?>
              <?php foreach ($itemsMaster as $im): ?>
                <tr>
                  <td><?= htmlspecialchars($im['item_name'] ?? '') ?></td>
                  <td><?= htmlspecialchars($im['category'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($im['unit'] ?? '-') ?></td>
                  <td class="text-end">
                    <?php if ($canEdit): ?>
                      <button class="btn btn-sm btn-outline-secondary edit-item-trigger"
                              data-modal-form="editItemForm"
                              data-modal-title="Edit Item"
                              data-id="<?= (int)$im['id'] ?>"
                              data-item_name="<?= htmlspecialchars($im['item_name'] ?? '', ENT_QUOTES) ?>"
                              data-category="<?= htmlspecialchars($im['category'] ?? '', ENT_QUOTES) ?>"
                              data-unit="<?= htmlspecialchars($im['unit'] ?? '', ENT_QUOTES) ?>">
                        <i class="bi bi-pencil"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                      <form method="POST" action="../../controllers/ItemController.php" class="d-inline" onsubmit="return confirm('Delete this item?');">
                        <input type="hidden" name="id" value="<?= (int)$im['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" name="delete_item_master"><i class="bi bi-trash"></i></button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($canEdit): ?>
      <div id="addItemForm" style="display:none;">
        <form method="POST" action="../../controllers/ItemController.php" class="row g-3 modal-two-col">
          <div class="col-md-6"><label class="form-label">Item Name</label><input type="text" name="item_name" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Tires"></div>
          <div class="col-md-6"><label class="form-label">Unit</label><input type="text" name="unit" class="form-control" placeholder="e.g. pcs"></div>
          <div class="col-12 d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary flex-grow-1" name="add_item_master"><i class="bi bi-plus-circle"></i> Add Item</button>
          </div>
        </form>
      </div>

      <div id="editItemForm" style="display:none;">
        <form method="POST" action="../../controllers/ItemController.php" class="row g-3 modal-two-col">
          <input type="hidden" name="id" id="edit_item_id">
          <div class="col-md-6"><label class="form-label">Item Name</label><input type="text" name="item_name" id="edit_item_name" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Category</label><input type="text" name="category" id="edit_item_category" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Unit</label><input type="text" name="unit" id="edit_item_unit" class="form-control"></div>
          <div class="col-12 d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary flex-grow-1" name="update_item_master"><i class="bi bi-save"></i> Save Changes</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    <?php endif; ?>
    <!-- BUDGET TAB -->
    <?php if ($activeTab === 'budget'): ?>
      <?php
        $currentYear = date('Y');
        $yearBudget = null;
        foreach ($budgets as $b) {
          if ($b['year'] == $currentYear) {
            $yearBudget = $b;
            break;
          }
        }
      ?>

<?php if ($canEdit): ?>
      <div class="table-card mb-4">
        <h5 class="mb-3"><i class="bi bi-sliders"></i> Budget Configuration</h5>
        <form method="POST" action="../../controllers/BudgetController.php" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Year</label>
            <input type="number" name="year" class="form-control" min="2000" value="<?= date('Y') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Allocated Budget</label>
            <input type="number" step="0.01" min="0" name="allocated" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Spent (Actual)</label>
            <input type="number" step="0.01" min="0" name="spent" class="form-control" placeholder="Optional">
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary" name="save"><i class="bi bi-save"></i> Save Budget</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- BUDGET STATS -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd;">
              <i class="bi bi-file-earmark-text" style="color: #1976d2;"></i>
            </div>
            <div class="stat-content">
              <div class="stat-label">Total Budget</div>
              <div class="stat-value">â‚½<?= number_format($yearBudget ? ($yearBudget['allocated'] ?? 0) : 0, 2) ?></div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="stat-card">
            <div class="stat-icon" style="background: #ffebee;">
              <i class="bi bi-graph-down" style="color: #d32f2f;"></i>
            </div>
            <div class="stat-content">
              <div class="stat-label">Total Spent</div>
              <div class="stat-value">â‚½<?= number_format($yearBudget ? ($yearBudget['spent'] ?? 0) : 0, 2) ?></div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="stat-card">
            <div class="stat-icon" style="background: #f1f8e9;">
              <i class="bi bi-graph-up" style="color: #689f38;"></i>
            </div>
            <div class="stat-content">
              <div class="stat-label">Remaining</div>
              <div class="stat-value">â‚½<?= number_format($yearBudget ? (($yearBudget['allocated'] ?? 0) - ($yearBudget['spent'] ?? 0)) : 0, 2) ?></div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="stat-card">
            <div class="stat-icon" style="background: #fff3e0;">
              <i class="bi bi-hourglass-split" style="color: #f57c00;"></i>
            </div>
            <div class="stat-content">
              <div class="stat-label">Utilization</div>
              <div class="stat-value"><?= $yearBudget && ($yearBudget['allocated'] ?? 0) > 0 ? number_format((($yearBudget['spent'] ?? 0) / $yearBudget['allocated']) * 100, 1) : 0 ?>%</div>
            </div>
          </div>
        </div>
      </div>

      <!-- BUDGET BREAKDOWN -->
      <div class="table-card">
        <h5 class="mb-3"><i class="bi bi-clock"></i> Budget Breakdown by Category</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Year</th>
                <th>Allocated</th>
                <th>Spent</th>
                <th>Requested (Pending+Approved)</th>
                <th>Balance After Requests</th>
              </tr>
            </thead>
            <tbody>
              <?php
                $summaryMap = [];
                foreach ($requestBudgetSummary as $s) {
                  $summaryMap[(int)$s['year']] = $s;
                }
              ?>
              <?php foreach ($budgets as $b): ?>
                <?php
                  $year = (int)$b['year'];
                  $requested = (float)($summaryMap[$year]['total_requested'] ?? 0);
                  $balanceAfter = (float)$b['allocated'] - ((float)$b['spent'] + $requested);
                ?>
                <tr>
                  <td><?= $year ?></td>
                  <td>â‚±<?= number_format((float)$b['allocated'], 2) ?></td>
                  <td>â‚±<?= number_format((float)$b['spent'], 2) ?></td>
                  <td>â‚±<?= number_format($requested, 2) ?></td>
                  <td class="<?= $balanceAfter < 0 ? 'text-danger fw-bold' : '' ?>">â‚±<?= number_format($balanceAfter, 2) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($budgets)): ?>
                <tr><td colspan="5" class="text-center text-muted">No budget records yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- APPROVALS TAB -->
    <?php if ($activeTab === 'approvals'): ?>
      <div class="table-card">
        <h5 class="mb-3">Pending Procurement Requests</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Supplier</th>
                <th>Status</th>
                <th>Linked PO</th>
                <?php if ($canApproveReq): ?><th class="text-end">Action</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php $pendingRows = array_filter($requests, fn($r) => (($r['status'] ?? '') === 'Pending')); ?>
              <?php if (empty($pendingRows)): ?>
                <tr><td colspan="<?= $canApproveReq ? '6' : '5' ?>" class="text-center text-muted">No pending requests.</td></tr>
              <?php endif; ?>
              <?php foreach ($pendingRows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['item_name'] ?? '') ?></td>
                  <td><?= (int)($r['quantity'] ?? 0) ?></td>
                  <td><?= htmlspecialchars($r['supplier'] ?? '') ?></td>
                  <td><span class="badge bg-warning text-dark">Pending</span></td>
                  <td><?php if (!empty($r['po_number'])): ?><?= htmlspecialchars($r['po_number']) ?><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                  <?php if ($canApproveReq): ?>
                    <td class="text-end">
                      <form method="POST" action="../../controllers/ProcurementController.php" class="d-inline">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-success" name="approve_request"><i class="bi bi-check2"></i> Approve</button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- REPORTS TAB -->
    <?php if ($activeTab === 'reports'): ?>
      <div class="table-card">
        <h5 class="mb-3">Procurement Reports</h5>
        <p class="text-muted">Reports and analytics coming soon</p>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php if ($canEdit || $canAdd || $canSupplierEdit): ?>
<script>
  function syncMasterItemName(scope) {
    (scope || document).querySelectorAll('.item-master-select').forEach(function (sel) {
      const hidden = sel.parentElement ? sel.parentElement.querySelector('.item-master-name') : null;
      const opt = sel.options[sel.selectedIndex];
      if (hidden) hidden.value = opt ? (opt.getAttribute('data-name') || '') : '';
    });
  }

  document.addEventListener('change', function (event) {
    const sel = event.target.closest('.item-master-select');
    if (!sel) return;
    const hidden = sel.parentElement ? sel.parentElement.querySelector('.item-master-name') : null;
    const opt = sel.options[sel.selectedIndex];
    if (hidden) hidden.value = opt ? (opt.getAttribute('data-name') || '') : '';
  });

  document.addEventListener('click', function (event) {
    const supplierBtn = event.target.closest('.edit-supplier-trigger');
    if (supplierBtn) {
      window.setTimeout(function () {
        const root = document.getElementById('modalBody');
        if (!root) return;
        const editId = root.querySelector('#edit_id');
        const editName = root.querySelector('#edit_name');
        const editContact = root.querySelector('#edit_contact_person');
        const editEmail = root.querySelector('#edit_email');
        const editPhone = root.querySelector('#edit_phone');
        if (editId) editId.value = supplierBtn.getAttribute('data-id') || '';
        if (editName) editName.value = supplierBtn.getAttribute('data-name') || '';
        if (editContact) editContact.value = supplierBtn.getAttribute('data-contact') || '';
        if (editEmail) editEmail.value = supplierBtn.getAttribute('data-email') || '';
        if (editPhone) editPhone.value = supplierBtn.getAttribute('data-phone') || '';
      }, 0);
      return;
    }

    const itemBtn = event.target.closest('.edit-item-trigger');
    if (itemBtn) {
      window.setTimeout(function () {
        const root = document.getElementById('modalBody');
        if (!root) return;
        const id = root.querySelector('#edit_item_id');
        const name = root.querySelector('#edit_item_name');
        const category = root.querySelector('#edit_item_category');
        const unit = root.querySelector('#edit_item_unit');
        if (id) id.value = itemBtn.getAttribute('data-id') || '';
        if (name) name.value = itemBtn.getAttribute('data-item_name') || '';
        if (category) category.value = itemBtn.getAttribute('data-category') || '';
        if (unit) unit.value = itemBtn.getAttribute('data-unit') || '';
      }, 0);
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    syncMasterItemName(document);
  });
</script>
<?php endif; ?>

<?php include "../layout/footer.php"; ?>












































