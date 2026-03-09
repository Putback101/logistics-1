<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Budget.php";

requireLogin();
requireRole(['admin','manager','procurement']);

$budget = new Budget($pdo);
$rows = $budget->getAll();
?>
<?php require_once __DIR__ . "/layout/header.php"; ?>

<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>
<main class="main-content">
  <div class="content-area">
    <h2 class="mb-2">Budget Tracking</h2>
      <p class="text-muted mb-4">Track allocated vs spent procurement budget per year.</p>

      <div class="form-card mb-4">
        <h5 class="mb-3">Set / Update Budget</h5>
        <form method="POST" action="../controllers/BudgetController.php" class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Year</label>
            <input type="number" name="year" class="form-control" min="2000" max="2100" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Allocated Amount</label>
            <input type="number" step="0.01" name="allocated" class="form-control" required>
          </div>
          <div class="col-md-5 d-flex align-items-end">
            <button class="btn btn-primary w-100" name="save">
              <i class="bi bi-save"></i> Save Budget
            </button>
          </div>
        </form>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Budget List</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Year</th>
                <th>Allocated</th>
                <th>Spent</th>
                <th>Remaining</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rows as $r): 
                $rem = (float)$r['allocated'] - (float)$r['spent'];
              ?>
              <tr>
                <td><?= (int)$r['year'] ?></td>
                <td>₱<?= number_format($r['allocated'],2) ?></td>
                <td>₱<?= number_format($r['spent'],2) ?></td>
                <td class="<?= $rem < 0 ? 'text-danger fw-bold' : 'fw-semibold' ?>">
                  ₱<?= number_format($rem,2) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</main>

<?php require_once __DIR__ . "/layout/footer.php"; ?>


