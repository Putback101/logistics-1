<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/flash.php";
require_once __DIR__ . "/../models/Asset.php";

requireLogin();

$role = $_SESSION['user']['role'] ?? '';

require_once __DIR__ . '/../config/permissions.php';
$canAdd = hasPermission($role, 'assets', 'add');
$canEdit = hasPermission($role, 'assets', 'edit');
$canDelete = hasPermission($role, 'assets', 'delete');

$asset = new Asset($pdo);
$rows = $asset->getAll();

$users = $pdo->query("SELECT id, fullname, role FROM users ORDER BY fullname ASC")->fetchAll();
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>


  <?php require_once __DIR__ . "/layout/sidebar.php"; ?>


    <?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h2 class="mb-1">Asset Registry</h2>
          <p class="text-muted mb-0">Central master list of all assets (tag, classification, ownership, status, location).</p>
        </div>

        <?php if ($canManage): ?>
          <?php if ($canAdd): ?>
            <button class="btn btn-primary" onclick="openAddAssetModal()">
              <i class="bi bi-plus-circle"></i> Add Asset
            </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>

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
                    <a class="btn btn-sm btn-outline-secondary"
                       href="asset_tracking.php?asset_id=<?= (int)$r['id'] ?>">
                      <i class="bi bi-geo"></i> Track
                    </a>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="asset_monitoring.php?asset_id=<?= (int)$r['id'] ?>">
                      <i class="bi bi-activity"></i> Monitor
                    </a>

                      <?php if ($canEdit): ?>
                        <a class="btn btn-sm btn-outline-primary" href="asset_edit.php?id=<?= (int)$r['id'] ?>">
                          <i class="bi bi-pencil"></i>
                        </a>
                      <?php endif; ?>
                      <?php if ($canDelete): ?>
                        <form class="d-inline" method="POST" action="../controllers/AssetController.php"
                              onsubmit="return confirm('Delete this asset?');">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <button class="btn btn-sm btn-outline-danger" name="delete_asset">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                  </td>
                </tr>

                <?php if ($canManage): ?>
                <!-- EDIT MODAL -->
                <div class="modal fade" id="editAssetModal<?= (int)$r['id'] ?>" tabindex="-1">
                  <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                      <form method="POST" action="../controllers/AssetController.php">
                        <div class="modal-header">
                          <h5 class="modal-title">Edit Asset</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                          <?php if ($canAdd): ?>
                          <!-- ADD ASSET FORM (Hidden by default, shown in modal) -->
                          <div id="addAssetForm" style="display: none;">
                            <form method="POST" action="../controllers/AssetController.php" class="row g-3">
                              <div class="col-md-4">
                                <label class="form-label">Asset Tag *</label>
                                <input class="form-control" name="asset_tag" required>
                              </div>
                              <div class="col-md-8">
                                <label class="form-label">Asset Name *</label>
                                <input class="form-control" name="asset_name" required>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <input class="form-control" name="asset_category" required>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                  <option value="Active">Active</option>
                                  <option value="In Use">In Use</option>
                                  <option value="Idle">Idle</option>
                                  <option value="Under Maintenance">Under Maintenance</option>
                                  <option value="Retired">Retired</option>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Brand</label>
                                <input class="form-control" name="brand">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Model</label>
                                <input class="form-control" name="model">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Serial No</label>
                                <input class="form-control" name="serial_no">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" name="acquisition_date">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Purchase Cost</label>
                                <input type="number" step="0.01" class="form-control" name="purchase_cost">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Location</label>
                                <input class="form-control" name="location">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label">Assigned To</label>
                                <select class="form-select" name="assigned_to">
                                  <option value="">—</option>
                                  <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-12 d-flex gap-2">
                                <button type="submit" name="add_asset" class="btn btn-primary flex-grow-1">
                                  <i class="bi bi-plus-circle"></i> Add Asset
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                              </div>
                            </form>
                          </div>
                          <?php endif; ?>

                        <div class="modal-body">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                          <div class="row g-3">
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
                                <?php
                                  $opts = ['Active','In Use','Idle','Under Maintenance','Retired'];
                                  foreach ($opts as $o):
                                ?>
                                  <option value="<?= $o ?>" <?= ($r['status']===$o?'selected':'') ?>><?= $o ?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>

                            <div class="col-md-4">
                              <label class="form-label">Brand</label>
                              <input class="form-control" name="brand" value="<?= htmlspecialchars($r['brand'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                              <label class="form-label">Model</label>
                              <input class="form-control" name="model" value="<?= htmlspecialchars($r['model'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                              <label class="form-label">Serial No</label>
                              <input class="form-control" name="serial_no" value="<?= htmlspecialchars($r['serial_no'] ?? '') ?>">
                            </div>

                            <div class="col-md-4">
                              <label class="form-label">Acquisition Date</label>
                              <input type="date" class="form-control" name="acquisition_date" value="<?= htmlspecialchars($r['acquisition_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                              <label class="form-label">Purchase Cost</label>
                              <input type="number" step="0.01" class="form-control" name="purchase_cost" value="<?= htmlspecialchars($r['purchase_cost'] ?? 0) ?>">
                            </div>
                            <div class="col-md-4">
                              <label class="form-label">Location</label>
                              <input class="form-control" name="location" value="<?= htmlspecialchars($r['location'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                              <label class="form-label">Assigned To</label>
                              <select class="form-select" name="assigned_to">
                                <option value="">— None —</option>
                                <?php foreach ($users as $u): ?>
                                  <option value="<?= (int)$u['id'] ?>" <?= ((int)$r['assigned_to']===(int)$u['id']?'selected':'') ?>>
                                    <?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </div>

                            <div class="col-md-6">
                              <label class="form-label">Notes</label>
                              <input class="form-control" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '') ?>">
                            </div>
                          </div>
                        </div>

                        <div class="modal-footer">
                          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                          <button class="btn btn-primary" name="update_asset">Save Changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
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

    </div>
  </main>
</div>

<?php if ($canManage): ?>
<!-- ADD MODAL -->
<div class="modal fade" id="addAssetModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="../controllers/AssetController.php">
        <div class="modal-header">
          <h5 class="modal-title">Add Asset</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Asset Tag *</label>
              <input class="form-control" name="asset_tag" placeholder="ASSET-0001" required>
            </div>
            <div class="col-md-8">
              <label class="form-label">Asset Name *</label>
              <input class="form-control" name="asset_name" placeholder="Forklift / Laptop / Generator" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Category *</label>
              <input class="form-control" name="asset_category" placeholder="Vehicle / Tool / IT / Equipment" required>
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

            <div class="col-md-4">
              <label class="form-label">Brand</label>
              <input class="form-control" name="brand">
            </div>
            <div class="col-md-4">
              <label class="form-label">Model</label>
              <input class="form-control" name="model">
            </div>
            <div class="col-md-4">
              <label class="form-label">Serial No</label>
              <input class="form-control" name="serial_no">
            </div>

            <div class="col-md-4">
              <label class="form-label">Acquisition Date</label>
              <input type="date" class="form-control" name="acquisition_date">
            </div>
            <div class="col-md-4">
              <label class="form-label">Purchase Cost</label>
              <input type="number" step="0.01" class="form-control" name="purchase_cost" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Location</label>
              <input class="form-control" name="location" placeholder="Warehouse A / Site B">
            </div>

            <div class="col-md-6">
              <label class="form-label">Assigned To</label>
              <select class="form-select" name="assigned_to">
                <option value="">— None —</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>">
                    <?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Notes</label>
              <input class="form-control" name="notes" placeholder="Optional notes">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" name="add_asset">Add Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/layout/footer.php"; ?>




