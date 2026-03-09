<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Maintenance.php";
require_once __DIR__ . "/helpers/badges.php";

requireLogin();
requireRole(['admin','manager','mro']);

$m = new Maintenance($pdo);
$logs = $m->getAll();
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'maintenance';

$fleet  = $pdo->query("SELECT id, vehicle_name, plate_number FROM fleet ORDER BY vehicle_name ASC")->fetchAll();
$assets = $pdo->query("SELECT id, asset_tag, asset_name FROM assets ORDER BY asset_name ASC")->fetchAll();
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>

<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <h2 class="mb-2">MRO: Maintenance & Repairs</h2>
    <p class="text-muted mb-4">Record maintenance and repair history for fleet and asset equipment.</p>

    <!-- TAB NAVIGATION -->
    <div class="tab-navigation mb-4">
      <a href="?tab=maintenance" class="tab-btn <?= $activeTab === 'maintenance' ? 'active' : '' ?>">
        <i class="bi bi-wrench"></i> Maintenance Logs
      </a>
      <a href="?tab=equipment" class="tab-btn <?= $activeTab === 'equipment' ? 'active' : '' ?>">
        <i class="bi bi-tools"></i> Equipment
      </a>
      <a href="?tab=history" class="tab-btn <?= $activeTab === 'history' ? 'active' : '' ?>">
        <i class="bi bi-clock-history"></i> History
      </a>
      <a href="?tab=reports" class="tab-btn <?= $activeTab === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Reports
      </a>
    </div>

    <!-- MAINTENANCE LOGS TAB -->
    <?php if ($activeTab === 'maintenance'): ?>
      <div class="form-card mb-4">
        <h5 class="mb-3">Log Maintenance / Repair</h5>

        <div class="alert alert-info py-2 mb-3">
          <small><strong>Note:</strong> Choose <strong>either</strong> Fleet <strong>or</strong> Asset (not both).</small>
        </div>

        <form method="POST" action="../controllers/MaintenanceController.php" class="row g-3" id="mroForm">

          <!-- Fleet Select -->
          <div class="col-md-6">
            <label class="form-label">Fleet (optional)</label>
            <select name="fleet_id" class="form-select" id="fleetSelect">
              <option value="">— Select Fleet —</option>
              <?php foreach($fleet as $f): ?>
                <option value="<?= (int)$f['id'] ?>">
                  <?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Asset Select -->
          <div class="col-md-6">
            <label class="form-label">Asset (optional)</label>
            <select class="form-select" name="asset_id" id="assetSelect">
              <option value="">— Select Asset —</option>
              <?php foreach ($assets as $a): ?>
                <option value="<?= (int)$a['id'] ?>">
                  <?= htmlspecialchars($a['asset_tag']) ?> — <?= htmlspecialchars($a['asset_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
              <option value="Maintenance">Maintenance</option>
              <option value="Repair">Repair</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Cost</label>
            <input type="number" step="0.01" name="cost" class="form-control" min="0" value="0">
          </div>

          <div class="col-md-3">
            <label class="form-label">Date Performed</label>
            <input type="date" name="performed_at" class="form-control">
            <div class="form-text">Leave blank if this is a request/pending.</div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Status</label>
            <input class="form-control" value="Auto (based on Date)" disabled>
          </div>

          <div class="col-12">
            <label class="form-label">Description *</label>
            <textarea name="description" class="form-control" rows="2" required></textarea>
          </div>

          <div class="col-12 d-grid">
            <button class="btn btn-primary" name="add">
              <i class="bi bi-wrench-adjustable"></i> Save Log
            </button>
          </div>
        </form>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Maintenance Logs</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Target</th>
                <th>Type</th>
                <th>Cost</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($logs as $l): ?>
                <?php
                  $target = '—';
                  if (!empty($l['vehicle_name'])) {
                    $target = $l['vehicle_name'] . ' (' . ($l['plate_number'] ?? '') . ')';
                  } elseif (!empty($l['asset_tag'])) {
                    $target = $l['asset_tag'] . ' — ' . ($l['asset_name'] ?? '');
                  }
                  $dateText = $l['performed_at'] ?? $l['created_at'];
                  $isRequest = empty($l['performed_at']);
                ?>
                <tr>
                  <td class="text-muted small">
                    <?= htmlspecialchars($dateText) ?>
                    <?php if ($isRequest): ?>
                      <span class="badge bg-secondary ms-2">Request</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($target) ?></td>
                  <td>
                    <span class="badge <?= ($l['type'] ?? '') === 'Repair' ? 'bg-danger' : 'bg-warning text-dark' ?>">
                      <?= htmlspecialchars($l['type'] ?? 'Maintenance') ?>
                    </span>
                  </td>
                  <td>₱<?= number_format((float)($l['cost'] ?? 0), 2) ?></td>
                  <td class="text-muted"><?= htmlspecialchars($l['description'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (count($logs) === 0): ?>
                <tr><td colspan="5" class="text-muted text-center py-4">No logs yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- EQUIPMENT TAB -->
    <?php if ($activeTab === 'equipment'): ?>
      <div class="table-card">
        <h5 class="mb-3">Equipment List</h5>
        <p class="text-muted">View all fleet and asset equipment</p>
        <div class="row mb-3">
          <div class="col-md-6">
            <h6 class="mb-2">Fleet Vehicles</h6>
            <div class="table-responsive">
              <table class="table table-sm table-hover">
                <tbody>
                  <?php foreach($fleet as $f): ?>
                  <tr>
                    <td><?= htmlspecialchars($f['vehicle_name']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($f['plate_number']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-6">
            <h6 class="mb-2">Assets</h6>
            <div class="table-responsive">
              <table class="table table-sm table-hover">
                <tbody>
                  <?php foreach($assets as $a): ?>
                  <tr>
                    <td><?= htmlspecialchars($a['asset_tag']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($a['asset_name']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- HISTORY TAB -->
    <?php if ($activeTab === 'history'): ?>
      <div class="table-card">
        <h5 class="mb-3">Maintenance History</h5>
        <p class="text-muted">Historical maintenance records</p>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Equipment</th><th>Type</th><th>Date Completed</th><th>Cost</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="4" class="text-muted text-center py-4">No historical records</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- REPORTS TAB -->
    <?php if ($activeTab === 'reports'): ?>
      <div class="table-card">
        <h5 class="mb-3">MRO Reports</h5>
        <p class="text-muted">Maintenance reports and analytics coming soon</p>
      </div>
    <?php endif; ?>

  </div>
</main>

<script>
  // UX: if Fleet chosen, disable Asset; if Asset chosen, disable Fleet
  (function(){
    const fleet = document.getElementById('fleetSelect');
    const asset = document.getElementById('assetSelect');

    function sync() {
      const hasFleet = fleet.value !== '';
      const hasAsset = asset.value !== '';

      if (hasFleet) {
        asset.disabled = true;
      } else {
        asset.disabled = false;
      }

      if (hasAsset) {
        fleet.disabled = true;
      } else {
        fleet.disabled = false;
      }
    }

    fleet.addEventListener('change', sync);
    asset.addEventListener('change', sync);
    sync();
  })();
</script>

<?php require_once __DIR__ . "/layout/footer.php"; ?>


