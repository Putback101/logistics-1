<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/PurchaseOrder.php";
require_once __DIR__ . "/../models/Approval.php";
require "../models/Supplier.php";
$sup = new Supplier($pdo);
$suppliers = $sup->getAll();

requireLogin();
requireRole(['admin','manager','procurement']);

$po = new PurchaseOrder($pdo);
$approval = new Approval($pdo);
$rows = $po->getAll();
?>
<?php require_once __DIR__ . "/layout/header.php"; ?>

<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <h2 class="mb-2">Purchase Orders</h2>
      <p class="text-muted mb-4">Create and manage purchase orders with approvals.</p>

      <div class="form-card mb-4">
        <h5 class="mb-3">Create PO</h5>
        <form method="POST" action="../controllers/PurchaseOrderController.php" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">PO Number</label>
            <input name="po_number" class="form-control" placeholder="PO-2026-001" required>
          </div>
          <div class="col-md-5">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-control" required>
              <option value="">-- Select Supplier --</option>
                <?php foreach($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100" name="add_po"><i class="bi bi-plus-circle"></i> Create</button>
          </div>
        </form>
      </div>

      <div class="table-card">
        <h5 class="mb-3">PO List</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>PO #</th><th>Supplier</th><th>Total</th><th>Status</th><th>Approval</th><th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $r): 
              $ap = $approval->getLatest('purchase_orders', (int)$r['id']);
            ?>
              <tr>
                <td><?= htmlspecialchars($r['po_number']) ?></td>
                <td><?= htmlspecialchars($r['supplier_name'] ?? $r['supplier'] ?? '') ?></td>
                <td>₱<?= number_format($r['total_amount'],2) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['status']) ?></span></td>
                <td>
                  <span class="badge <?= ($ap['status'] ?? 'Pending')==='Approved' ? 'bg-success' : (($ap['status'] ?? 'Pending')==='Rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                    <?= htmlspecialchars($ap['status'] ?? 'Pending') ?>
                  </span>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="purchase_order_view.php?id=<?= $r['id'] ?>">
                    <i class="bi bi-eye"></i> View
                  </a>
                  <a class="btn btn-sm btn-outline-danger"
                     href="../controllers/PurchaseOrderController.php?delete=<?= $r['id'] ?>"
                     onclick="return confirm('Delete this PO?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
    </div>
  </div>
</main>

<?php require_once __DIR__ . "/layout/footer.php"; ?>


