<?php
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/Maintenance.php";
require_once __DIR__ . "/../../config/permissions.php";
require_once __DIR__ . "/../helpers/badges.php";

requireLogin();
requireRole(['admin','manager','mro_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'mro', 'add');

$m = new Maintenance($pdo);
$logs = $m->getAll();
$activeTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'maintenance';
if (!in_array($activeTab, ['maintenance','equipment','history','reports'], true)) {
  $activeTab = 'maintenance';
}

$logTotal = count($logs);
$maintenanceCount = 0;
$repairCount = 0;
$pendingWork = 0;
$totalCost = 0.0;
foreach ($logs as $l) {
  $type = $l['type'] ?? 'Maintenance';
  if ($type === 'Repair') $repairCount++; else $maintenanceCount++;
  if (empty($l['performed_at'])) $pendingWork++;
  $totalCost += (float)($l['cost'] ?? 0);
}

$fleet  = $pdo->query("SELECT id, vehicle_name, plate_number, status FROM fleet ORDER BY vehicle_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$assets = $pdo->query("SELECT id, asset_tag, asset_name, status FROM assets ORDER BY asset_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$historyRows = array_values(array_filter($logs, static function(array $row): bool {
  return !empty($row['performed_at']);
}));

$reportByType = [
  'Maintenance' => ['count' => 0, 'cost' => 0.0],
  'Repair' => ['count' => 0, 'cost' => 0.0],
];
foreach ($logs as $row) {
  $type = ($row['type'] ?? 'Maintenance') === 'Repair' ? 'Repair' : 'Maintenance';
  $reportByType[$type]['count']++;
  $reportByType[$type]['cost'] += (float)($row['cost'] ?? 0);
}
?>

<?php require_once __DIR__ . "/../layout/header.php"; ?>
<?php require_once __DIR__ . "/../layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/../layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <div class="module-header mb-4">
      <div>
        <h2 class="mb-2">MRO: Maintenance and Repairs</h2>
        <p class="text-muted mb-0">Record and monitor maintenance and repair work for fleet and assets.</p>
      </div>
      <?php if ($canAdd && $activeTab === 'maintenance'): ?>
      <button class="btn btn-primary" data-modal-form="addMaintenanceForm" data-modal-title="Log Maintenance / Repair">
        <i class="bi bi-plus-lg"></i> New Maintenance Log
      </button>
      <?php endif; ?>
    </div>

    <div class="module-kpi-grid mb-4">
      <div class="module-kpi-card"><div class="module-kpi-label">Total Logs</div><div class="module-kpi-value"><?= $logTotal ?></div><div class="module-kpi-detail">All recorded jobs</div><div class="module-kpi-icon"><i class="fas fa-clipboard-list"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">Maintenance</div><div class="module-kpi-value"><?= $maintenanceCount ?></div><div class="module-kpi-detail">Routine service tasks</div><div class="module-kpi-icon"><i class="fas fa-wrench"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">Repairs</div><div class="module-kpi-value"><?= $repairCount ?></div><div class="module-kpi-detail">Break-fix incidents</div><div class="module-kpi-icon"><i class="fas fa-tools"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">Pending Work</div><div class="module-kpi-value"><?= $pendingWork ?></div><div class="module-kpi-detail">No completion date yet</div><div class="module-kpi-icon"><i class="fas fa-hourglass-half"></i></div></div>
    </div>

    <div class="tab-navigation mb-4">
      <a href="?tab=maintenance" class="tab-btn <?= $activeTab === 'maintenance' ? 'active' : '' ?>"><i class="bi bi-wrench"></i> Maintenance Logs</a>
      <a href="?tab=equipment" class="tab-btn <?= $activeTab === 'equipment' ? 'active' : '' ?>"><i class="bi bi-tools"></i> Equipment</a>
      <a href="?tab=history" class="tab-btn <?= $activeTab === 'history' ? 'active' : '' ?>"><i class="bi bi-clock-history"></i> History</a>
      <a href="?tab=reports" class="tab-btn <?= $activeTab === 'reports' ? 'active' : '' ?>"><i class="bi bi-graph-up"></i> Reports</a>
    </div>

    <?php if ($activeTab === 'maintenance'): ?>
      <?php if ($canAdd): ?>
      <div id="addMaintenanceForm" style="display:none;">
        <form method="POST" action="../../controllers/MaintenanceController.php" class="row g-3" id="mroForm">
          <div class="col-md-6"><label class="form-label">Fleet (optional)</label><select name="fleet_id" class="form-select" id="fleetSelect"><option value="">- Select Fleet -</option><?php foreach($fleet as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Asset (optional)</label><select class="form-select" name="asset_id" id="assetSelect"><option value="">- Select Asset -</option><?php foreach ($assets as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['asset_tag']) ?> - <?= htmlspecialchars($a['asset_name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Type</label><select name="type" class="form-select"><option value="Maintenance">Maintenance</option><option value="Repair">Repair</option></select></div>
          <div class="col-md-3"><label class="form-label">Cost</label><input type="number" step="0.01" name="cost" class="form-control" min="0" value="0"></div>
          <div class="col-md-3"><label class="form-label">Date Performed</label><input type="date" name="performed_at" class="form-control"><div class="form-text">Leave blank if this is pending.</div></div>
          <div class="col-md-3"><label class="form-label">Status</label><input class="form-control" value="Auto (based on Date)" disabled></div>
          <div class="col-12"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="2" required></textarea></div>
          <div class="col-12 d-flex gap-2"><button class="btn btn-primary flex-grow-1" name="add"><i class="bi bi-wrench-adjustable"></i> Save Log</button><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button></div>
        </form>
      </div>
      <?php endif; ?>

      <div class="table-card">
        <h5 class="mb-3">Maintenance Logs</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Date</th><th>Target</th><th>Type</th><th>Cost</th><th>Description</th><th>Recorded By</th></tr></thead>
            <tbody>
              <?php if (count($logs) === 0): ?><tr><td colspan="6" class="text-muted text-center py-4">No logs yet.</td></tr><?php endif; ?>
              <?php foreach($logs as $l): ?>
                <?php
                  $target = '-';
                  if (!empty($l['vehicle_name'])) $target = $l['vehicle_name'] . (!empty($l['plate_number']) ? ' (' . $l['plate_number'] . ')' : '');
                  elseif (!empty($l['asset_tag'])) $target = $l['asset_tag'] . ' - ' . ($l['asset_name'] ?? '');
                  $dateText = $l['performed_at'] ?? $l['created_at'];
                  $isRequest = empty($l['performed_at']);
                ?>
                <tr>
                  <td class="text-muted small"><?= htmlspecialchars((string)$dateText) ?><?php if ($isRequest): ?><span class="badge bg-secondary ms-2">Request</span><?php endif; ?></td>
                  <td><?= htmlspecialchars((string)$target) ?></td>
                  <td><span class="badge <?= ($l['type'] ?? '') === 'Repair' ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= htmlspecialchars((string)($l['type'] ?? 'Maintenance')) ?></span></td>
                  <td><?= number_format((float)($l['cost'] ?? 0), 2) ?></td>
                  <td class="text-muted"><?= htmlspecialchars((string)($l['description'] ?? '')) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars((string)($l['recorded_by_name'] ?? '-')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'equipment'): ?>
      <div class="table-card mb-3">
        <h5 class="mb-3">Equipment in Scope</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Type</th><th>Code</th><th>Name</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach($fleet as $f): ?><tr><td>Fleet</td><td><?= htmlspecialchars((string)$f['plate_number']) ?></td><td><?= htmlspecialchars((string)$f['vehicle_name']) ?></td><td><span class="badge <?= badge_class((string)($f['status'] ?? 'Available')) ?>"><?= htmlspecialchars((string)($f['status'] ?? 'Available')) ?></span></td></tr><?php endforeach; ?>
              <?php foreach($assets as $a): ?><tr><td>Asset</td><td><?= htmlspecialchars((string)$a['asset_tag']) ?></td><td><?= htmlspecialchars((string)$a['asset_name']) ?></td><td><span class="badge <?= badge_class((string)($a['status'] ?? 'Active')) ?>"><?= htmlspecialchars((string)($a['status'] ?? 'Active')) ?></span></td></tr><?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'history'): ?>
      <div class="table-card">
        <h5 class="mb-3">Maintenance History</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Date Completed</th><th>Target</th><th>Type</th><th>Cost</th><th>Description</th></tr></thead>
            <tbody>
              <?php if (empty($historyRows)): ?><tr><td colspan="5" class="text-muted text-center py-4">No completed maintenance history yet.</td></tr><?php endif; ?>
              <?php foreach($historyRows as $l): ?>
                <?php
                  $target = '-';
                  if (!empty($l['vehicle_name'])) $target = $l['vehicle_name'] . (!empty($l['plate_number']) ? ' (' . $l['plate_number'] . ')' : '');
                  elseif (!empty($l['asset_tag'])) $target = $l['asset_tag'] . ' - ' . ($l['asset_name'] ?? '');
                ?>
                <tr>
                  <td class="text-muted small"><?= htmlspecialchars((string)($l['performed_at'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string)$target) ?></td>
                  <td><span class="badge <?= ($l['type'] ?? '') === 'Repair' ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= htmlspecialchars((string)($l['type'] ?? 'Maintenance')) ?></span></td>
                  <td><?= number_format((float)($l['cost'] ?? 0), 2) ?></td>
                  <td class="text-muted"><?= htmlspecialchars((string)($l['description'] ?? '-')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'reports'): ?>
      <div class="module-kpi-grid mb-4">
        <div class="module-kpi-card"><div class="module-kpi-label">Total Cost</div><div class="module-kpi-value"><?= number_format($totalCost, 2) ?></div><div class="module-kpi-detail">Maintenance + repairs</div><div class="module-kpi-icon"><i class="fas fa-coins"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Maintenance Jobs</div><div class="module-kpi-value"><?= $reportByType['Maintenance']['count'] ?></div><div class="module-kpi-detail">Routine work orders</div><div class="module-kpi-icon"><i class="fas fa-wrench"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Repair Jobs</div><div class="module-kpi-value"><?= $reportByType['Repair']['count'] ?></div><div class="module-kpi-detail">Corrective work</div><div class="module-kpi-icon"><i class="fas fa-tools"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Pending Requests</div><div class="module-kpi-value"><?= $pendingWork ?></div><div class="module-kpi-detail">No performed date yet</div><div class="module-kpi-icon"><i class="fas fa-hourglass-half"></i></div></div>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Cost by Work Type</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Type</th><th>Jobs</th><th>Total Cost</th><th>Average Cost</th></tr></thead>
            <tbody>
              <?php foreach (['Maintenance','Repair'] as $type): ?>
                <?php
                  $count = (int)$reportByType[$type]['count'];
                  $cost = (float)$reportByType[$type]['cost'];
                  $avg = $count > 0 ? ($cost / $count) : 0;
                ?>
                <tr>
                  <td><?= htmlspecialchars($type) ?></td>
                  <td><?= $count ?></td>
                  <td><?= number_format($cost, 2) ?></td>
                  <td><?= number_format($avg, 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
(function(){
  const fleet = document.getElementById('fleetSelect');
  const asset = document.getElementById('assetSelect');
  if (!fleet || !asset) return;
  function sync() {
    const hasFleet = fleet.value !== '';
    const hasAsset = asset.value !== '';
    asset.disabled = hasFleet;
    fleet.disabled = hasAsset;
  }
  fleet.addEventListener('change', sync);
  asset.addEventListener('change', sync);
  sync();
})();
</script>

<?php require_once __DIR__ . "/../layout/footer.php"; ?>
