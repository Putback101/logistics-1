<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Fleet.php";
require_once __DIR__ . "/helpers/badges.php";

/* RBAC */
requireLogin();
requireRole(['admin','manager','project']);

$fleet = new Fleet($pdo);
$vehicles = $fleet->getAll();
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'vehicles';
?>

<?php include "layout/header.php"; ?>

<?php include "layout/sidebar.php"; ?>

<?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <h2 class="mb-2">Fleet Management</h2>
    <p class="text-muted mb-4">Manage company vehicles and their current operational status.</p>

    <!-- TAB NAVIGATION -->
    <div class="tab-navigation mb-4">
      <a href="?tab=vehicles" class="tab-btn <?= $activeTab === 'vehicles' ? 'active' : '' ?>">
        <i class="bi bi-truck"></i> Vehicles
      </a>
      <a href="?tab=maintenance" class="tab-btn <?= $activeTab === 'maintenance' ? 'active' : '' ?>">
        <i class="bi bi-wrench"></i> Maintenance
      </a>
      <a href="?tab=reports" class="tab-btn <?= $activeTab === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Reports
      </a>
    </div>

    <!-- VEHICLES TAB -->
    <?php if ($activeTab === 'vehicles'): ?>
      <!-- ADD VEHICLE FORM -->
      <div class="form-card mb-4">
        <h5 class="mb-3">Add Vehicle</h5>

        <form method="POST"
              action="../controllers/FleetController.php"
              class="row g-3">

          <div class="col-md-4">
            <label class="form-label">Vehicle Name</label>
            <input type="text"
                   name="vehicle_name"
                   class="form-control"
                   placeholder="e.g. Delivery Truck"
                   required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Plate Number</label>
            <input type="text"
                   name="plate_number"
                   class="form-control"
                   placeholder="ABC-1234"
                   required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="Available">Available</option>
              <option value="In Use">In Use</option>
              <option value="Maintenance">Maintenance</option>
            </select>
          </div>

          <div class="col-md-2 d-grid">
            <label class="form-label invisible">Action</label>
            <button type="submit"
                    name="add"
                    class="btn btn-primary">
              <i class="bi bi-plus-circle"></i> Add Vehicle
            </button>
          </div>

        </form>
      </div>

      <!-- VEHICLE TABLE -->
      <div class="table-card">
        <h5 class="mb-3">Vehicle List</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Vehicle</th>
                <th>Plate</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vehicles as $v): ?>
              <tr>
                <td><?= htmlspecialchars($v['vehicle_name']) ?></td>
                <td><?= htmlspecialchars($v['plate_number']) ?></td>
                <td>
                  <span class="badge <?= badge_class($v['status']) ?>">
                    <?= htmlspecialchars($v['status']) ?>
                  </span>
                </td>
                <td class="text-end">
                  <a href="fleet_edit.php?id=<?= $v['id'] ?>"
                     class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil"></i> Edit
                  </a>
                  <a href="../controllers/FleetController.php?delete=<?= $v['id'] ?>"
                     class="btn btn-sm btn-outline-danger"
                     onclick="return confirm('Delete this vehicle?')">
                    <i class="bi bi-trash"></i> Delete
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- MAINTENANCE TAB -->
    <?php if ($activeTab === 'maintenance'): ?>
      <div class="table-card">
        <h5 class="mb-3">Maintenance Schedule</h5>
        <p class="text-muted">View and manage vehicle maintenance schedules</p>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Vehicle</th><th>Type</th><th>Date</th><th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="5" class="text-muted text-center py-4">No maintenance records</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- REPORTS TAB -->
    <?php if ($activeTab === 'reports'): ?>
      <div class="table-card">
        <h5 class="mb-3">Fleet Reports</h5>
        <p class="text-muted">Fleet analytics and reporting coming soon</p>
      </div>
    <?php endif; ?>

<?php include "layout/footer.php"; ?>


