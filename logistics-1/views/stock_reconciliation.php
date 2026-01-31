<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";

requireLogin();
requireRole(['admin','manager','warehouse']);


$items = $pdo->query("SELECT id, item_name, stock FROM inventory ORDER BY item_name ASC")->fetchAll();
// Detect if stock_reconciliation has item_id column
$hasItemId = false;
$cols = $pdo->query("SHOW COLUMNS FROM stock_reconciliation")->fetchAll();
foreach ($cols as $c) {
  if ($c['Field'] === 'item_id') { $hasItemId = true; break; }
}

if ($hasItemId) {
  $logs = $pdo->query("
    SELECT r.*, i.item_name
    FROM stock_reconciliation r
    LEFT JOIN inventory i ON i.id = r.item_id
    ORDER BY r.reconciled_at DESC
    LIMIT 50
  ")->fetchAll();
} else {
  // Fallback: table likely stores item_name instead of item_id
  $logs = $pdo->query("
    SELECT r.*,
           COALESCE(r.item_name, 'Unknown Item') AS item_name
    FROM stock_reconciliation r
    ORDER BY r.reconciled_at DESC
    LIMIT 50
  ")->fetchAll();
}
?>
<?php require_once __DIR__ . "/layout/header.php"; ?>


  <?php require_once __DIR__ . "/layout/sidebar.php"; ?>

    <?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
      <h2 class="mb-2">Stock Reconciliation</h2>
      <p class="text-muted mb-4">Compare physical stock vs system stock and correct inventory.</p>

      <div class="form-card mb-4">
        <h5 class="mb-3">Reconcile Item</h5>
        <form method="POST" action="../controllers/ReconciliationController.php" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Item</label>
            <select name="item_id" class="form-select" required>
              <?php foreach($items as $it): ?>
                <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['item_name']) ?> (System: <?= (int)$it['stock'] ?>)</option>
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
      </div>

      <div class="table-card">
        <h5 class="mb-3">Recent Reconciliations</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr><th>Date</th><th>Item</th><th>System</th><th>Physical</th><th>Variance</th></tr>
            </thead>
            <tbody>
              <?php foreach($logs as $l): ?>
              <tr>
                <td class="text-muted small"><?= htmlspecialchars($l['reconciled_at']) ?></td>
                <td><?= htmlspecialchars($l['item_name']) ?></td>
                <td><?= (int)$l['system_stock'] ?></td>
                <td><?= (int)$l['physical_stock'] ?></td>
                <td class="<?= (int)$l['variance'] !== 0 ? 'fw-bold text-danger' : 'text-muted' ?>">
                  <?= (int)$l['variance'] ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<?php require_once __DIR__ . "/layout/footer.php"; ?>




