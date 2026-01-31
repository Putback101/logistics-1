<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/permissions.php";
require_once __DIR__ . "/../models/Procurement.php";
require_once __DIR__ . "/../models/Budget.php";
require_once __DIR__ . "/../models/Supplier.php";
require_once __DIR__ . "/../models/PurchaseOrder.php";
require_once __DIR__ . "/helpers/badges.php";

requireLogin();
requireRole(['admin','manager','procurement','procurement_staff']);

$procurement = new Procurement($pdo);
$budget = new Budget($pdo);
$supplier = new Supplier($pdo);
$po = new PurchaseOrder($pdo);

$userRole = $_SESSION['user']['role'] ?? 'staff';
$canAdd = hasPermission($userRole, 'procurement', 'add');
$canEdit = hasPermission($userRole, 'procurement', 'edit');
$canDelete = hasPermission($userRole, 'procurement', 'delete');

$requests = $procurement->getAll();
$budgets = $budget->getAll();
$suppliers = $supplier->getAll();
$pos = $po->getAll();

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'purchase-orders';
?>

<?php include "layout/header.php"; ?>

<?php include "layout/sidebar.php"; ?>
<?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

    <h2 class="mb-2">Procurement Management</h2>
    <p class="text-muted mb-4">Manage purchase orders, suppliers, and budget tracking</p>

    <!-- TAB NAVIGATION -->
    <div class="tab-navigation mb-4">
      <a href="?tab=purchase-orders" class="tab-btn <?= $activeTab === 'purchase-orders' ? 'active' : '' ?>">
        <i class="bi bi-bag-check"></i> Purchase Orders
      </a>
      <a href="?tab=suppliers" class="tab-btn <?= $activeTab === 'suppliers' ? 'active' : '' ?>">
        <i class="bi bi-person-vcard"></i> Suppliers
      </a>
      <a href="?tab=budget" class="tab-btn <?= $activeTab === 'budget' ? 'active' : '' ?>">
        <i class="bi bi-clock"></i> Budget
      </a>
      <a href="?tab=reports" class="tab-btn <?= $activeTab === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Reports
      </a>
    </div>

    <!-- PURCHASE ORDERS TAB -->
    <?php if ($activeTab === 'purchase-orders'): ?>
      <!-- ACTION BAR -->
      <div class="mb-4">
        <?php if ($canAdd): ?>
        <button class="btn btn-primary" onclick="openAddProcurementModal()">
          <i class="bi bi-plus-circle"></i> Add Procurement Request
        </button>
        <?php endif; ?>
      </div>

      <!-- PURCHASE ORDERS TABLE -->
      <div class="table-card mb-4">
        <h5 class="mb-3">Purchase Orders</h5>

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
                <td>₽<?= number_format($p['total_amount'] ?? 0, 2) ?></td>
                <td><span class="badge <?= badge_class($p['status'] ?? 'Draft') ?>">
                  <?= htmlspecialchars($p['status'] ?? 'Draft') ?>
                    </span>
                </td>
                <td class="text-end">
                  <a href="purchase_order_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php if ($canEdit): ?>
                    <a href="procurement_edit.php?po_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                    <a href="../controllers/ProcurementController.php?delete_po=<?= $p['id'] ?>"
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

      <?php if ($canAdd): ?>
      <!-- ADD PROCUREMENT FORM (Hidden by default, shown in modal) -->
      <div id="addProcurementForm" style="display: none;">
        <form method="POST" action="../controllers/ProcurementController.php" class="row g-3">
          <div class="col-12">
            <label class="form-label">Item Name</label>
            <input type="text" name="item_name" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Supplier</label>
            <input type="text" name="supplier" class="form-control" required>
          </div>

          <div class="col-12">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Delivered">Delivered</option>
            </select>
          </div>

          <div class="col-12 d-flex gap-2">
            <button type="submit" name="add" class="btn btn-primary flex-grow-1">
              <i class="bi bi-plus-circle"></i> Add Request
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- PROCUREMENT REQUESTS TABLE (Legacy - now using PO section above) -->
      <div class="table-card" style="display: none;">
        <h5 class="mb-3">Procurement Requests</h5>
        <p class="text-muted">Please use the Purchase Orders section above</p>
      </div>
    <?php endif; ?>

    <!-- SUPPLIERS TAB -->
    <?php if ($activeTab === 'suppliers'): ?>
      <!-- ACTION BAR -->
      <div class="mb-4">
        <?php if ($canAdd): ?>
        <button class="btn btn-primary" onclick="openAddSupplierModal()">
          <i class="bi bi-plus-circle"></i> Add Supplier
        </button>
        <?php endif; ?>
      </div>

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
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($suppliers as $s): ?>
              <tr>
                <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['contact_person'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                <td class="text-end">
                  <?php if ($canEdit): ?>
                    <a href="procurement_edit.php?supplier_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                    <a href="../controllers/SupplierController.php?delete=<?= $s['id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Delete this supplier?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($canAdd): ?>
      <!-- ADD SUPPLIER FORM (Hidden by default, shown in modal) -->
      <div id="addSupplierForm" style="display: none;">
        <form method="POST" action="../controllers/SupplierController.php" class="row g-3">
          <div class="col-12">
            <label class="form-label">Supplier Name</label>
            <input type="text" name="name" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Contact Person</label>
            <input type="text" name="contact_person" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>

          <div class="col-12">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" required>
          </div>

          <div class="col-12">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2"></textarea>
          </div>

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

      <!-- BUDGET STATS -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd;">
              <i class="bi bi-file-earmark-text" style="color: #1976d2;"></i>
            </div>
            <div class="stat-content">
              <div class="stat-label">Total Budget</div>
              <div class="stat-value">₽<?= number_format($yearBudget ? ($yearBudget['allocated'] ?? 0) : 0, 2) ?></div>
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
              <div class="stat-value">₽<?= number_format($yearBudget ? ($yearBudget['spent'] ?? 0) : 0, 2) ?></div>
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
              <div class="stat-value">₽<?= number_format($yearBudget ? (($yearBudget['allocated'] ?? 0) - ($yearBudget['spent'] ?? 0)) : 0, 2) ?></div>
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
        <p class="text-muted">No budget categories found</p>
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

<?php include "layout/footer.php"; ?>


