<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Asset.php";

requireLogin();

$role = $_SESSION['user']['role'] ?? '';
$canManage = in_array($role, ['admin','manager','asset'], true);

$asset = new Asset($pdo);
$assets = $asset->getAll();

$users = $pdo->query("SELECT id, fullname, role FROM users ORDER BY fullname ASC")->fetchAll();

$selectedId = (int)($_GET['asset_id'] ?? 0);
$selected = $selectedId ? $asset->getById($selectedId) : null;
$movements = $selectedId ? $asset->movementLog($selectedId) : [];
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>


  <?php require_once __DIR__ . "/layout/sidebar.php"; ?>


    <?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h2 class="mb-1">Asset Tracking</h2>
          <p class="text-muted mb-0">Track movements, transfers, current location, and assignment history.</p>
        </div>

        <?php if ($canManage && $selected): ?>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transferModal">
            <i class="bi bi-arrow-left-right"></i> Record Transfer
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
        <!-- TRANSFER MODAL -->
        <div class="modal fade" id="transferModal" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="POST" action="../controllers/AssetController.php">
                <div class="modal-header">
                  <h5 class="modal-title">Record Asset Transfer</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <input type="hidden" name="asset_id" value="<?= (int)$selected['id'] ?>">
                  <input type="hidden" name="from_location" value="<?= htmlspecialchars($selected['location'] ?? '') ?>">
                  <input type="hidden" name="from_user" value="<?= htmlspecialchars($selected['assigned_to'] ?? '') ?>">

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">To Location</label>
                      <input class="form-control" name="to_location" placeholder="Warehouse B / Site C" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">To User</label>
                      <select class="form-select" name="to_user">
                        <option value="">— None —</option>
                        <?php foreach ($users as $u): ?>
                          <option value="<?= (int)$u['id'] ?>">
                            <?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Transfer Date/Time</label>
                      <input type="datetime-local" class="form-control" name="moved_at"
                             value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Remarks</label>
                      <input class="form-control" name="remarks" placeholder="Reason / notes">
                    </div>
                  </div>
                </div>

                <div class="modal-footer">
                  <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-primary" name="transfer_asset">Save Transfer</button>
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




