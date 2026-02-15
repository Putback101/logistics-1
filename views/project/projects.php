<?php
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/permissions.php";
require_once __DIR__ . "/../../models/Project.php";
require_once __DIR__ . "/../../models/ProjectTask.php";
require_once __DIR__ . "/../../models/ProjectResource.php";

requireLogin();
requireRole(['admin','manager','project_staff']);

$projectModel = new Project($pdo);
$taskModel = new ProjectTask($pdo);
$resModel = new ProjectResource($pdo);

$projects = $projectModel->getAll();
$rawTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'planning';
$tabAliases = [
  'projects' => 'planning',
  'tasks' => 'scheduling',
  'reports' => 'timeline',
];
$activeTab = $tabAliases[$rawTab] ?? $rawTab;
if (!in_array($activeTab, ['planning','scheduling','fleet-expansion','timeline','resources'], true)) {
  $activeTab = 'planning';
}

$selectedProjectId = (isset($_GET['project_id']) && ctype_digit((string)$_GET['project_id'])) ? (int)$_GET['project_id'] : 0;
$selectedProject = $selectedProjectId > 0 ? $projectModel->getById($selectedProjectId) : null;
if (!$selectedProject) {
  $selectedProjectId = 0;
}
$openModal = isset($_GET['open_modal']) ? (string)$_GET['open_modal'] : '';
$requestedPlanningSection = isset($_GET['planning_section']) ? (string)$_GET['planning_section'] : 'work-breakdown';
if (!in_array($requestedPlanningSection, ['work-breakdown', 'capacity-planning'], true)) {
  $requestedPlanningSection = 'work-breakdown';
}

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'projects', 'add');
$canEdit = hasPermission($userRole, 'projects', 'edit');
$canDelete = hasPermission($userRole, 'projects', 'delete');

$projectTotal = count($projects);
$projectOngoing = 0;
$projectPlanned = 0;
$projectCompleted = 0;
$projectOnHold = 0;
foreach ($projects as $p) {
  $status = $p['status'] ?? 'Planned';
  if ($status === 'Ongoing') $projectOngoing++;
  if ($status === 'Planned') $projectPlanned++;
  if ($status === 'Completed') $projectCompleted++;
  if ($status === 'On Hold') $projectOnHold++;
}

$taskSummaryByProject = [];
$taskSummaryRows = $pdo->query("\n  SELECT
    project_id,
    COUNT(*) AS total_tasks,
    SUM(CASE WHEN status='Done' THEN 1 ELSE 0 END) AS done_tasks,
    SUM(CASE WHEN priority='High' THEN 1 ELSE 0 END) AS milestone_tasks,
    SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status <> 'Done' THEN 1 ELSE 0 END) AS overdue_tasks
  FROM project_tasks
  GROUP BY project_id
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($taskSummaryRows as $row) {
  $taskSummaryByProject[(int)$row['project_id']] = [
    'total' => (int)($row['total_tasks'] ?? 0),
    'done' => (int)($row['done_tasks'] ?? 0),
    'milestones' => (int)($row['milestone_tasks'] ?? 0),
    'overdue' => (int)($row['overdue_tasks'] ?? 0),
  ];
}

$resourceSummaryByProject = [];
$resourceSummaryRows = $pdo->query("\n  SELECT
    project_id,
    COUNT(*) AS total_resources,
    SUM(CASE WHEN resource_type='User' THEN 1 ELSE 0 END) AS user_resources,
    SUM(CASE WHEN resource_type='Fleet' THEN 1 ELSE 0 END) AS fleet_resources,
    SUM(CASE WHEN resource_type='Asset' THEN 1 ELSE 0 END) AS asset_resources
  FROM project_resources
  GROUP BY project_id
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($resourceSummaryRows as $row) {
  $resourceSummaryByProject[(int)$row['project_id']] = [
    'total' => (int)($row['total_resources'] ?? 0),
    'users' => (int)($row['user_resources'] ?? 0),
    'fleet' => (int)($row['fleet_resources'] ?? 0),
    'assets' => (int)($row['asset_resources'] ?? 0),
  ];
}

$tasksAll = $pdo->query("\n  SELECT t.*, p.name AS project_name, u.fullname AS assigned_user
  FROM project_tasks t
  LEFT JOIN projects p ON p.id = t.project_id
  LEFT JOIN users u ON u.id = t.assigned_user_id
  ORDER BY COALESCE(t.due_date,'9999-12-31') ASC, t.id DESC
  LIMIT 300
")->fetchAll(PDO::FETCH_ASSOC);

$resourcesAll = $pdo->query("\n  SELECT r.*, p.name AS project_name,
         u.fullname AS user_name,
         f.vehicle_name AS vehicle_name, f.plate_number AS plate_number,
         a.asset_tag AS asset_tag, a.asset_name AS asset_name
  FROM project_resources r
  LEFT JOIN projects p ON p.id = r.project_id
  LEFT JOIN users  u ON (r.resource_type='User'  AND u.id=r.resource_id)
  LEFT JOIN fleet  f ON (r.resource_type='Fleet' AND f.id=r.resource_id)
  LEFT JOIN assets a ON (r.resource_type='Asset' AND a.id=r.resource_id)
  ORDER BY r.created_at DESC
  LIMIT 300
")->fetchAll(PDO::FETCH_ASSOC);

$timelineRows = $pdo->query("\n  SELECT p.id, p.name, p.type, p.start_date, p.end_date, p.status,
         COUNT(t.id) AS total_tasks,
         SUM(CASE WHEN t.status = 'Done' THEN 1 ELSE 0 END) AS done_tasks
  FROM projects p
  LEFT JOIN project_tasks t ON t.project_id = p.id
  GROUP BY p.id
  ORDER BY COALESCE(p.start_date, '9999-12-31') ASC, p.created_at DESC
  LIMIT 300
")->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('parse_fleet_expansion_meta')) {
  function parse_fleet_expansion_meta(string $description): array {
    $target = 0;
    $vehiclePlan = '';
    if (preg_match('/Target\\s*Units\\s*:\\s*(\\d+)/i', $description, $m)) {
      $target = (int)$m[1];
    }
    if (preg_match('/Vehicle\\s*Plan\\s*:\\s*(.+)/i', $description, $m)) {
      $vehiclePlan = trim($m[1]);
    }
    return [
      'target_units' => max(0, $target),
      'vehicle_plan' => $vehiclePlan,
    ];
  }
}

$fleetExpansionProjects = array_values(array_filter($projects, static function(array $p): bool {
  return (($p['type'] ?? '') === 'Fleet Expansion');
}));
$fleetCounts = $pdo->query("SELECT status, COUNT(*) AS c FROM fleet GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$fleetTotal = (int)array_sum(array_map('intval', $fleetCounts ?: []));

$fleetAllocRows = $pdo->query("\n  SELECT project_id, COUNT(*) AS allocated_units
  FROM project_resources
  WHERE resource_type='Fleet'
  GROUP BY project_id
")->fetchAll(PDO::FETCH_ASSOC);
$fleetAllocByProject = [];
foreach ($fleetAllocRows as $row) {
  $fleetAllocByProject[(int)$row['project_id']] = (int)$row['allocated_units'];
}

$fleetExpansionMeta = [];
$totalExpansionTarget = 0;
$totalExpansionAllocated = 0;
foreach ($fleetExpansionProjects as $p) {
  $meta = parse_fleet_expansion_meta((string)($p['description'] ?? ''));
  $pid = (int)($p['id'] ?? 0);
  $allocated = (int)($fleetAllocByProject[$pid] ?? 0);
  $target = (int)$meta['target_units'];
  $gap = max(0, $target - $allocated);
  $progress = $target > 0 ? min(100, (int)round(($allocated / $target) * 100)) : 0;

  $totalExpansionTarget += $target;
  $totalExpansionAllocated += $allocated;

  $fleetExpansionMeta[] = [
    'project' => $p,
    'vehicle_plan' => $meta['vehicle_plan'],
    'target_units' => $target,
    'allocated_units' => $allocated,
    'gap_units' => $gap,
    'progress_pct' => $progress,
  ];
}
$totalExpansionGap = max(0, $totalExpansionTarget - $totalExpansionAllocated);

$projectTasks = [];
$projectResources = [];
$users = $pdo->query("SELECT id, fullname FROM users ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);
$fleet = $pdo->query("SELECT id, vehicle_name, plate_number FROM fleet ORDER BY vehicle_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$assets = $pdo->query("SELECT id, asset_tag, asset_name FROM assets ORDER BY asset_name ASC")->fetchAll(PDO::FETCH_ASSOC);
if ($selectedProjectId > 0) {
  $projectTasks = $taskModel->getByProject($selectedProjectId);
  $projectResources = $resModel->getByProject($selectedProjectId);
}
?>
<?php require_once __DIR__ . "/../layout/header.php"; ?>
<?php require_once __DIR__ . "/../layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/../layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <div class="module-header mb-4">
      <div>
        <h2 class="mb-2">Project Management</h2>
        <p class="text-muted mb-0">Plan projects, schedule milestones, manage timelines, and allocate resources.</p>
      </div>
      <?php if ($canAdd && $activeTab === 'planning'): ?>
      <button class="btn btn-primary" data-modal-form="addProjectForm" data-modal-title="Create Project">
        <i class="bi bi-plus-lg"></i> New Project
      </button>
      <?php endif; ?>
      <?php if ($canAdd && $activeTab === 'fleet-expansion'): ?>
      <button class="btn btn-primary" data-modal-form="addFleetExpansionForm" data-modal-title="New Fleet Expansion Project">
        <i class="bi bi-plus-lg"></i> New Fleet Expansion
      </button>
      <?php endif; ?>
    </div>

    <div class="module-kpi-grid mb-4">
      <div class="module-kpi-card"><div class="module-kpi-label">Total Projects</div><div class="module-kpi-value"><?= $projectTotal ?></div><div class="module-kpi-detail"><?= $projectPlanned ?> planned</div><div class="module-kpi-icon"><i class="fas fa-folder-open"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">Ongoing</div><div class="module-kpi-value"><?= $projectOngoing ?></div><div class="module-kpi-detail">Currently active</div><div class="module-kpi-icon"><i class="fas fa-project-diagram"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">Completed</div><div class="module-kpi-value"><?= $projectCompleted ?></div><div class="module-kpi-detail">Finished projects</div><div class="module-kpi-icon"><i class="fas fa-check-circle"></i></div></div>
      <div class="module-kpi-card"><div class="module-kpi-label">Fleet Expansion</div><div class="module-kpi-value"><?= count($fleetExpansionProjects) ?></div><div class="module-kpi-detail">Expansion initiatives</div><div class="module-kpi-icon"><i class="fas fa-truck-moving"></i></div></div>
    </div>

    <div class="tab-navigation mb-4">
      <a href="?tab=planning" class="tab-btn <?= $activeTab === 'planning' ? 'active' : '' ?>"><i class="bi bi-journal-check"></i> Project Planning</a>
      <a href="?tab=scheduling" class="tab-btn <?= $activeTab === 'scheduling' ? 'active' : '' ?>"><i class="bi bi-calendar-week"></i> Scheduling</a>
      <a href="?tab=fleet-expansion" class="tab-btn <?= $activeTab === 'fleet-expansion' ? 'active' : '' ?>"><i class="bi bi-truck"></i> Fleet Expansion Project</a>
      <a href="?tab=timeline" class="tab-btn <?= $activeTab === 'timeline' ? 'active' : '' ?>"><i class="bi bi-clock-history"></i> Timeline Management</a>
      <a href="?tab=resources" class="tab-btn <?= $activeTab === 'resources' ? 'active' : '' ?>"><i class="bi bi-people"></i> Resource Allocation</a>
    </div>
    <?php if ($activeTab === 'planning'): ?>
      <div class="table-card mb-4">
        <h5 class="mb-3">Project Planning Board</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr><th>Project</th><th>Scope Snapshot</th><th>Planning Window</th><th>Milestones</th><th>Readiness</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach($projects as $p): ?>
              <?php
                $pid = (int)($p['id'] ?? 0);
                $taskStats = $taskSummaryByProject[$pid] ?? ['total' => 0, 'done' => 0, 'milestones' => 0, 'overdue' => 0];
                $resStats = $resourceSummaryByProject[$pid] ?? ['total' => 0, 'users' => 0, 'fleet' => 0, 'assets' => 0];
                $scopeRaw = trim((string)($p['description'] ?? ''));
                $scopePreview = $scopeRaw !== ''
                  ? (function_exists('mb_strimwidth') ? mb_strimwidth($scopeRaw, 0, 90, '...') : (strlen($scopeRaw) > 90 ? substr($scopeRaw, 0, 90) . '...' : $scopeRaw))
                  : 'No charter summary yet.';
                $hasWindow = !empty($p['start_date']) && !empty($p['end_date']);
                $readinessScore = 0;
                if ($scopeRaw !== '') $readinessScore++;
                if ($hasWindow) $readinessScore++;
                if (($taskStats['total'] ?? 0) > 0) $readinessScore++;
                if (($resStats['users'] ?? 0) > 0) $readinessScore++;
                $readinessLabel = 'Low';
                $readinessClass = 'bg-danger';
                if ($readinessScore >= 4) { $readinessLabel = 'Ready'; $readinessClass = 'bg-success'; }
                elseif ($readinessScore >= 2) { $readinessLabel = 'In Progress'; $readinessClass = 'bg-warning text-dark'; }
              ?>
              <tr>
                <td><div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div><div class="text-muted small"><?= htmlspecialchars($p['type']) ?></div></td>
                <td><?= htmlspecialchars($scopePreview) ?></td>
                <td class="text-muted small"><?= htmlspecialchars($p['start_date'] ?? '-') ?> -> <?= htmlspecialchars($p['end_date'] ?? '-') ?></td>
                <td><span class="badge bg-secondary"><?= (int)$taskStats['done'] ?>/<?= (int)$taskStats['total'] ?></span> <small class="text-muted">(<?= (int)$taskStats['milestones'] ?> high, <?= (int)$taskStats['overdue'] ?> overdue)</small></td>
                <td><span class="badge <?= $readinessClass ?>"><?= htmlspecialchars($readinessLabel) ?></span></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($p['status']) ?></span></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-primary project-open-trigger"
                    data-modal-form="viewProjectForm" data-modal-title="Project Overview"
                    data-open-url="projects.php?tab=planning&project_id=<?= (int)$p['id'] ?>#project-focus"
                    data-id="<?= (int)$p['id'] ?>"
                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                    data-type="<?= htmlspecialchars($p['type'], ENT_QUOTES) ?>"
                    data-description="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>"
                    data-start="<?= htmlspecialchars($p['start_date'] ?? '', ENT_QUOTES) ?>"
                    data-end="<?= htmlspecialchars($p['end_date'] ?? '', ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($p['status'] ?? 'Planned', ENT_QUOTES) ?>">
                    <i class="bi bi-kanban"></i> Open
                  </button>
                  <?php if ($canEdit): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary project-edit-trigger"
                    data-modal-form="editProjectForm" data-modal-title="Edit Project"
                    data-id="<?= (int)$p['id'] ?>"
                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                    data-type="<?= htmlspecialchars($p['type'], ENT_QUOTES) ?>"
                    data-description="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>"
                    data-start="<?= htmlspecialchars($p['start_date'] ?? '', ENT_QUOTES) ?>"
                    data-end="<?= htmlspecialchars($p['end_date'] ?? '', ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($p['status'] ?? 'Planned', ENT_QUOTES) ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                  <a class="btn btn-sm btn-outline-danger" href="../../controllers/ProjectController.php?delete=<?= (int)$p['id'] ?>" onclick="return confirm('Delete this project?')"><i class="bi bi-trash"></i></a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($projects)): ?><tr><td colspan="7" class="text-muted text-center py-4">No projects found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($selectedProjectId > 0 && $selectedProject && $openModal !== 'planningBoardSwitchForm'): ?>
      <div class="module-header mb-3" id="project-focus">
        <div>
          <h2 class="mb-1"><?= htmlspecialchars($selectedProject['name']) ?></h2>
          <div class="text-muted small"><?= htmlspecialchars($selectedProject['type']) ?> | <?= htmlspecialchars($selectedProject['start_date'] ?? '-') ?> -> <?= htmlspecialchars($selectedProject['end_date'] ?? '-') ?> | <?= htmlspecialchars($selectedProject['status']) ?></div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="form-card mb-3">
            <h5 class="mb-3">Work Breakdown and Milestones</h5>
            <form method="POST" action="../../controllers/ProjectTaskController.php" class="row g-3">
              <input type="hidden" name="project_id" value="<?= (int)$selectedProject['id'] ?>">
              <div class="col-md-6"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
              <div class="col-md-3"><label class="form-label">Start</label><input type="date" name="start_date" class="form-control"></div>
              <div class="col-md-3"><label class="form-label">Due</label><input type="date" name="due_date" class="form-control"></div>
              <div class="col-md-3"><label class="form-label">Priority</label><select name="priority" class="form-select"><option>Low</option><option selected>Medium</option><option>High</option></select></div>
              <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option>Todo</option><option>In Progress</option><option>Done</option></select></div>
              <div class="col-md-3"><label class="form-label">Assign User</label><select name="assigned_user_id" class="form-select"><option value="">None</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label">Assign Fleet</label><select name="assigned_fleet_id" class="form-select"><option value="">None</option><?php foreach($fleet as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label">Assign Asset</label><select name="assigned_asset_id" class="form-select"><option value="">None</option><?php foreach($assets as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['asset_tag'].' - '.$a['asset_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
              <div class="col-12 d-grid"><button class="btn btn-primary" name="add_task" <?= $canEdit ? '' : 'disabled' ?>><i class="bi bi-plus-circle"></i> Add Task</button></div>
            </form>
          </div>

          <div class="table-card">
            <h5 class="mb-3">Milestone and Task Plan</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle">
                <thead class="table-light"><tr><th>Task</th><th>Dates</th><th>Assigned</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach($projectTasks as $t): ?>
                  <tr>
                    <td><div class="fw-semibold"><?= htmlspecialchars($t['title']) ?></div><div class="text-muted small"><?= htmlspecialchars($t['priority']) ?></div></td>
                    <td class="text-muted small"><?= htmlspecialchars($t['start_date'] ?? '-') ?> -> <?= htmlspecialchars($t['due_date'] ?? '-') ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($t['assigned_user'] ?? '-') ?><br>Fleet: <?= htmlspecialchars(!empty($t['assigned_vehicle']) ? ($t['assigned_vehicle'] . (!empty($t['assigned_plate']) ? ' (' . $t['assigned_plate'] . ')' : '')) : '-') ?><br>Asset: <?= htmlspecialchars(!empty($t['assigned_asset_tag']) ? ($t['assigned_asset_tag'] . ' - ' . ($t['assigned_asset_name'] ?? '')) : '-') ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($t['status']) ?></span></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-danger <?= $canEdit ? '' : 'disabled pe-none' ?>" href="../../controllers/ProjectTaskController.php?delete_task=<?= (int)$t['id'] ?>&project_id=<?= (int)$selectedProject['id'] ?>" onclick="return confirm('Delete this task?')"><i class="bi bi-trash"></i></a></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($projectTasks)): ?><tr><td colspan="5" class="text-muted text-center py-4">No tasks yet.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="form-card mb-3">
            <h5 class="mb-3">Capacity Planning</h5>
            <form method="POST" action="../../controllers/ProjectResourceController.php" class="row g-3">
              <input type="hidden" name="project_id" value="<?= (int)$selectedProject['id'] ?>">
              <div class="col-md-5"><label class="form-label">Resource Type</label><select name="resource_type" class="form-select" id="rtype"><option value="User">User</option><option value="Fleet">Fleet</option><option value="Asset">Asset</option></select></div>
              <div class="col-md-7"><label class="form-label">Resource</label><select name="resource_id" class="form-select" id="resourceSelect"><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" data-type="User"><?= htmlspecialchars($u['fullname']) ?> (User)</option><?php endforeach; ?><?php foreach($fleet as $f): ?><option value="<?= (int)$f['id'] ?>" data-type="Fleet"><?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?> (Fleet)</option><?php endforeach; ?><?php foreach($assets as $a): ?><option value="<?= (int)$a['id'] ?>" data-type="Asset"><?= htmlspecialchars($a['asset_tag'].' - '.$a['asset_name']) ?> (Asset)</option><?php endforeach; ?></select></div>
              <div class="col-md-6"><label class="form-label">Role / Label</label><input name="role_label" class="form-control" placeholder="e.g. Driver, Coordinator"></div>
              <div class="col-md-3"><label class="form-label">From</label><input type="date" name="allocated_from" class="form-control"></div>
              <div class="col-md-3"><label class="form-label">To</label><input type="date" name="allocated_to" class="form-control"></div>
              <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
              <div class="col-12 d-grid"><button class="btn btn-primary" name="add_resource" <?= $canEdit ? '' : 'disabled' ?>><i class="bi bi-person-plus"></i> Allocate</button></div>
            </form>
          </div>

          <div class="table-card">
            <h5 class="mb-3">Planned Resource Assignments</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle">
                <thead class="table-light"><tr><th>Resource</th><th>Period</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach($projectResources as $r): ?>
                  <?php
                    $resourceName = 'Resource';
                    if (($r['resource_type'] ?? '') === 'User') $resourceName = $r['user_name'] ?? 'User';
                    elseif (($r['resource_type'] ?? '') === 'Fleet') $resourceName = $r['vehicle_name'] ?? 'Fleet';
                    elseif (($r['resource_type'] ?? '') === 'Asset') {
                      $resourceName = trim((string)(($r['asset_tag'] ?? '') . ' ' . ($r['asset_name'] ?? '')));
                      if ($resourceName === '') $resourceName = 'Asset';
                    }
                  ?>
                  <tr>
                    <td><div class="fw-semibold"><?= htmlspecialchars($resourceName) ?></div><div class="text-muted small"><?= htmlspecialchars($r['role_label'] ?? '-') ?></div></td>
                    <td class="text-muted small"><?= htmlspecialchars($r['allocated_from'] ?? '-') ?> -> <?= htmlspecialchars($r['allocated_to'] ?? '-') ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-danger <?= $canEdit ? '' : 'disabled pe-none' ?>" href="../../controllers/ProjectResourceController.php?delete_resource=<?= (int)$r['id'] ?>&project_id=<?= (int)$selectedProject['id'] ?>" onclick="return confirm('Remove this resource?')"><i class="bi bi-trash"></i></a></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($projectResources)): ?><tr><td colspan="3" class="text-muted text-center py-4">No resources assigned yet.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canAdd): ?>
      <div id="addProjectForm" style="display:none;">
        <form method="POST" action="../../controllers/ProjectController.php" class="row g-3">
          <div class="col-md-4"><label class="form-label">Project Name</label><input name="name" class="form-control" required></div>
          <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="General">General</option><option value="Fleet Expansion">Fleet Expansion</option></select></div>
          <div class="col-md-2"><label class="form-label">Start</label><input type="date" name="start_date" class="form-control"></div>
          <div class="col-md-2"><label class="form-label">End</label><input type="date" name="end_date" class="form-control"></div>
          <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option>Planned</option><option>Ongoing</option><option>Completed</option><option>On Hold</option></select></div>
          <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
          <div class="col-12 d-flex gap-2"><button type="submit" name="add" class="btn btn-primary flex-grow-1"><i class="bi bi-plus-circle"></i> Create Project</button><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button></div>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($canEdit): ?>
      <div id="viewProjectForm" style="display:none;">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Project Name</label><input id="view_project_name" class="form-control" readonly></div>
          <div class="col-md-6"><label class="form-label">Type</label><input id="view_project_type" class="form-control" readonly></div>
          <div class="col-md-4"><label class="form-label">Start</label><input id="view_project_start" class="form-control" readonly></div>
          <div class="col-md-4"><label class="form-label">End</label><input id="view_project_end" class="form-control" readonly></div>
          <div class="col-md-4"><label class="form-label">Status</label><input id="view_project_status" class="form-control" readonly></div>
          <div class="col-12"><label class="form-label">Description</label><textarea id="view_project_description" class="form-control" rows="4" readonly></textarea></div>
          <div class="col-12 d-flex gap-2">
<button type="button" id="view_project_open_link" class="btn btn-primary flex-grow-1 project-board-switch-trigger" data-modal-form="planningBoardSwitchForm" data-modal-title="Choose Planning Section" data-project-id="<?= (int)$selectedProjectId ?>" data-planning-section="<?= htmlspecialchars($requestedPlanningSection, ENT_QUOTES) ?>">Open Full Planning Board</button>
          </div>
        </div>
      </div>
      <div id="planningBoardSwitchForm" style="display:none;">
        <div class="row g-3">
          <div class="col-12"><div class="tab-navigation planning-modal-tabs" id="planning_board_switches">
            <button type="button" class="tab-btn planning-switch-btn" data-target="work-breakdown">Work Breakdown and Milestones</button>
            <button type="button" class="tab-btn planning-switch-btn" data-target="capacity-planning">Capacity Planning</button>
          </div></div>
          <div class="col-12">
            <div class="form-card" style="padding:12px; min-height:220px;">
              <div id="planning_board_preview_content"></div>
            </div>
            <div id="planning_preview_work_breakdown_template" class="d-none">
              <h6 class="mb-3">Work Breakdown and Milestones</h6>
              <form method="POST" action="../../controllers/ProjectTaskController.php" class="row g-2 mb-3">
                <input type="hidden" name="project_id" class="planning-project-id" value="">
                <div class="col-md-6"><label class="form-label small">Title</label><input name="title" class="form-control" placeholder="Task title" required></div>
                <div class="col-md-3"><label class="form-label small">Start</label><input type="date" name="start_date" class="form-control"></div>
                <div class="col-md-3"><label class="form-label small">Due</label><input type="date" name="due_date" class="form-control"></div>
                <div class="col-md-3"><label class="form-label small">Priority</label><select name="priority" class="form-select"><option>Low</option><option selected>Medium</option><option>High</option></select></div>
                <div class="col-md-3"><label class="form-label small">Status</label><select name="status" class="form-select"><option>Todo</option><option>In Progress</option><option>Done</option></select></div>
                <div class="col-md-3"><label class="form-label small">Assign User</label><select name="assigned_user_id" class="form-select"><option value="">None</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small">Assign Fleet</label><select name="assigned_fleet_id" class="form-select"><option value="">None</option><?php foreach($fleet as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small">Assign Asset</label><select name="assigned_asset_id" class="form-select"><option value="">None</option><?php foreach($assets as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['asset_tag'].' - '.$a['asset_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label small">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="col-12"><button type="submit" class="btn btn-primary w-100" name="add_task"><i class="bi bi-plus-circle"></i> Add Task</button></div>
              </form>
              <h6 class="mb-2">Milestone and Task Plan</h6>
              <div class="table-card p-0 mb-0" style="overflow:hidden;">
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Task</th><th>Dates</th><th>Assigned</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody><tr><td colspan="5" class="text-muted text-center py-3">Save task to view project table.</td></tr></tbody>
                  </table>
                </div>
              </div>
            </div>
            <div id="planning_preview_capacity_template" class="d-none">
              <h6 class="mb-3">Capacity Planning</h6>
              <form method="POST" action="../../controllers/ProjectResourceController.php" class="row g-2 mb-3 planning-capacity-form">
                <input type="hidden" name="project_id" class="planning-project-id" value="">
                <div class="col-md-6"><label class="form-label small">Resource Type</label>
                  <select name="resource_type" class="form-select planning-resource-type">
                    <option value="User">User</option>
                    <option value="Fleet">Fleet</option>
                    <option value="Asset">Asset</option>
                  </select>
                </div>
                <div class="col-md-6"><label class="form-label small">Resource</label>
                  <select name="resource_id" class="form-select planning-resource-select" required>
                    <?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" data-type="User"><?= htmlspecialchars($u['fullname']) ?> (User)</option><?php endforeach; ?>
                    <?php foreach($fleet as $f): ?><option value="<?= (int)$f['id'] ?>" data-type="Fleet"><?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?> (Fleet)</option><?php endforeach; ?>
                    <?php foreach($assets as $a): ?><option value="<?= (int)$a['id'] ?>" data-type="Asset"><?= htmlspecialchars($a['asset_tag'].' - '.$a['asset_name']) ?> (Asset)</option><?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6"><label class="form-label small">Role / Label</label><input name="role_label" class="form-control" placeholder="e.g. Driver, Coordinator"></div>
                <div class="col-md-3"><label class="form-label small">From</label><input type="date" name="allocated_from" class="form-control"></div>
                <div class="col-md-3"><label class="form-label small">To</label><input type="date" name="allocated_to" class="form-control"></div>
                <div class="col-12"><label class="form-label small">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                <div class="col-12"><button type="submit" class="btn btn-primary w-100" name="add_resource"><i class="bi bi-person-plus"></i> Allocate</button></div>
              </form>
              <h6 class="mb-2">Planned Resource Assignments</h6>
              <div class="table-card p-0 mb-0" style="overflow:hidden;">
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Resource</th><th>Period</th><th class="text-end">Action</th></tr></thead>
                    <tbody><tr><td colspan="3" class="text-muted text-center py-3">Save allocation to view project table.</td></tr></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div id="editProjectForm" style="display:none;">
        <form method="POST" action="../../controllers/ProjectController.php" class="row g-3">
          <input type="hidden" name="id" id="edit_project_id">
          <div class="col-md-6"><label class="form-label">Project Name</label><input name="name" id="edit_project_name" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Type</label><select name="type" id="edit_project_type" class="form-select"><option value="General">General</option><option value="Fleet Expansion">Fleet Expansion</option></select></div>
          <div class="col-md-4"><label class="form-label">Start</label><input type="date" name="start_date" id="edit_project_start" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">End</label><input type="date" name="end_date" id="edit_project_end" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Status</label><select name="status" id="edit_project_status" class="form-select"><option>Planned</option><option>Ongoing</option><option>Completed</option><option>On Hold</option></select></div>
          <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="edit_project_description" class="form-control" rows="3"></textarea></div>
          <div class="col-12 d-flex gap-2"><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button><button class="btn btn-primary flex-grow-1" name="update"><i class="bi bi-save"></i> Save Changes</button></div>
        </form>
      </div>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($activeTab === 'scheduling'): ?>
      <div class="table-card">
        <h5 class="mb-3">Scheduling</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Task</th><th>Project</th><th>Priority</th><th>Status</th><th>Start</th><th>Due</th></tr></thead>
            <tbody>
              <?php if (empty($tasksAll)): ?><tr><td colspan="6" class="text-muted text-center py-4">No tasks available</td></tr><?php endif; ?>
              <?php foreach ($tasksAll as $t): ?>
                <tr>
                  <td><?= htmlspecialchars($t['title'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($t['project_name'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($t['priority'] ?? '-') ?></td>
                  <td><span class="badge bg-secondary"><?= htmlspecialchars($t['status'] ?? '-') ?></span></td>
                  <td class="text-muted small"><?= htmlspecialchars($t['start_date'] ?? '-') ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($t['due_date'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'fleet-expansion'): ?>
      <div class="module-kpi-grid mb-4">
        <div class="module-kpi-card"><div class="module-kpi-label">Current Fleet Units</div><div class="module-kpi-value"><?= $fleetTotal ?></div><div class="module-kpi-detail"><?= (int)($fleetCounts['Available'] ?? 0) ?> available</div><div class="module-kpi-icon"><i class="fas fa-truck"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Expansion Target</div><div class="module-kpi-value"><?= $totalExpansionTarget ?></div><div class="module-kpi-detail">planned additional units</div><div class="module-kpi-icon"><i class="fas fa-bullseye"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Allocated to Expansion</div><div class="module-kpi-value"><?= $totalExpansionAllocated ?></div><div class="module-kpi-detail">fleet units assigned</div><div class="module-kpi-icon"><i class="fas fa-link"></i></div></div>
        <div class="module-kpi-card"><div class="module-kpi-label">Expansion Gap</div><div class="module-kpi-value"><?= $totalExpansionGap ?></div><div class="module-kpi-detail">units still needed</div><div class="module-kpi-icon"><i class="fas fa-exclamation-triangle"></i></div></div>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Fleet Expansion Program</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Project</th><th>Vehicle Plan</th><th>Target Units</th><th>Allocated</th><th>Gap</th><th>Progress</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              <?php if (empty($fleetExpansionMeta)): ?><tr><td colspan="8" class="text-muted text-center py-4">No fleet expansion projects found</td></tr><?php endif; ?>
              <?php foreach ($fleetExpansionMeta as $row): ?>
                <?php $p = $row['project']; ?>
                <tr>
                  <td><div class="fw-semibold"><?= htmlspecialchars($p['name'] ?? '-') ?></div><div class="text-muted small"><?= htmlspecialchars($p['start_date'] ?? '-') ?> -> <?= htmlspecialchars($p['end_date'] ?? '-') ?></div></td>
                  <td><?= htmlspecialchars($row['vehicle_plan'] !== '' ? $row['vehicle_plan'] : '-') ?></td>
                  <td><?= (int)$row['target_units'] ?></td>
                  <td><?= (int)$row['allocated_units'] ?></td>
                  <td><?= (int)$row['gap_units'] ?></td>
                  <td><span class="badge bg-secondary"><?= (int)$row['progress_pct'] ?>%</span></td>
                  <td><span class="badge bg-secondary"><?= htmlspecialchars($p['status'] ?? '-') ?></span></td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary project-open-trigger"
                    data-modal-form="viewProjectForm" data-modal-title="Project Overview"
                    data-open-url="projects.php?tab=planning&project_id=<?= (int)$p['id'] ?>#project-focus"
                    data-id="<?= (int)$p['id'] ?>"
                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                    data-type="<?= htmlspecialchars($p['type'], ENT_QUOTES) ?>"
                    data-description="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>"
                    data-start="<?= htmlspecialchars($p['start_date'] ?? '', ENT_QUOTES) ?>"
                    data-end="<?= htmlspecialchars($p['end_date'] ?? '', ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($p['status'] ?? 'Planned', ENT_QUOTES) ?>">
                    <i class="bi bi-kanban"></i> Open
                  </button>
                    <?php if ($canEdit): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary project-edit-trigger"
                      data-modal-form="editProjectForm" data-modal-title="Edit Project"
                      data-id="<?= (int)$p['id'] ?>"
                      data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                      data-type="<?= htmlspecialchars($p['type'], ENT_QUOTES) ?>"
                      data-description="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>"
                      data-start="<?= htmlspecialchars($p['start_date'] ?? '', ENT_QUOTES) ?>"
                      data-end="<?= htmlspecialchars($p['end_date'] ?? '', ENT_QUOTES) ?>"
                      data-status="<?= htmlspecialchars($p['status'] ?? 'Planned', ENT_QUOTES) ?>">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($canAdd): ?>
      <div id="addFleetExpansionForm" style="display:none;">
        <form method="POST" action="../../controllers/ProjectController.php" id="fleetExpansionForm" class="row g-3">
          <input type="hidden" name="add" value="1">
          <input type="hidden" name="type" value="Fleet Expansion">
          <input type="hidden" name="description" id="fleet_expansion_description">
          <div class="col-md-6"><label class="form-label">Project Name</label><input class="form-control" name="name" required></div>
          <div class="col-md-6"><label class="form-label">Vehicle Plan</label><input class="form-control" id="fleet_vehicle_plan" placeholder="e.g. 10 Light Trucks + 5 Vans" required></div>
          <div class="col-md-4"><label class="form-label">Target Units</label><input type="number" min="1" class="form-control" id="fleet_target_units" required></div>
          <div class="col-md-4"><label class="form-label">Start</label><input type="date" name="start_date" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">End</label><input type="date" name="end_date" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option>Planned</option><option>Ongoing</option><option>Completed</option><option>On Hold</option></select></div>
          <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" id="fleet_notes" rows="3" placeholder="Funding source, phases, expected delivery, risks..."></textarea></div>
          <div class="col-12 d-flex gap-2"><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button><button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-plus-circle"></i> Create Expansion</button></div>
        </form>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($activeTab === 'timeline'): ?>
      <div class="table-card">
        <h5 class="mb-3">Timeline Management</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Project</th><th>Type</th><th>Start</th><th>End</th><th>Progress</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($timelineRows)): ?><tr><td colspan="6" class="text-muted text-center py-4">No project timeline data</td></tr><?php endif; ?>
              <?php foreach ($timelineRows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['name'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($r['type'] ?? '-') ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($r['start_date'] ?? '-') ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($r['end_date'] ?? '-') ?></td>
                  <td class="text-muted small"><?= (int)($r['done_tasks'] ?? 0) ?> / <?= (int)($r['total_tasks'] ?? 0) ?> tasks done</td>
                  <td><span class="badge bg-secondary"><?= htmlspecialchars($r['status'] ?? '-') ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'resources'): ?>
      <div class="table-card">
        <h5 class="mb-3">Resource Allocation</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light"><tr><th>Project</th><th>Resource</th><th>Type</th><th>Role</th><th>Period</th></tr></thead>
            <tbody>
              <?php if (empty($resourcesAll)): ?><tr><td colspan="5" class="text-muted text-center py-4">No resources allocated</td></tr><?php endif; ?>
              <?php foreach ($resourcesAll as $r): ?>
                <?php
                  $resourceName = '-';
                  if (($r['resource_type'] ?? '') === 'User') $resourceName = $r['user_name'] ?? '-';
                  elseif (($r['resource_type'] ?? '') === 'Fleet') $resourceName = trim((string)(($r['vehicle_name'] ?? '-') . ' ' . ($r['plate_number'] ?? '')));
                  elseif (($r['resource_type'] ?? '') === 'Asset') $resourceName = trim((string)(($r['asset_tag'] ?? '-') . ' ' . ($r['asset_name'] ?? '')));
                ?>
                <tr>
                  <td><?= htmlspecialchars($r['project_name'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($resourceName) ?></td>
                  <td><?= htmlspecialchars($r['resource_type'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($r['role_label'] ?? '-') ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($r['allocated_from'] ?? '-') ?> -> <?= htmlspecialchars($r['allocated_to'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php if ($canEdit || $canAdd): ?>
<script>
(function () {
  document.addEventListener('click', function (event) {
    const btn = event.target.closest('.project-open-trigger');
    if (!btn) return;
    event.preventDefault();
    setTimeout(function () {
      const root = document.getElementById('modalBody');
      if (!root) return;
      const name = root.querySelector('#view_project_name');
      const type = root.querySelector('#view_project_type');
      const desc = root.querySelector('#view_project_description');
      const start = root.querySelector('#view_project_start');
      const end = root.querySelector('#view_project_end');
      const status = root.querySelector('#view_project_status');
      const openLink = root.querySelector('#view_project_open_link');
      if (name) name.value = btn.getAttribute('data-name') || '';
      if (type) type.value = btn.getAttribute('data-type') || '';
      if (desc) desc.value = btn.getAttribute('data-description') || '';
      if (start) start.value = btn.getAttribute('data-start') || '';
      if (end) end.value = btn.getAttribute('data-end') || '';
      if (status) status.value = btn.getAttribute('data-status') || '';
      if (openLink) {
        openLink.setAttribute('href', '#');
        openLink.setAttribute('data-project-id', btn.getAttribute('data-id') || '');
        openLink.setAttribute('data-planning-section', 'work-breakdown');
      }
    }, 0);
  });

  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.project-board-switch-trigger');
    if (!trigger) return;

    setTimeout(function () {
      const root = document.getElementById('modalBody');
      if (!root) return;

      const previewContent = root.querySelector('#planning_board_preview_content');
      const workTpl = root.querySelector('#planning_preview_work_breakdown_template');
      const capacityTpl = root.querySelector('#planning_preview_capacity_template');

      const previewMap = {
        'work-breakdown': workTpl ? workTpl.innerHTML : '',
        'capacity-planning': capacityTpl ? capacityTpl.innerHTML : ''
      };

      function setActiveButton(activeTab) {
        const switchBtns = root.querySelectorAll('.planning-switch-btn');
        switchBtns.forEach(function (btn) {
          const isActive = btn.getAttribute('data-target') === activeTab;
          btn.classList.toggle('active', isActive);
        });
      }

      function bindResourceFilter() {
        const typeSel = root.querySelector('.planning-resource-type');
        const resSel = root.querySelector('.planning-resource-select');
        if (!typeSel || !resSel) return;
        const sync = function () {
          const t = typeSel.value;
          const opts = Array.from(resSel.options);
          let first = null;
          opts.forEach(function (o) {
            const ok = o.getAttribute('data-type') === t;
            o.hidden = !ok;
            if (ok && !first) first = o;
          });
          if (first) resSel.value = first.value;
        };
        typeSel.onchange = sync;
        sync();
      }

      function updateView(tab) {
        const target = tab || 'work-breakdown';
        if (previewContent) {
          previewContent.innerHTML = previewMap[target] || previewMap['work-breakdown'] || '';
        }
        const projectId = trigger.getAttribute('data-project-id') || '';
        root.querySelectorAll('.planning-project-id').forEach(function (el) { el.value = projectId; });
        bindResourceFilter();
        setActiveButton(target);
      }

      root._planningUpdateView = updateView;

      if (!root.dataset.planningSwitchBound) {
        root.addEventListener('click', function (e) {
          const btn = e.target.closest('.planning-switch-btn');
          if (!btn) return;
          const target = btn.getAttribute('data-target') || 'work-breakdown';
          if (typeof root._planningUpdateView === 'function') {
            root._planningUpdateView(target);
          }
        });
        root.dataset.planningSwitchBound = '1';
      }

      const requested = trigger.getAttribute('data-planning-section') || 'work-breakdown';
      updateView(requested);
    }, 0);
  });

  document.addEventListener('click', function (event) {
    const btn = event.target.closest('.project-edit-trigger');
    if (!btn) return;
    event.preventDefault();
    setTimeout(function () {
      const root = document.getElementById('modalBody');
      if (!root) return;
      const id = root.querySelector('#edit_project_id');
      const name = root.querySelector('#edit_project_name');
      const type = root.querySelector('#edit_project_type');
      const desc = root.querySelector('#edit_project_description');
      const start = root.querySelector('#edit_project_start');
      const end = root.querySelector('#edit_project_end');
      const status = root.querySelector('#edit_project_status');
      if (id) id.value = btn.getAttribute('data-id') || '';
      if (name) name.value = btn.getAttribute('data-name') || '';
      if (type) type.value = btn.getAttribute('data-type') || 'General';
      if (desc) desc.value = btn.getAttribute('data-description') || '';
      if (start) start.value = btn.getAttribute('data-start') || '';
      if (end) end.value = btn.getAttribute('data-end') || '';
      if (status) status.value = btn.getAttribute('data-status') || 'Planned';
    }, 0);
  });

  const typeSel = document.getElementById('rtype');
  const resSel = document.getElementById('resourceSelect');
  function filterResourceOptions() {
    if (!typeSel || !resSel) return;
    const type = typeSel.value;
    const options = Array.from(resSel.options);
    let firstVisible = null;
    options.forEach(function (o) {
      const ok = o.getAttribute('data-type') === type;
      o.hidden = !ok;
      if (ok && !firstVisible) firstVisible = o;
    });
    if (firstVisible) resSel.value = firstVisible.value;
  }
  if (typeSel && resSel) {
    typeSel.addEventListener('change', filterResourceOptions);
    filterResourceOptions();
  }

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('open_modal') === 'planningBoardSwitchForm') {
    const projectIdFromUrl = urlParams.get('project_id') || '';
    const sectionFromUrl = urlParams.get('planning_section') || 'work-breakdown';
    const launchBtn = document.getElementById('view_project_open_link');
    if (launchBtn) {
      launchBtn.setAttribute('data-project-id', projectIdFromUrl);
      launchBtn.setAttribute('data-planning-section', sectionFromUrl);
    }

    window.setTimeout(function () {
      const root = document.getElementById('modalBody');
      if (!root || typeof root._planningUpdateView !== 'function') return;
      root.querySelectorAll('.planning-project-id').forEach(function (el) { el.value = projectIdFromUrl; });
      root._planningUpdateView(sectionFromUrl);
    }, 120);
  }

  document.addEventListener('submit', function (event) {
    const fleetForm = event.target.closest('#fleetExpansionForm');
    if (!fleetForm) return;
    const plan = (fleetForm.querySelector('#fleet_vehicle_plan')?.value || '').trim();
    const target = parseInt((fleetForm.querySelector('#fleet_target_units')?.value || '0'), 10);
    const notes = (fleetForm.querySelector('#fleet_notes')?.value || '').trim();
    const lines = [
      'Fleet Expansion Program',
      'Vehicle Plan: ' + plan,
      'Target Units: ' + (Number.isFinite(target) && target > 0 ? target : 0)
    ];
    if (notes !== '') lines.push('Notes: ' + notes);
    const out = fleetForm.querySelector('#fleet_expansion_description');
    if (out) out.value = lines.join("\n");
  });
})();
</script>

<?php endif; ?>

<?php require_once __DIR__ . "/../layout/footer.php"; ?>









































