<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Asset.php";

requireLogin();

$role = $_SESSION['user']['role'] ?? '';
$canManage = in_array($role, ['admin','manager','asset'], true);

$asset = new Asset($pdo);
$assets = $asset->getAll();

$selectedId = (int)($_GET['asset_id'] ?? 0);
$selected = $selectedId ? $asset->getById($selectedId) : null;
$logs = $selectedId ? $asset->monitorLog($selectedId) : [];
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>


  <?php require_once __DIR__ . "/layout/sidebar.php"; ?>


    <?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h2 class="mb-1">Asset Monitoring</h2>
          <p class="text-muted mb-0">Monitor asset condition, usage, inspections, and maintenance triggers.</p>
        </div>

        <?php if ($canManage && $selected): ?>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#monitorModal">
            <i class="bi bi-plus-circle"></i> Add Monitoring Log
          </button>
        <?php endif; ?>
      </div>

      <div class="table-card mt-4">
        <h5 class="mb-3">Select Asset</h5>
        <form method="GET" class="row g-2">
          <div class="col-md-6">
            <select class="form-select" name="asset_id" onchange="this.form.submit()">
              <option value="">— Choose an asset —</option>
              <?php foreach ($assets as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id']===$selectedId?'selected':'') ?>>
                  <?= htmlspecialchars($a['asset_tag']) ?> — <?= htmlspecialchars($a['asset_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>
      </div>

      <?php if ($selected): ?>
        <div class="table-card mt-4">
          <h5 class="mb-3">Current Details</h5>
          <div class="row g-3">
            <div class="col-md-3"><div class="text-muted small">Tag</div><div class="fw-semibold"><?= htmlspecialchars($selected['asset_tag']) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Status</div><div class="fw-semibold"><?= htmlspecialchars($selected['status']) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Category</div><div class="fw-semibold"><?= htmlspecialchars($selected['asset_category']) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Location</div><div class="fw-semibold"><?= htmlspecialchars($selected['location'] ?? '—') ?></div></div>
          </div>
        </div>

        <div class="table-card mt-4">
          <h5 class="mb-3">Monitoring History</h5>

          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Date Logged</th>
                  <th>Condition</th>
                  <th>Usage Hours</th>
                  <th>Last Inspected</th>
                  <th>Next Inspection</th>
                  <th>Recorded By</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $l): ?>
                  <tr>
                    <td><?= htmlspecialchars($l['created_at']) ?></td>
                    <td><?= htmlspecialchars($l['condition_status']) ?></td>
                    <td><?= htmlspecialchars($l['usage_hours']) ?></td>
                    <td><?= htmlspecialchars($l['last_inspected'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($l['next_inspection'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($l['recorded_by_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($l['remarks'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>

                <?php if (count($logs) === 0): ?>
                  <tr><td colspan="7" class="text-muted">No monitoring logs yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($canManage): ?>
        <!-- MONITOR MODAL -->
        <div class="modal fade" id="monitorModal" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="POST" action="../controllers/AssetController.php">
                <div class="modal-header">
                  <h5 class="modal-title">Add Monitoring Log</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <input type="hidden" name="asset_id" value="<?= (int)$selected['id'] ?>">

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Condition Status</label>
                      <select class="form-select" name="condition_status">
                        <option>Good</option>
                        <option>Needs Inspection</option>
                        <option>Needs Maintenance</option>
                      </select>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Usage Hours</label>
                      <input type="number" step="0.01" class="form-control" name="usage_hours" value="0">
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Last Inspected</label>
                      <input type="date" class="form-control" name="last_inspected">
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Next Inspection</label>
                      <input type="date" class="form-control" name="next_inspection">
                    </div>

                    <div class="col-md-12">
                      <label class="form-label">Remarks</label>
                      <input class="form-control" name="remarks" placeholder="Notes / findings">
                    </div>
                  </div>
                </div>

                <div class="modal-footer">
                  <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-primary" name="add_monitor">Save Log</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>

      <?php endif; ?>

    </div>
  </main>
</div>

<?php require_once __DIR__ . "/layout/footer.php"; ?>




