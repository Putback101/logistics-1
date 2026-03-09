<?php
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/Fleet.php";
require_once __DIR__ . "/../../config/permissions.php";

requireLogin();
requireRole(['admin','manager','project_staff','asset']);

$userRole = $_SESSION['user']['role'] ?? '';
if (!hasPermission($userRole, 'projects', 'edit')) {
  http_response_code(403);
  die('Forbidden');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header('Location: fleet.php?tab=vehicles');
  exit;
}

$fleetModel = new Fleet($pdo);
$vehicle = $fleetModel->find($id);
if (!$vehicle) {
  header('Location: fleet.php?tab=vehicles');
  exit;
}
?>

<?php include "../layout/header.php"; ?>
<?php include "../layout/sidebar.php"; ?>
<?php include "../layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <div class="module-header mb-4">
      <div>
        <h2 class="mb-2">Edit Fleet Vehicle</h2>
        <p class="text-muted mb-0">Update vehicle information and status.</p>
      </div>
      <a href="fleet.php?tab=vehicles" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <div class="table-card">
      <form method="POST" action="../../controllers/FleetController.php" class="row g-3">
        <input type="hidden" name="return" value="../views/project/fleet.php?tab=vehicles">
        <input type="hidden" name="id" value="<?= (int)$vehicle['id'] ?>">

        <div class="col-md-5">
          <label class="form-label">Vehicle Name</label>
          <input type="text" name="vehicle_name" class="form-control" value="<?= htmlspecialchars((string)$vehicle['vehicle_name']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Plate Number</label>
          <input type="text" name="plate_number" class="form-control" value="<?= htmlspecialchars((string)$vehicle['plate_number']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="Available" <?= (($vehicle['status'] ?? '') === 'Available') ? 'selected' : '' ?>>Available</option>
            <option value="In Use" <?= (($vehicle['status'] ?? '') === 'In Use') ? 'selected' : '' ?>>In Use</option>
            <option value="Maintenance" <?= (($vehicle['status'] ?? '') === 'Maintenance') ? 'selected' : '' ?>>Maintenance</option>
          </select>
        </div>

        <div class="col-12 d-flex gap-2">
          <button type="submit" name="update" class="btn btn-primary flex-grow-1"><i class="bi bi-save"></i> Save Changes</button>
          <a href="fleet.php?tab=vehicles" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include "../layout/footer.php"; ?>