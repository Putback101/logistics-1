<?php
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/flash.php";
require_once __DIR__ . "/../../config/permissions.php";
require_once __DIR__ . "/../../models/Asset.php";

requireLogin();

$role = $_SESSION['user']['role'] ?? '';
$canManage = in_array($role, ['admin','manager','asset'], true);
$canAdd = hasPermission($role, 'assets', 'add');
$canEdit = hasPermission($role, 'assets', 'edit');
$canDelete = hasPermission($role, 'assets', 'delete');

$asset = new Asset($pdo);
$rows = $asset->getAll();
$users = $pdo->query("SELECT id, fullname, role FROM users ORDER BY fullname ASC")->fetchAll();

$assetTotal = count($rows);
$assetActive = 0;
$assetInUse = 0;
$assetMaintenance = 0;
foreach ($rows as $assetRow) {
  $status = (string)($assetRow['status'] ?? '');
  if ($status === 'Active') $assetActive++;
  if ($status === 'In Use') $assetInUse++;
  if ($status === 'Under Maintenance') $assetMaintenance++;
}

$activeTab = $_GET['tab'] ?? 'registry';
if (!in_array($activeTab, ['registry', 'tracking', 'monitoring'], true)) {
  $activeTab = 'registry';
}

$selectedId = (int)($_GET['asset_id'] ?? 0);
$selected = $selectedId > 0 ? $asset->getById($selectedId) : null;
$movements = ($activeTab === 'tracking' && $selectedId > 0) ? $asset->movementLog($selectedId) : [];
$logs = ($activeTab === 'monitoring' && $selectedId > 0) ? $asset->monitorLog($selectedId) : [];
$assetQuery = $selectedId > 0 ? ('&asset_id=' . (int)$selectedId) : '';
?>

<?php require_once __DIR__ . "/../layout/header.php"; ?>
<?php require_once __DIR__ . "/../layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/../layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

    <div class="module-header mb-2">
      <div>
        <?php if ($activeTab === 'registry'): ?>
          <h2 class="mb-1">Asset Registry</h2>
          <p class="text-muted mb-0">Central master list of all assets (tag, classification, ownership, status, location).</p>
        <?php elseif ($activeTab === 'tracking'): ?>
          <h2 class="mb-1">Asset Tracking</h2>
          <p class="text-muted mb-0">Track movements, transfers, current location, and assignment history.</p>
        <?php else: ?>
          <h2 class="mb-1">Asset Monitoring</h2>
          <p class="text-muted mb-0">Monitor asset condition, usage, inspections, and maintenance triggers.</p>
        <?php endif; ?>
      </div>

      <?php if ($activeTab === 'registry' && $canManage && $canAdd): ?>
        <button class="btn btn-primary" data-modal-form="addAssetForm" data-modal-title="Add Asset">
          <i class="bi bi-plus-circle"></i> Add Asset
        </button>
      <?php endif; ?>

      <?php if ($activeTab === 'tracking' && $canManage && $selected): ?>
        <button class="btn btn-primary" data-modal-form="transferAssetForm" data-modal-title="Record Asset Transfer">
          <i class="bi bi-arrow-left-right"></i> Record Transfer
        </button>
      <?php endif; ?>

      <?php if ($activeTab === 'monitoring' && $canManage && $selected): ?>
        <button class="btn btn-primary" data-modal-form="monitorAssetForm" data-modal-title="Add Monitoring Log">
          <i class="bi bi-plus-circle"></i> Add Monitoring Log
        </button>
      <?php endif; ?>
    </div>

    <div class="module-kpi-grid mb-4">
      <div class="module-kpi-card">
        <div class="module-kpi-label">Total Assets</div>
        <div class="module-kpi-value"><?= $assetTotal ?></div>
        <div class="module-kpi-detail">Registered assets</div>
        <div class="module-kpi-icon"><i class="fas fa-boxes"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">Active</div>
        <div class="module-kpi-value"><?= $assetActive ?></div>
        <div class="module-kpi-detail">Ready for use</div>
        <div class="module-kpi-icon"><i class="fas fa-check-circle"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">In Use</div>
        <div class="module-kpi-value"><?= $assetInUse ?></div>
        <div class="module-kpi-detail">Currently assigned</div>
        <div class="module-kpi-icon"><i class="fas fa-briefcase"></i></div>
      </div>
      <div class="module-kpi-card">
        <div class="module-kpi-label">Under Maintenance</div>
        <div class="module-kpi-value"><?= $assetMaintenance ?></div>
        <div class="module-kpi-detail">Needs service</div>
        <div class="module-kpi-icon"><i class="fas fa-tools"></i></div>
      </div>
    </div>

    <div class="tab-navigation mb-4">
      <a href="asset.php?tab=registry" class="tab-btn <?= $activeTab === 'registry' ? 'active' : '' ?>">
        <i class="bi bi-archive"></i> Asset Registry
      </a>
      <a href="asset.php?tab=tracking<?= htmlspecialchars($assetQuery) ?>" class="tab-btn <?= $activeTab === 'tracking' ? 'active' : '' ?>">
        <i class="bi bi-geo-alt"></i> Tracking
      </a>
      <a href="asset.php?tab=monitoring<?= htmlspecialchars($assetQuery) ?>" class="tab-btn <?= $activeTab === 'monitoring' ? 'active' : '' ?>">
        <i class="bi bi-activity"></i> Monitoring
      </a>
    </div>

    <?php if ($activeTab === 'registry'): ?>
      <div class="table-card mt-4">
        <h5 class="mb-3">Asset List</h5>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Tag</th>
                <th>Name</th>
                <th>Category</th>
                <th>Status</th>
                <th>Location</th>
                <th>Assigned To</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><span class="badge text-bg-light border"><?= htmlspecialchars($r['asset_tag']) ?></span></td>
                  <td class="fw-semibold"><?= htmlspecialchars($r['asset_name']) ?></td>
                  <td><?= htmlspecialchars($r['asset_category']) ?></td>
                  <td><?= htmlspecialchars($r['status']) ?></td>
                  <td><?= htmlspecialchars($r['location'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['assigned_name'] ?? '—') ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="asset.php?tab=tracking&asset_id=<?= (int)$r['id'] ?>">
                      <i class="bi bi-geo"></i> Track
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="asset.php?tab=monitoring&asset_id=<?= (int)$r['id'] ?>">
                      <i class="bi bi-activity"></i> Monitor
                    </a>

                    <?php if ($canEdit): ?>
                      <button class="btn btn-sm btn-outline-primary" data-modal-form="editAssetForm<?= (int)$r['id'] ?>" data-modal-title="Edit Asset">
                        <i class="bi bi-pencil"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                      <form class="d-inline" method="POST" action="../../controllers/AssetController.php" onsubmit="return confirm('Delete this asset?');">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" name="delete_asset">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>

                <?php if ($canManage && $canEdit): ?>
                <div id="editAssetForm<?= (int)$r['id'] ?>" style="display:none;">
                  <form method="POST" action="../../controllers/AssetController.php" class="row g-3">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <div class="col-md-4">
                      <label class="form-label">Asset Tag *</label>
                      <input class="form-control" name="asset_tag" value="<?= htmlspecialchars($r['asset_tag']) ?>" required>
                    </div>
                    <div class="col-md-8">
                      <label class="form-label">Asset Name *</label>
                      <input class="form-control" name="asset_name" value="<?= htmlspecialchars($r['asset_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Category *</label>
                      <input class="form-control" name="asset_category" value="<?= htmlspecialchars($r['asset_category']) ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Status</label>
                      <select class="form-select" name="status">
                        <?php foreach (['Active','In Use','Idle','Under Maintenance','Retired'] as $o): ?>
                          <option value="<?= $o ?>" <?= ($r['status'] === $o ? 'selected' : '') ?>><?= $o ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Brand</label><input class="form-control" name="brand" value="<?= htmlspecialchars($r['brand'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Model</label><input class="form-control" name="model" value="<?= htmlspecialchars($r['model'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Serial No</label><input class="form-control" name="serial_no" value="<?= htmlspecialchars($r['serial_no'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Acquisition Date</label><input type="date" class="form-control" name="acquisition_date" value="<?= htmlspecialchars($r['acquisition_date'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Purchase Cost</label><input type="number" step="0.01" class="form-control" name="purchase_cost" value="<?= htmlspecialchars($r['purchase_cost'] ?? 0) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Location</label><input class="form-control" name="location" value="<?= htmlspecialchars($r['location'] ?? '') ?>"></div>
                    <div class="col-md-6">
                      <label class="form-label">Assigned To</label>
                      <select class="form-select" name="assigned_to">
                        <option value="">— None —</option>
                        <?php foreach ($users as $u): ?>
                          <option value="<?= (int)$u['id'] ?>" <?= ((int)$r['assigned_to'] === (int)$u['id'] ? 'selected' : '') ?>>
                            <?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '') ?>"></div>
                    <div class="col-12 d-flex gap-2">
                      <button class="btn btn-primary flex-grow-1" name="update_asset">Save Changes</button>
                      <button class="btn btn-secondary" type="button" onclick="closeModal()">Cancel</button>
                    </div>
                  </form>
                </div>
                <?php endif; ?>

              <?php endforeach; ?>

              <?php if (count($rows) === 0): ?>
                <tr><td colspan="7" class="text-muted">No assets yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($activeTab === 'tracking'): ?>
      <div class="table-card mt-4">
        <h5 class="mb-3">Select Asset</h5>
        <form method="GET" class="row g-2">
          <input type="hidden" name="tab" value="tracking">
          <div class="col-md-6">
            <select class="form-select" name="asset_id" onchange="this.form.submit()">
              <option value="">— Choose an asset —</option>
              <?php foreach ($rows as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id'] === $selectedId ? 'selected' : '') ?>>
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
            <div class="col-md-3"><div class="text-muted small">Location</div><div class="fw-semibold"><?= htmlspecialchars($selected['location'] ?? '—') ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Assigned To (User ID)</div><div class="fw-semibold"><?= htmlspecialchars($selected['assigned_to'] ?? '—') ?></div></div>
          </div>
        </div>

        <div class="table-card mt-4">
          <h5 class="mb-3">Movement History</h5>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Date/Time</th>
                  <th>From</th>
                  <th>To</th>
                  <th>From User</th>
                  <th>To User</th>
                  <th>Moved By</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($movements as $m): ?>
                  <tr>
                    <td><?= htmlspecialchars($m['moved_at']) ?></td>
                    <td><?= htmlspecialchars($m['from_location'] ?? '') ?></td>
                    <td><?= htmlspecialchars($m['to_location'] ?? '') ?></td>
                    <td><?= htmlspecialchars($m['from_user_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($m['to_user_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($m['moved_by_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($m['remarks'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (count($movements) === 0): ?>
                  <tr><td colspan="7" class="text-muted">No movement history yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($canManage): ?>
        <div id="transferAssetForm" style="display:none;">
          <form method="POST" action="../../controllers/AssetController.php" class="row g-3">
            <input type="hidden" name="asset_id" value="<?= (int)$selected['id'] ?>">
            <input type="hidden" name="from_location" value="<?= htmlspecialchars($selected['location'] ?? '') ?>">
            <input type="hidden" name="from_user" value="<?= htmlspecialchars($selected['assigned_to'] ?? '') ?>">
            <div class="col-md-6">
              <label class="form-label">To Location</label>
              <input class="form-control" name="to_location" placeholder="Warehouse B / Site C" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">To User</label>
              <select class="form-select" name="to_user">
                <option value="">— None —</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Transfer Date/Time</label>
              <input type="datetime-local" class="form-control" name="moved_at" value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Remarks</label>
              <input class="form-control" name="remarks" placeholder="Reason / notes">
            </div>
            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary flex-grow-1" name="transfer_asset">Save Transfer</button>
              <button class="btn btn-secondary" type="button" onclick="closeModal()">Cancel</button>
            </div>
          </form>
        </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php else: ?>
      <div class="table-card mt-4">
        <h5 class="mb-3">Select Asset</h5>
        <form method="GET" class="row g-2">
          <input type="hidden" name="tab" value="monitoring">
          <div class="col-md-6">
            <select class="form-select" name="asset_id" onchange="this.form.submit()">
              <option value="">— Choose an asset —</option>
              <?php foreach ($rows as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id'] === $selectedId ? 'selected' : '') ?>>
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
        <div id="monitorAssetForm" style="display:none;">
          <form method="POST" action="../../controllers/AssetController.php" class="row g-3">
            <input type="hidden" name="asset_id" value="<?= (int)$selected['id'] ?>">
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
            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary flex-grow-1" name="add_monitor">Save Log</button>
              <button class="btn btn-secondary" type="button" onclick="closeModal()">Cancel</button>
            </div>
          </form>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</main>

<?php if ($canManage && $canAdd): ?>
<div id="addAssetForm" style="display:none;">
  <form method="POST" action="../../controllers/AssetController.php" class="row g-3">
    <div class="col-md-4"><label class="form-label">Asset Tag *</label><input class="form-control" name="asset_tag" placeholder="ASSET-0001" required></div>
    <div class="col-md-8"><label class="form-label">Asset Name *</label><input class="form-control" name="asset_name" placeholder="Forklift / Laptop / Generator" required></div>
    <div class="col-md-6"><label class="form-label">Category *</label><input class="form-control" name="asset_category" placeholder="Vehicle / Tool / IT / Equipment" required></div>
    <div class="col-md-6">
      <label class="form-label">Status</label>
      <select class="form-select" name="status">
        <option>Active</option>
        <option>In Use</option>
        <option>Idle</option>
        <option>Under Maintenance</option>
        <option>Retired</option>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Brand</label><input class="form-control" name="brand"></div>
    <div class="col-md-4"><label class="form-label">Model</label><input class="form-control" name="model"></div>
    <div class="col-md-4"><label class="form-label">Serial No</label><input class="form-control" name="serial_no"></div>
    <div class="col-md-4"><label class="form-label">Acquisition Date</label><input type="date" class="form-control" name="acquisition_date"></div>
    <div class="col-md-4"><label class="form-label">Purchase Cost</label><input type="number" step="0.01" class="form-control" name="purchase_cost" value="0"></div>
    <div class="col-md-4"><label class="form-label">Location</label><input class="form-control" name="location" placeholder="Warehouse A / Site B"></div>
    <div class="col-md-6">
      <label class="form-label">Assigned To</label>
      <select class="form-select" name="assigned_to">
        <option value="">— None —</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="notes" placeholder="Optional notes"></div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary flex-grow-1" name="add_asset">Add Asset</button>
      <button class="btn btn-secondary" type="button" onclick="closeModal()">Cancel</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/../layout/footer.php"; ?>


