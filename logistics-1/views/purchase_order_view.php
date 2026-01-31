<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/policy.php";

require_once __DIR__ . "/../models/PurchaseOrder.php";
require_once __DIR__ . "/../models/Approval.php";

requireLogin();
requireRole(['admin','manager','procurement']);

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) { header("Location: purchase_orders.php"); exit; }

$po = new PurchaseOrder($pdo);
$approval = new Approval($pdo);

$data = $po->getById((int)$_GET['id']);
if (!$data) { header("Location: purchase_orders.php"); exit; }

$items = $po->getItems((int)$data['id']);
$ap = $approval->getLatest('purchase_orders', (int)$data['id']);
$apStatus = $ap['status'] ?? 'Pending';

$editable = can_edit_po($data);
?>
<?php require_once __DIR__ . "/layout/header.php"; ?>


  <?php require_once __DIR__ . "/layout/sidebar.php"; ?>

    <?php require_once __DIR__ . "/layout/topbar.php"; ?>
<main class="main-content">
  <div class="content-area">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="mb-1">PO: <?= htmlspecialchars($data['po_number']) ?></h2>
          <div class="text-muted small">
            Supplier: <?= htmlspecialchars($data['supplier_name'] ?? $data['supplier'] ?? '') ?>
            • Total: ₱<?= number_format($data['total_amount'],2) ?>
            • Status: <?= htmlspecialchars($data['status']) ?>
            <?= !empty($data['is_locked']) ? ' • <span class="badge bg-secondary">Locked</span>' : '' ?>
          </div>
        </div>
        <a class="btn btn-outline-secondary" href="purchase_orders.php"><i class="bi bi-arrow-left"></i> Back</a>
      </div>

      <div class="row g-3">
        <div class="col-lg-7">

          <div class="form-card mb-3">
            <h5 class="mb-3">Add Item</h5>

            <?php if ($editable): ?>
              <form method="POST" action="../controllers/PurchaseOrderController.php" class="row g-3">
                <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">

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
            <?php else: ?>
              <div class="alert alert-warning mb-0">
                This PO is locked or not editable at this stage.
              </div>
            <?php endif; ?>
          </div>

          <div class="table-card">
            <h5 class="mb-3">Items</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                  <tr><th>Item</th><th>Qty</th><th>Unit</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                <?php foreach($items as $it): ?>
                  <tr>
                    <td><?= htmlspecialchars($it['item_name']) ?></td>
                    <td><?= (int)$it['quantity'] ?></td>
                    <td>₱<?= number_format($it['unit_cost'],2) ?></td>
                    <td>₱<?= number_format($it['quantity']*$it['unit_cost'],2) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="mt-3 d-flex flex-wrap gap-2">

              <!-- Request Approval -->
              <?php if (can_po_action('request_approval', $data) && in_array($data['status'], ['Draft','Rejected'], true)): ?>
                <form method="POST" action="../controllers/PurchaseOrderController.php">
                  <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">
                  <button class="btn btn-outline-primary" name="request_approval">
                    <i class="bi bi-send"></i> Request Approval
                  </button>
                </form>
              <?php endif; ?>

              <!-- Send to Supplier -->
              <?php if (can_po_action('send_to_supplier', $data) && $data['status'] === 'Approved'): ?>
                <form method="POST" action="../controllers/PurchaseOrderController.php">
                  <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">
                  <button class="btn btn-primary" name="send_to_supplier">
                    <i class="bi bi-truck"></i> Mark as Sent
                  </button>
                </form>
              <?php endif; ?>
              <?php if (can_po_action('send_to_supplier', $data) && $data['status'] === 'Returned'): ?>
                <form method="POST" action="../controllers/PurchaseOrderController.php">
                  <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">
                  <button class="btn btn-primary" name="send_to_supplier">
                    <i class="bi bi-truck"></i> Re-send to Supplier
                  </button>
                </form>
              <?php endif; ?>


              <!-- Admin approve/reject -->
              <?php if (($_SESSION['user']['role'] ?? '') === 'admin' && $data['status'] === 'Pending Approval'): ?>
                <form method="POST" action="../controllers/PurchaseOrderController.php" class="d-flex gap-2">
                  <input type="hidden" name="po_id" value="<?= (int)$data['id'] ?>">
                  <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
                  <button class="btn btn-success" name="approve"><i class="bi bi-check2"></i></button>
                  <button class="btn btn-danger" name="reject"><i class="bi bi-x"></i></button>
                </form>
              <?php endif; ?>

            </div>

            <div class="mt-2">
              <span class="badge <?= $apStatus==='Approved' ? 'bg-success' : ($apStatus==='Rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                Approval: <?= htmlspecialchars($apStatus) ?>
              </span>
            </div>

          </div>
        </div>

        <div class="col-lg-5">
          <div class="table-card">
            <h5 class="mb-3">Next Step</h5>
            <p class="text-muted mb-2">
              After the PO is marked <b>Sent</b>, go to Receiving and record delivered items. The system will move PO to <b>Received</b>.
            </p>
            <a class="btn btn-primary w-100" href="receiving.php">
              <i class="bi bi-box-arrow-in-down"></i> Go to Receiving
            </a>
          </div>
        </div>

      </div>

    </div>
  </main>
</div>

<?php require_once __DIR__ . "/layout/footer.php"; ?>




