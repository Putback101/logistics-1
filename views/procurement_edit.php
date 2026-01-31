<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Procurement.php";

requireLogin();
requireRole(['admin','manager','procurement']);


if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  header("Location: procurement.php");
  exit;
}

$procurement = new Procurement($pdo);
$request = $procurement->getById($_GET['id']);

if (!$request) {
  header("Location: procurement.php");
  exit;
}
?>

<?php include "layout/header.php"; ?>


  <?php include "layout/sidebar.php"; ?>


    <?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h2 class="mb-1">Edit Procurement</h2>
          <div class="text-muted">Update item, supplier, quantity, and status.</div>
        </div>
        <a href="procurement.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Back
        </a>
      </div>

      <div class="form-card">
        <form method="POST" action="../controllers/ProcurementController.php" class="row g-3">
          <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">

          <div class="col-md-4">
            <label class="form-label">Item Name</label>
            <input type="text" name="item_name" class="form-control"
                   value="<?= htmlspecialchars($request['item_name']) ?>" required>
          </div>

          <div class="col-md-2">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" class="form-control" min="1"
                   value="<?= (int)$request['quantity'] ?>" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Supplier</label>
            <input type="text" name="supplier" class="form-control"
                   value="<?= htmlspecialchars($request['supplier']) ?>" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php
                $statuses = ['Pending','Approved','Delivered'];
                foreach ($statuses as $s) {
                  $sel = ($request['status'] === $s) ? 'selected' : '';
                  echo "<option value=\"".htmlspecialchars($s)."\" $sel>".htmlspecialchars($s)."</option>";
                }
              ?>
            </select>
          </div>

          <div class="col-12 d-flex gap-2 justify-content-end">
            <a href="procurement.php" class="btn btn-light border">Cancel</a>
            <button type="submit" name="update" class="btn btn-primary">
              <i class="bi bi-save"></i> Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<?php include "layout/footer.php"; ?>




