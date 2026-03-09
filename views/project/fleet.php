<?php
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/Fleet.php";
require_once __DIR__ . "/../../config/permissions.php";
require_once __DIR__ . "/../helpers/badges.php";

requireLogin();
requireRole(['admin','manager','project_staff','asset']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'projects', 'add');
$canEdit = hasPermission($userRole, 'projects', 'edit');
$canDelete = hasPermission($userRole, 'projects', 'delete');

$fleetModel = new Fleet($pdo);
$vehicles = $fleetModel->getAll();

$activeTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'vehicles';
if (!in_array($activeTab, ['vehicles','maintenance','reports'], true)) {
  $activeTab = 'vehicles';
}

$fleetTotal = count($vehicles);
$fleetAvailable = 0;
$fleetInUse = 0;
$fleetMaintenance = 0;
foreach ($vehicles as $v) {
  $status = $v['status'] ?? 'Available';
  if ($status === 'Available') $fleetAvailable++;
  if ($status === 'In Use') $fleetInUse++;
  if ($status === 'Maintenance') $fleetMaintenance++;
}

$fleetMaintenanceLogs = $pdo->query("\n  SELECT
    m.*,
    f.vehicle_name,
    f.plate_number,
    u.fullname AS recorded_by_name
  FROM maintenance_logs m
  LEFT JOIN fleet f ON f.id = m.fleet_id
  LEFT JOIN users u ON u.id = m.recorded_by
  WHERE m.fleet_id IS NOT NULL
  ORDER BY COALESCE(m.performed_at, DATE(m.created_at)) DESC, m.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$fleetReportRows = $pdo->query("\n  SELECT
    f.id,
    f.vehicle_name,
    f.plate_number,
    f.status,
    COUNT(m.id) AS total_jobs,
    SUM(CASE WHEN m.type='Maintenance' THEN 1 ELSE 0 END) AS maintenance_jobs,
    SUM(CASE WHEN m.type='Repair' THEN 1 ELSE 0 END) AS repair_jobs,
    COALESCE(SUM(m.cost),0) AS total_cost,
    MAX(m.performed_at) AS last_performed_at
  FROM fleet f
  LEFT JOIN maintenance_logs m ON m.fleet_id = f.id
  GROUP BY f.id
  ORDER BY f.vehicle_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalFleetMaintenanceCost = 0.0;
foreach ($fleetReportRows as $r) {
  $totalFleetMaintenanceCost += (float)($r['total_cost'] ?? 0);
}
?>

<?php include "../layout/header.php"; ?>
<?php include "../layout/sidebar.php"; ?>
<?php include "../layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <div class="module-header mb-4">
      <div>
        <h2 class="mb-2">Fleet Management</h2>
        <p class="text-muted mb-0">Manage company vehicles and monitor maintenance performance.</p>
      </div>
      <?php if ($canAdd && $activeTab === 'vehicles'): ?>
      <button class="btn btn-primary" data-modal-form="addFleetForm" data-modal-title="Add Vehicle">
        <i class="bi bi-plus-lg"></i> New Vehicle
      </button>
      <?php endif; ?>
    </div>

    <div class="module-kpi-grid mb-4">
      <div class="module-kpi-card"><div class="module-kpi-label">Total Vehicles</div><div class="module-kpi-value"><?= $fleetTotal ?></div><div class="module-kpi-detail">Registered fleet units</div><div class="module-kpi-icon"><i class="fas fa-truck"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">Available</div><div class="module-kpi-value"><?= $fleetAvailable ?></div><div class="module-kpi-detail">Ready for assignment</div><div class="module-kpi-icon"><i class="fas fa-check-circle"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">In Use</div><div class="module-kpi-value"><?= $fleetInUse ?></div><div class="module-kpi-detail">Active operations</div><div class="module-kpi-icon"><i class="fas fa-road"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">Maintenance</div><div class="module-kpi-value"><?= $fleetMaintenance ?></div><div class="module-kpi-detail">Service required</div><div class="module-kpi-icon"><i class="fas fa-tools"></i></div></div>
    </div>

    <div class="tab-navigation mb-4">
      <a href="?tab=vehicles" class="tab-btn <?= $activeTab === 'vehicles' ? 'active' : '' ?>"><i class="bi bi-truck"></i> Vehicles</a>
      <a href="?tab=maintenance" class="tab-btn <?= $activeTab === 'maintenance' ? 'active' : '' ?>"><i class="bi bi-wrench"></i> Maintenance</a>
      <a href="?tab=reports" class="tab-btn <?= $activeTab === 'reports' ? 'active' : '' ?>"><i class="bi bi-graph-up"></i> Reports</a>
    </div>

    <?php if ($activeTab === 'vehicles'): ?>
      <?php if ($canAdd): ?>
      <div id="addFleetForm" style="display:none;">
        <form method="POST" action="../../controllers/FleetController.php" class="row g-3">
          <input type="hidden" name="return" value="../views/project/fleet.php?tab=vehicles">
          <div class="col-md-5"><label class="form-label">Vehicle Name</label><input type="text" name="vehicle_name" class="form-control" placeholder="e.g. Delivery Truck" required></div>
          <div class="col-md-4"><label class="form-label">Plate Number</label><input type="text" name="plate_number" class="form-control" placeholder="ABC-1234" required></div>
          <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Available">Available</option><option value="In Use">In Use</option><option value="Maintenance">Maintenance</option></select></div>
          <div class="col-12 d-flex gap-2"><button type="submit" name="add" class="btn btn-primary flex-grow-1"><i class="bi bi-plus-circle"></i> Add Vehicle</button><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button></div>
        </form>
      </div>
      <?php endif; ?>

      <div class="table-card">
        <h5 class="mb-3">Vehicle List</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Vehicle</th><th>Plate</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($vehicles as $v): ?>
              <tr>
                <td><?= htmlspecialchars($v['vehicle_name']) ?></td>
                <td><?= htmlspecialchars($v['plate_number']) ?></td>
                <td><span class="badge <?= badge_class($v['status']) ?>"><?= htmlspecialchars($v['status']) ?></span></td>
                <td class="text-end">
                  <?php if ($canEdit): ?><a href="fleet_edit.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
                  <?php if ($canDelete): ?><a href="../../controllers/FleetController.php?delete=<?= (int)$v['id'] ?>&return=<?= urlencode('../views/project/fleet.php?tab=vehicles') ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this vehicle?')"><i class="bi bi-trash"></i> Delete</a><?php endif; ?>
                  <?php if (!$canEdit && !$canDelete): ?><span class="text-muted">-</span><?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($vehicles)): ?><tr><td colspan="4" class="text-muted text-center py-4">No vehicles found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'maintenance'): ?>
      <div class="table-card">
        <h5 class="mb-3">Fleet Maintenance Logs</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Date</th><th>Vehicle</th><th>Type</th><th>Cost</th><th>Description</th><th>Recorded By</th></tr></thead>
            <tbody>
              <?php if (empty($fleetMaintenanceLogs)): ?><tr><td colspan="6" class="text-muted text-center py-4">No fleet maintenance logs yet.</td></tr><?php endif; ?>
              <?php foreach ($fleetMaintenanceLogs as $l): ?>
                <?php $dateText = !empty($l['performed_at']) ? $l['performed_at'] : ($l['created_at'] ?? '-'); ?>
                <tr>
                  <td class="text-muted small"><?= htmlspecialchars((string)$dateText) ?></td>
                  <td><?= htmlspecialchars((string)(($l['vehicle_name'] ?? '-') . (!empty($l['plate_number']) ? ' (' . $l['plate_number'] . ')' : ''))) ?></td>
                  <td><span class="badge <?= (($l['type'] ?? '') === 'Repair') ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= htmlspecialchars((string)($l['type'] ?? 'Maintenance')) ?></span></td>
                  <td><?= number_format((float)($l['cost'] ?? 0), 2) ?></td>
                  <td class="text-muted"><?= htmlspecialchars((string)($l['description'] ?? '-')) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars((string)($l['recorded_by_name'] ?? '-')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'reports'): ?>
      <div class="module-kpi-grid mb-4">
        <div class="module-kpi-card"><div class="module-kpi-label">Total Maintenance Cost</div><div class="module-kpi-value"><?= number_format($totalFleetMaintenanceCost, 2) ?></div><div class="module-kpi-detail">All logged fleet work</div><div class="module-kpi-icon"><i class="fas fa-coins"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Vehicles with Logs</div><div class="module-kpi-value"><?= count(array_filter($fleetReportRows, fn($r) => (int)$r['total_jobs'] > 0)) ?></div><div class="module-kpi-detail">Tracked maintenance history</div><div class="module-kpi-icon"><i class="fas fa-clipboard-check"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Total Jobs</div><div class="module-kpi-value"><?= array_sum(array_map(fn($r) => (int)$r['total_jobs'], $fleetReportRows)) ?></div><div class="module-kpi-detail">Maintenance + repairs</div><div class="module-kpi-icon"><i class="fas fa-tools"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Repair Jobs</div><div class="module-kpi-value"><?= array_sum(array_map(fn($r) => (int)$r['repair_jobs'], $fleetReportRows)) ?></div><div class="module-kpi-detail">Break-fix incidents</div><div class="module-kpi-icon"><i class="fas fa-wrench"></i></div></div>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Fleet Performance Report</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Vehicle</th><th>Status</th><th>Total Jobs</th><th>Maintenance</th><th>Repair</th><th>Total Cost</th><th>Last Service</th></tr></thead>
            <tbody>
              <?php if (empty($fleetReportRows)): ?><tr><td colspan="7" class="text-muted text-center py-4">No fleet report data.</td></tr><?php endif; ?>
              <?php foreach ($fleetReportRows as $r): ?>
                <tr>
                  <td><div class="fw-semibold"><?= htmlspecialchars((string)$r['vehicle_name']) ?></div><div class="text-muted small"><?= htmlspecialchars((string)$r['plate_number']) ?></div></td>
                  <td><span class="badge <?= badge_class((string)($r['status'] ?? 'Available')) ?>"><?= htmlspecialchars((string)($r['status'] ?? 'Available')) ?></span></td>
                  <td><?= (int)$r['total_jobs'] ?></td>
                  <td><?= (int)$r['maintenance_jobs'] ?></td>
                  <td><?= (int)$r['repair_jobs'] ?></td>
                  <td><?= number_format((float)$r['total_cost'], 2) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars((string)($r['last_performed_at'] ?? '-')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include "../layout/footer.php"; ?>