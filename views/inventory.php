<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Inventory.php";
require_once __DIR__ . "/helpers/badges.php";

requireLogin();
requireRole(['admin','manager','warehouse']);

$inventory = new Inventory($pdo);
$items = $inventory->getAll();
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'inventory';
?>

<?php include "layout/header.php"; ?>

<?php include "layout/sidebar.php"; ?>
<?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <h2 class="mb-2">Inventory Management</h2>
    <p class="text-muted mb-4">Track item stocks and storage locations.</p>

    <!-- TAB NAVIGATION -->
    <div class="tab-navigation mb-4">
      <a href="?tab=inventory" class="tab-btn <?= $activeTab === 'inventory' ? 'active' : '' ?>">
        <i class="bi bi-boxes"></i> Inventory
      </a>
      <a href="?tab=stock-levels" class="tab-btn <?= $activeTab === 'stock-levels' ? 'active' : '' ?>">
        <i class="bi bi-bar-chart"></i> Stock Levels
      </a>
      <a href="?tab=reconciliation" class="tab-btn <?= $activeTab === 'reconciliation' ? 'active' : '' ?>">
        <i class="bi bi-arrow-left-right"></i> Reconciliation
      </a>
      <a href="?tab=reports" class="tab-btn <?= $activeTab === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Reports
      </a>
    </div>

    <!-- INVENTORY TAB -->
    <?php if ($activeTab === 'inventory'): ?>
      <div class="form-card mb-4">
        <h5 class="mb-3">Add Inventory Item</h5>

        <form method="POST" action="../controllers/InventoryController.php" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Item Name</label>
            <input type="text" name="item_name" class="form-control" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Stock</label>
            <input type="number" name="stock" class="form-control" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" required>
          </div>

          <div class="col-md-2 d-grid">
            <label class="form-label invisible">Action</label>
            <button type="submit" name="add" class="btn btn-primary">
              <i class="bi bi-plus-circle"></i> Add
            </button>
          </div>
        </form>
      </div>

      <!-- TABLE -->
      <div class="table-card">
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
                <td><?= $i['stock'] ?></td>
                <td><?= htmlspecialchars($i['location']) ?></td>
                <td class="text-end">
                  <a href="inventory_edit.php?id=<?= $i['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="../controllers/InventoryController.php?delete=<?= $i['id'] ?>"
                     class="btn btn-sm btn-outline-danger"
                     onclick="return confirm('Delete this item?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- STOCK LEVELS TAB -->
    <?php if ($activeTab === 'stock-levels'): ?>
      <div class="table-card">
        <h5 class="mb-3">Stock Levels</h5>
        <p class="text-muted">Current inventory levels by location</p>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Item</th><th>Location</th><th>Current Stock</th><th>Min Level</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $i): ?>
              <tr>
                <td><?= htmlspecialchars($i['item_name']) ?></td>
                <td><?= htmlspecialchars($i['location']) ?></td>
                <td><?= $i['stock'] ?></td>
                <td>—</td>
                <td><span class="badge bg-success">In Stock</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- RECONCILIATION TAB -->
    <?php if ($activeTab === 'reconciliation'): ?>
      <div class="table-card">
        <h5 class="mb-3">Stock Reconciliation</h5>
        <p class="text-muted">Verify and adjust inventory discrepancies</p>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Item</th><th>Expected</th><th>Actual</th><th>Variance</th><th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="5" class="text-muted text-center py-4">No reconciliation records</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- REPORTS TAB -->
    <?php if ($activeTab === 'reports'): ?>
      <div class="table-card">
        <h5 class="mb-3">Inventory Reports</h5>
        <p class="text-muted">Inventory analytics and reporting coming soon</p>
      </div>
    <?php endif; ?>

  </div>
</main>


