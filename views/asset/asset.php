<?php
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/flash.php";
require_once __DIR__ . "/../../config/permissions.php";
require_once __DIR__ . "/../../models/Asset.php";
require_once __DIR__ . "/../../models/AssetRequest.php";

requireLogin();

$role = $_SESSION['user']['role'] ?? '';
$canManage = in_array($role, ['admin','manager','asset'], true);
$canAdd = hasPermission($role, 'assets', 'add');
$canEdit = hasPermission($role, 'assets', 'edit');
$canDelete = hasPermission($role, 'assets', 'delete');
$canAddVehicle = $canManage;

$asset = new Asset($pdo);
$rows = $asset->getAll();
$users = $pdo->query("SELECT id, fullname, role FROM users ORDER BY fullname ASC")->fetchAll();
$warehouseItems = $pdo->query("SELECT i.id, i.item_name, i.stock, i.location, it.category AS item_category, it.unit AS item_unit FROM inventory i LEFT JOIN items it ON it.id = i.item_id ORDER BY i.item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$prefillSourceInventoryId = isset($_GET['source_inventory_id']) && ctype_digit((string)$_GET['source_inventory_id']) ? (int)$_GET['source_inventory_id'] : 0;
$prefillWarehouseItem = null;
if ($prefillSourceInventoryId > 0) {
  $ws = $pdo->prepare("SELECT i.id, i.item_name, i.stock, i.location, it.category AS item_category, it.unit AS item_unit FROM inventory i LEFT JOIN items it ON it.id = i.item_id WHERE i.id = ? LIMIT 1");
  $ws->execute([$prefillSourceInventoryId]);
  $prefillWarehouseItem = $ws->fetch(PDO::FETCH_ASSOC) ?: null;
}

$maxTagNo = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(asset_tag, 7) AS UNSIGNED)), 0) FROM assets WHERE asset_tag REGEXP '^ASSET-[0-9]+$'")->fetchColumn();
$autoAssetTag = 'ASSET-' . str_pad((string)($maxTagNo + 1), 4, '0', STR_PAD_LEFT);
$prefillAssetCategory = trim((string)($prefillWarehouseItem['item_category'] ?? ''));
$prefillAssetNotes = $prefillWarehouseItem ? ('Converted from warehouse item #' . (int)$prefillWarehouseItem['id']) : '';
$assetCategoryOptions = ['Vehicle', 'Tool', 'IT Equipment', 'Heavy Equipment', 'Spare Parts', 'Office Equipment', 'Warehouse Equipment'];
if ($prefillAssetCategory !== '' && !in_array($prefillAssetCategory, $assetCategoryOptions, true)) {
  $assetCategoryOptions[] = $prefillAssetCategory;
}
foreach ($rows as $assetRow) {
  $cat = trim((string)($assetRow['asset_category'] ?? ''));
  if ($cat !== '' && !in_array($cat, $assetCategoryOptions, true)) {
    $assetCategoryOptions[] = $cat;
  }
}
sort($assetCategoryOptions);

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
$assetRequests = [];
$hasAssetRequestsTable = false;
try {
  $hasAssetRequestsTable = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asset_requests'")->fetchColumn() > 0;
  if ($hasAssetRequestsTable) {
    $assetRequests = (new AssetRequest($pdo))->getAll();
  }
} catch (Throwable $e) {
  $hasAssetRequestsTable = false;
  $assetRequests = [];
}
$pendingAssetRequests = 0;
foreach ($assetRequests as $ar) {
  if (($ar['status'] ?? '') === 'Pending') {
    $pendingAssetRequests++;
  }
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
  <div class="content-area asset-skin">

    <div class="module-header mb-4">
      <div>
        <h2 class="mb-2">Asset Management</h2>
        <p class="text-muted mb-0">Manage assets, movement history, and monitoring records in one module.</p>
      </div>

      <?php if ($canAdd && $activeTab === 'registry' && $canManage): ?>
      <button class="btn btn-primary" data-modal-form="addAssetForm" data-modal-title="Choose Asset Section">
        <i class="bi bi-plus-lg"></i> New Asset
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
      <div class="module-kpi-card">
        <div class="module-kpi-label">Inbound Requests</div>
        <div class="module-kpi-value"><?= $pendingAssetRequests ?></div>
        <div class="module-kpi-detail">Pending integration requests</div>
        <div class="module-kpi-icon"><i class="fas fa-inbox"></i></div>
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
                <th>Source</th>
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
                  <td><?= htmlspecialchars($r['assigned_name'] ?? '-') ?></td>
                  <td><?php if (!empty($r['source_inventory_id'])): ?><span class="badge text-bg-light border">Warehouse #<?= (int)$r['source_inventory_id'] ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
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
                      <select class="form-select" name="asset_category" required>
                        <option value="">- Select Category -</option>
                        <?php foreach ($assetCategoryOptions as $catOpt): ?>
                          <option value="<?= htmlspecialchars($catOpt) ?>" <?= ((string)($r['asset_category'] ?? '') === $catOpt) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($catOpt) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
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
                        <option value="">- None -</option>
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
                <tr><td colspan="8" class="text-muted">No assets yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-card mt-4">
        <h5 class="mb-3">Inbound Asset Requests</h5>
        <?php if (!$hasAssetRequestsTable): ?>
          <p class="text-muted mb-0">Inbound queue is not enabled yet. Run migration `2026-03-09-asset-external-intake.sql`.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>Request Ref</th>
                  <th>Type</th>
                  <th>Source</th>
                  <th>Asset</th>
                  <th>Qty</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($assetRequests)): ?>
                  <tr><td colspan="8" class="text-center text-muted">No inbound asset requests yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($assetRequests as $ar): ?>
                  <?php
                    $sourceLabel = strtoupper((string)($ar['source_module'] ?? 'UNKNOWN'));
                    if (!empty($ar['source_system'])) {
                      $sourceLabel .= ' / ' . (string)$ar['source_system'];
                    }
                    $assetLabel = trim((string)($ar['asset_name'] ?? ''));
                    if (!empty($ar['asset_tag'])) {
                      $assetLabel = (string)$ar['asset_tag'] . ' - ' . $assetLabel;
                    }
                  ?>
                  <tr>
                    <td>
                      <?= htmlspecialchars((string)($ar['request_ref'] ?? '-')) ?>
                      <?php if (!empty($ar['source_reference'])): ?>
                        <div class="text-muted small"><?= htmlspecialchars((string)$ar['source_reference']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string)($ar['request_type'] ?? 'registration')) ?></td>
                    <td><?= htmlspecialchars($sourceLabel) ?></td>
                    <td><?= htmlspecialchars($assetLabel) ?></td>
                    <td><?= (int)($ar['quantity'] ?? 1) ?></td>
                    <td><?= htmlspecialchars((string)($ar['priority'] ?? 'Normal')) ?></td>
                    <td><span class="badge <?= ($ar['status'] ?? 'Pending') === 'Pending' ? 'bg-warning text-dark' : 'bg-secondary' ?>"><?= htmlspecialchars((string)($ar['status'] ?? 'Pending')) ?></span></td>
                    <td class="text-muted small"><?= htmlspecialchars((string)($ar['created_at'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($activeTab === 'tracking'): ?>
      <div class="table-card mt-4">
        <h5 class="mb-3">Asset List (Tracking)</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Tag</th>
                <th>Name</th>
                <th>Status</th>
                <th>Location</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="text-center text-muted">No assets available.</td></tr>
              <?php endif; ?>
              <?php foreach ($rows as $a): ?>
                <tr class="<?= ((int)$a['id'] === $selectedId) ? 'table-primary' : '' ?>">
                  <td><?= htmlspecialchars((string)$a['asset_tag']) ?></td>
                  <td><?= htmlspecialchars((string)$a['asset_name']) ?></td>
                  <td><?= htmlspecialchars((string)($a['status'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string)($a['location'] ?? '-')) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="asset.php?tab=tracking&asset_id=<?= (int)$a['id'] ?>">
                      <?= ((int)$a['id'] === $selectedId) ? 'Selected' : 'View' ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($selected): ?>
        <div class="table-card mt-4">
          <h5 class="mb-3">Current Details</h5>
          <div class="row g-3">
            <div class="col-md-3"><div class="text-muted small">Tag</div><div class="fw-semibold"><?= htmlspecialchars($selected['asset_tag']) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Status</div><div class="fw-semibold"><?= htmlspecialchars($selected['status']) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Location</div><div class="fw-semibold"><?= htmlspecialchars($selected['location'] ?? '-') ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Assigned To (User ID)</div><div class="fw-semibold"><?= htmlspecialchars($selected['assigned_to'] ?? '-') ?></div></div>
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
                    <td><?= htmlspecialchars($m['from_user_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($m['to_user_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($m['moved_by_name'] ?? '-') ?></td>
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
                <option value="">- None -</option>
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
      <?php else: ?>
        <div class="table-card mt-4">
          <p class="text-muted mb-0">Select an asset from the table above to view tracking details.</p>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="table-card mt-4">
        <h5 class="mb-3">Asset List (Monitoring)</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Tag</th>
                <th>Name</th>
                <th>Category</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="text-center text-muted">No assets available.</td></tr>
              <?php endif; ?>
              <?php foreach ($rows as $a): ?>
                <tr class="<?= ((int)$a['id'] === $selectedId) ? 'table-primary' : '' ?>">
                  <td><?= htmlspecialchars((string)$a['asset_tag']) ?></td>
                  <td><?= htmlspecialchars((string)$a['asset_name']) ?></td>
                  <td><?= htmlspecialchars((string)($a['asset_category'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string)($a['status'] ?? '-')) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="asset.php?tab=monitoring&asset_id=<?= (int)$a['id'] ?>">
                      <?= ((int)$a['id'] === $selectedId) ? 'Selected' : 'View' ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($selected): ?>
        <div class="table-card mt-4">
          <h5 class="mb-3">Current Details</h5>
          <div class="row g-3">
            <div class="col-md-3"><div class="text-muted small">Tag</div><div class="fw-semibold"><?= htmlspecialchars($selected['asset_tag']) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Status</div><div class="fw-semibold"><?= htmlspecialchars($selected['status']) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Category</div><div class="fw-semibold"><?= htmlspecialchars($selected['asset_category']) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Location</div><div class="fw-semibold"><?= htmlspecialchars($selected['location'] ?? '-') ?></div></div>
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
                    <td><?= htmlspecialchars($l['last_inspected'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($l['next_inspection'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($l['recorded_by_name'] ?? '-') ?></td>
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
      <?php else: ?>
        <div class="table-card mt-4">
          <p class="text-muted mb-0">Select an asset from the table above to view monitoring details.</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</main>

<?php if ($canManage && ($canAdd || $canAddVehicle)): ?>
<div id="addAssetForm" style="display:none;">
  <div class="row g-3">
    <div class="col-12">
      <h5 class="mb-0">Choose Asset Section</h5>
    </div>
    <div class="col-12">
      <div class="tab-navigation planning-modal-tabs" id="assetCreateSwitch">
        <?php if ($canAdd): ?><button type="button" class="tab-btn active" data-pane="assetAddPane">Asset / Equipment</button><?php endif; ?>
        <?php if ($canAddVehicle): ?><button type="button" class="tab-btn <?= !$canAdd ? 'active' : '' ?>" data-pane="vehicleAddPane">Fleet / Vehicle</button><?php endif; ?>
      </div>
    </div>

    <?php if ($canAdd): ?><div id="assetAddPane" class="asset-add-pane col-12">
      <form method="POST" action="../../controllers/AssetController.php" class="row g-3">
        <input type="hidden" name="source_inventory_id" value="<?= (int)$prefillSourceInventoryId ?>">
        <input type="hidden" name="consume_from_inventory" value="<?= $prefillSourceInventoryId > 0 ? '1' : '0' ?>">
        <input type="hidden" name="consume_qty" value="1">
        <?php if ($prefillWarehouseItem): ?>
          <div class="col-12">
            <div class="alert alert-info mb-1">Linked Warehouse Source: <strong>#<?= (int)$prefillWarehouseItem['id'] ?> - <?= htmlspecialchars((string)($prefillWarehouseItem['item_name'] ?? '')) ?></strong> (Stock: <?= (int)$prefillWarehouseItem['stock'] ?>). Saving this asset will deduct <strong>1</strong> stock.</div>
          </div>
        <?php endif; ?>

        <div class="col-md-4"><label class="form-label">Asset Tag *</label><input class="form-control" name="asset_tag" value="<?= htmlspecialchars((string)$autoAssetTag) ?>" readonly><div class="form-text">Auto-generated</div></div>
        <div class="col-md-8"><label class="form-label">Asset Name *</label><input class="form-control" name="asset_name" value="<?= htmlspecialchars((string)($prefillWarehouseItem['item_name'] ?? '')) ?>" placeholder="Forklift / Laptop / Generator" required></div>
        <div class="col-md-6">
          <label class="form-label">Category *</label>
          <select class="form-select" name="asset_category" required>
            <option value="">- Select Category -</option>
            <?php foreach ($assetCategoryOptions as $catOpt): ?>
              <option value="<?= htmlspecialchars($catOpt) ?>" <?= ($prefillAssetCategory === $catOpt) ? 'selected' : '' ?>>
                <?= htmlspecialchars($catOpt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
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
        <div class="col-md-4"><label class="form-label">Location</label><input class="form-control" name="location" value="<?= htmlspecialchars((string)($prefillWarehouseItem['location'] ?? '')) ?>" placeholder="Warehouse A / Site B"></div>
        <div class="col-md-6">
          <label class="form-label">Assigned To</label>
          <select class="form-select" name="assigned_to">
            <option value="">- None -</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="notes" value="<?= htmlspecialchars((string)$prefillAssetNotes) ?>" placeholder="Optional notes"></div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary flex-grow-1" name="add_asset">Add Asset</button>
          <button class="btn btn-secondary" type="button" onclick="closeModal()">Cancel</button>
        </div>
      </form>
    </div><?php endif; ?>

    <?php if ($canAddVehicle): ?><div id="vehicleAddPane" class="asset-add-pane col-12 <?= $canAdd ? 'd-none' : '' ?>">
      <form method="POST" action="../../controllers/FleetController.php" class="row g-3">
        <input type="hidden" name="return" value="../views/asset/asset.php?tab=registry">
        <div class="col-md-6"><label class="form-label">Vehicle Name *</label><input type="text" name="vehicle_name" class="form-control" placeholder="e.g. Delivery Truck" required></div>
        <div class="col-md-3"><label class="form-label">Plate Number *</label><input type="text" name="plate_number" class="form-control" placeholder="ABC-1234" required></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Available">Available</option><option value="In Use">In Use</option><option value="Maintenance">Maintenance</option></select></div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" name="add" class="btn btn-primary flex-grow-1"><i class="bi bi-plus-circle"></i> Add Vehicle</button>
          <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        </div>
      </form>
    </div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/../layout/footer.php"; ?>











