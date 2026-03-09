<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Project.php";
require_once __DIR__ . "/../models/ProjectTask.php";
require_once __DIR__ . "/../models/ProjectResource.php";

requireLogin();
requireRole(['admin','manager','project']);


if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) { header("Location: projects.php"); exit; }

$projectModel = new Project($pdo);
$taskModel = new ProjectTask($pdo);
$resModel = new ProjectResource($pdo);

$project = $projectModel->getById((int)$_GET['id']);
if (!$project) { header("Location: projects.php"); exit; }

$tasks = $taskModel->getByProject((int)$project['id']);
$resources = $resModel->getByProject((int)$project['id']);

// dropdown data
$users = $pdo->query("SELECT id, fullname FROM users ORDER BY fullname ASC")->fetchAll();
$fleet = $pdo->query("SELECT id, vehicle_name, plate_number FROM fleet ORDER BY vehicle_name ASC")->fetchAll();
$assets = $pdo->query("SELECT id, asset_tag, asset_name FROM assets ORDER BY asset_name ASC")->fetchAll();
?>
<?php require_once __DIR__ . "/layout/header.php"; ?>


  <?php require_once __DIR__ . "/layout/sidebar.php"; ?>

    <?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="mb-1"><?= htmlspecialchars($project['name']) ?></h2>
          <div class="text-muted small">
            <?= htmlspecialchars($project['type']) ?> •
            <?= htmlspecialchars($project['start_date'] ?? '-') ?> → <?= htmlspecialchars($project['end_date'] ?? '-') ?> •
            <?= htmlspecialchars($project['status']) ?>
          </div>
        </div>
        <a class="btn btn-outline-secondary" href="projects.php"><i class="bi bi-arrow-left"></i> Back</a>
      </div>

      <div class="row g-3">
        <!-- TASKS -->
        <div class="col-lg-7">
          <div class="form-card mb-3">
            <h5 class="mb-3">Add Task (Scheduling / Timeline)</h5>
            <form method="POST" action="../controllers/ProjectTaskController.php" class="row g-3">
              <input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>">
              <div class="col-md-6">
                <label class="form-label">Title</label>
                <input name="title" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Start</label>
                <input type="date" name="start_date" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">Due</label>
                <input type="date" name="due_date" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                  <option>Low</option><option selected>Medium</option><option>High</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option>Todo</option><option>In Progress</option><option>Done</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Assign User</label>
                <select name="assigned_user_id" class="form-select">
                  <option value="">None</option>
                  <?php foreach($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Assign Fleet</label>
                <select name="assigned_fleet_id" class="form-select">
                  <option value="">None</option>
                  <?php foreach($fleet as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
  <label class="form-label">Assign Asset</label>
  <select name="assigned_asset_id" class="form-select">
    <option value="">None</option>
    <?php foreach($assets as $a): ?>
      <option value="<?= (int)$a['id'] ?>">
        <?= htmlspecialchars($a['asset_tag'].' — '.$a['asset_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
              </div>
              <div class="col-12 d-grid">
                <button class="btn btn-primary" name="add_task"><i class="bi bi-plus-circle"></i> Add Task</button>
              </div>
            </form>
          </div>

          <div class="table-card">
            <h5 class="mb-3">Tasks</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Task</th><th>Dates</th><th>Assigned</th><th>Status</th><th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach($tasks as $t): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($t['title']) ?></div>
                      <div class="text-muted small"><?= htmlspecialchars($t['priority']) ?></div>
                    </td>
                    <td class="text-muted small">
                      <?= htmlspecialchars($t['start_date'] ?? '-') ?> → <?= htmlspecialchars($t['due_date'] ?? '-') ?>
                    </td>
                    <td class="text-muted small">
  <?= htmlspecialchars($t['assigned_user'] ?? '-') ?><br>

  <?php
    $fleetLabel = !empty($t['assigned_vehicle'])
      ? ($t['assigned_vehicle'] . (!empty($t['assigned_plate']) ? ' (' . $t['assigned_plate'] . ')' : ''))
      : '-';

    $assetLabel = !empty($t['assigned_asset_tag'])
      ? ($t['assigned_asset_tag'] . ' — ' . ($t['assigned_asset_name'] ?? ''))
      : '-';
  ?>

  Fleet: <?= htmlspecialchars($fleetLabel) ?><br>
  Asset: <?= htmlspecialchars($assetLabel) ?>
</td>

                    <td><span class="badge bg-secondary"><?= htmlspecialchars($t['status']) ?></span></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-danger"
                         href="../controllers/ProjectTaskController.php?delete_task=<?= $t['id'] ?>&project_id=<?= $project['id'] ?>"
                         onclick="return confirm('Delete this task?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- RESOURCES -->
        <div class="col-lg-5">
          <div class="form-card mb-3">
            <h5 class="mb-3">Resource Allocation</h5>
            <form method="POST" action="../controllers/ProjectResourceController.php" class="row g-3">
              <input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>">

              <div class="col-md-5">
                <label class="form-label">Resource Type</label>
                <select name="resource_type" class="form-select" id="rtype">
  <option value="User">User</option>
  <option value="Fleet">Fleet</option>
  <option value="Asset">Asset</option>
</select>
              </div>

              <div class="col-md-7">
  <label class="form-label">Resource</label>
  <select name="resource_id" class="form-select" id="resourceSelect">
    <!-- Users -->
    <?php foreach($users as $u): ?>
      <option value="<?= (int)$u['id'] ?>" data-type="User">
        <?= htmlspecialchars($u['fullname']) ?> (User)
      </option>
    <?php endforeach; ?>

    <!-- Fleet -->
    <?php foreach($fleet as $f): ?>
      <option value="<?= (int)$f['id'] ?>" data-type="Fleet">
        <?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?> (Fleet)
      </option>
    <?php endforeach; ?>

    <!-- Assets -->
    <?php foreach($assets as $a): ?>
      <option value="<?= (int)$a['id'] ?>" data-type="Asset">
        <?= htmlspecialchars($a['asset_tag'].' — '.$a['asset_name']) ?> (Asset)
      </option>
    <?php endforeach; ?>
  </select>
</div>

<script>
  (function(){
    const typeSel = document.getElementById('rtype');
    const resSel  = document.getElementById('resourceSelect');

    function filter() {
      const t = typeSel.value;
      const opts = Array.from(resSel.options);
      let firstVisible = null;

      opts.forEach(o => {
        const ok = (o.getAttribute('data-type') === t);
        o.hidden = !ok;
        if (ok && !firstVisible) firstVisible = o;
      });

      if (firstVisible) resSel.value = firstVisible.value;
    }

    typeSel.addEventListener('change', filter);
    filter();
  })();
</script>


              <div class="col-md-6">
                <label class="form-label">Role / Label</label>
                <input name="role_label" class="form-control" placeholder="e.g. Driver, Coordinator">
              </div>

              <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="allocated_from" class="form-control">
              </div>

              <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="allocated_to" class="form-control">
              </div>

              <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
              </div>

              <div class="col-12 d-grid">
                <button class="btn btn-primary" name="add_resource"><i class="bi bi-person-plus"></i> Allocate</button>
              </div>
            </form>
          </div>

          <div class="table-card">
            <h5 class="mb-3">Allocated Resources</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                  <tr><th>Resource</th><th>Period</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody>
                <?php foreach($resources as $r): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold">
                        <?= htmlspecialchars($r['resource_type'] === 'User' ? ($r['user_name'] ?? 'User') : ($r['vehicle_name'] ?? 'Fleet')) ?>
                      </div>
                      <div class="text-muted small">
                        <?= htmlspecialchars($r['role_label'] ?? '-') ?>
                      </div>
                    </td>
                    <td class="text-muted small">
                      <?= htmlspecialchars($r['allocated_from'] ?? '-') ?> → <?= htmlspecialchars($r['allocated_to'] ?? '-') ?>
                    </td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-danger"
                         href="../controllers/ProjectResourceController.php?delete_resource=<?= $r['id'] ?>&project_id=<?= $project['id'] ?>"
                         onclick="return confirm('Remove this resource?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

    </div>
  </main>
</div>

<?php require_once __DIR__ . "/layout/footer.php"; ?>




