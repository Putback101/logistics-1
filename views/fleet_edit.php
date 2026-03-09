<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Fleet.php";

requireLogin();
requireRole(['admin','manager','project']);


if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  header("Location: fleet.php");
  exit;
}

$fleet = new Fleet($pdo);
$vehicle = $fleet->getById($_GET['id']);

if (!$vehicle) {
  header("Location: fleet.php");
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
          <h2 class="mb-1">Edit Vehicle</h2>
          <div class="text-muted">Update vehicle details and status.</div>
        </div>
        <a href="fleet.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Back
        </a>
      </div>

      <div class="form-card">
        <form method="POST" action="../controllers/FleetController.php" class="row g-3">
          <input type="hidden" name="id" value="<?= (int)$vehicle['id'] ?>">

          <div class="col-md-5">
            <label class="form-label">Vehicle Name</label>
            <input type="text" name="vehicle_name" class="form-control"
                   value="<?= htmlspecialchars($vehicle['vehicle_name']) ?>" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Plate Number</label>
            <input type="text" name="plate_number" class="form-control"
                   value="<?= htmlspecialchars($vehicle['plate_number']) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php
                $statuses = ['Available','In Use','Maintenance'];
                foreach ($statuses as $s) {
                  $sel = ($vehicle['status'] === $s) ? 'selected' : '';
                  echo "<option value=\"".htmlspecialchars($s)."\" $sel>".htmlspecialchars($s)."</option>";
                }
              ?>
            </select>
          </div>

          <div class="col-12 d-flex gap-2 justify-content-end">
            <a href="fleet.php" class="btn btn-light border">Cancel</a>
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




