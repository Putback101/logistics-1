<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../config/permissions.php";
require_once __DIR__ . "/../models/Dashboard.php";
require_once __DIR__ . "/../models/DashboardCharts.php";

requireLogin();
$base = app_base_url();

$dash = new Dashboard($pdo);
$charts = new DashboardCharts($pdo);
$userRole = $_SESSION['user']['role'] ?? '';
$userName = $_SESSION['user']['fullname'] ?? 'User';

$fleetStats = $dash->getFleetStats();
$procStats = $dash->getProcurementStats();
$poStats = $dash->getPurchaseOrderStats();
$invStats = $dash->getInventoryStats();
$projectStats = $dash->getProjectStats();
$maintenanceStats = $dash->getMaintenanceStats();
$recentActivities = $dash->getRecentActivities(5, $userRole, (int)($_SESSION['user']['id'] ?? 0));
$pendingTasks = $dash->getPendingTasks($userRole);
$canProcAdd = hasPermission($userRole, 'procurement', 'add');
$canProjectAdd = hasPermission($userRole, 'projects', 'add');
$canAssetAdd = hasPermission($userRole, 'assets', 'add');
$canMroAdd = hasPermission($userRole, 'mro', 'add');
$canWarehouseEdit = hasPermission($userRole, 'warehousing', 'edit');

$budgetsQuick = $canProcAdd
    ? $pdo->query("SELECT year FROM budgets ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$suppliersQuick = $canProcAdd
    ? $pdo->query("SELECT name FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$itemsMasterQuick = $canProcAdd
    ? $pdo->query("SELECT id, item_name, unit FROM items ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$usersQuick = $canAssetAdd
    ? $pdo->query("SELECT id, fullname, role FROM users ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$assetCategoryOptionsQuick = ['Vehicle', 'Tool', 'IT Equipment', 'Heavy Equipment', 'Spare Parts', 'Office Equipment', 'Warehouse Equipment'];
$assetCategoryRowsQuick = $canAssetAdd
    ? $pdo->query("SELECT DISTINCT asset_category FROM assets WHERE asset_category IS NOT NULL AND asset_category <> '' ORDER BY asset_category ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
foreach ($assetCategoryRowsQuick as $catRow) {
    $cat = trim((string)($catRow['asset_category'] ?? ''));
    if ($cat !== '' && !in_array($cat, $assetCategoryOptionsQuick, true)) {
        $assetCategoryOptionsQuick[] = $cat;
    }
}
$fleetQuick = $canMroAdd
    ? $pdo->query("SELECT id, vehicle_name, plate_number FROM fleet ORDER BY vehicle_name ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$assetsQuick = ($canMroAdd || $canWarehouseEdit)
    ? $pdo->query("SELECT id, asset_tag, asset_name FROM assets ORDER BY asset_name ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$receivablePosQuick = $canWarehouseEdit
    ? $pdo->query("SELECT id, po_number FROM purchase_orders WHERE status IN ('Sent') ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
if (!function_exists('time_ago_label')) {
    function time_ago_label($datetime)
    {
        $ts = strtotime((string)$datetime);
        if (!$ts) {
            return '-';
        }

        $diff = time() - $ts;
        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff < 2) {
            return 'just now';
        }

        if ($diff < 60) {
            $v = (int)$diff;
            return $v . ' second' . ($v !== 1 ? 's' : '') . ' ago';
        }

        if ($diff < 3600) {
            $v = (int)floor($diff / 60);
            return $v . ' minute' . ($v !== 1 ? 's' : '') . ' ago';
        }

        if ($diff < 86400) {
            $v = (int)floor($diff / 3600);
            return $v . ' hour' . ($v !== 1 ? 's' : '') . ' ago';
        }

        if ($diff < 604800) {
            $v = (int)floor($diff / 86400);
            return $v . ' day' . ($v !== 1 ? 's' : '') . ' ago';
        }

        return date('M d, Y', $ts);
    }
}

if (!function_exists('pending_task_url')) {
    function pending_task_url($taskRow, $base)
    {
        $task = (string)($taskRow['task'] ?? '');
        $id = (int)($taskRow['ref_id'] ?? 0);

        switch ($task) {
            case 'Approve Request':
            case 'Review Procurement':
                return $id > 0
                    ? $base . '/views/procurement/procurement.php?tab=purchase-orders&edit_request_id=' . $id
                    : $base . '/views/procurement/procurement.php?tab=approvals';
            case 'Receive Stock':
                return $id > 0
                    ? $base . '/views/warehousing/inventory.php?po_id=' . $id
                    : $base . '/views/warehousing/inventory.php';
            case 'Start Task':
                return $id > 0
                    ? $base . '/views/project/projects.php?tab=scheduling&task_id=' . $id
                    : $base . '/views/project/projects.php?tab=scheduling';
            case 'Perform Maintenance':
                return $id > 0
                    ? $base . '/views/mro/maintenance.php?tab=maintenance&log_id=' . $id
                    : $base . '/views/mro/maintenance.php?tab=maintenance';
            default:
                return '#';
        }
    }
}
$kpiCards = [];
switch ($userRole) {
    case 'admin':
    case 'manager':
        $projectOngoing = (int)($projectStats['Ongoing'] ?? 0);
        $projectPlanned = (int)($projectStats['Planned'] ?? 0);
        $kpiCards = [
            [
                'icon' => 'fas fa-dolly-flatbed',
                'label' => 'Procurement',
                'value' => (int)($procStats['Pending'] ?? 0),
                'detail' => 'pending requests',
                'href' => $base . '/views/procurement/procurement.php',
            ],
            [
                'icon' => 'fas fa-project-diagram',
                'label' => 'Project Management',
                'value' => $projectOngoing,
                'detail' => $projectPlanned . ' planned projects',
                'href' => $base . '/views/project/projects.php',
            ],
            [
                'icon' => 'fas fa-boxes',
                'label' => 'Asset Management',
                'value' => (int)$dash->countAssets(),
                'detail' => 'registered assets',
                'href' => $base . '/views/asset/asset.php?tab=registry',
            ],
            [
                'icon' => 'fas fa-tools',
                'label' => 'MRO',
                'value' => (int)($maintenanceStats['total'] ?? 0),
                'detail' => 'maintenance logs (30d)',
                'href' => $base . '/views/mro/maintenance.php',
            ],
            [
                'icon' => 'fas fa-warehouse',
                'label' => 'Warehousing',
                'value' => (int)($poStats['Sent'] ?? 0),
                'detail' => 'POs waiting receipt',
                'href' => $base . '/views/warehousing/inventory.php',
            ],
        ];
        break;
    case 'procurement_staff':
        $kpiCards = [
            ['icon' => 'fas fa-hourglass-half', 'label' => 'Pending', 'value' => $procStats['Pending'] ?? 0, 'detail' => 'requests to review'],
            ['icon' => 'fas fa-check-circle', 'label' => 'Approved', 'value' => $procStats['Approved'] ?? 0, 'detail' => 'in progress'],
            ['icon' => 'fas fa-shopping-bag', 'label' => 'Purchase Orders', 'value' => $poStats['Approved'] ?? 0, 'detail' => 'awaiting send'],
        ];
        break;
    case 'warehouse_staff':
        $kpiCards = [
            ['icon' => 'fas fa-box-open', 'label' => 'Total Stock', 'value' => $invStats['total'] ?? 0, 'detail' => 'inventory items'],
            ['icon' => 'fas fa-exclamation-triangle', 'label' => 'Low Stock', 'value' => $invStats['low_stock'] ?? 0, 'detail' => 'below minimum'],
            ['icon' => 'fas fa-truck-loading', 'label' => 'Receiving', 'value' => $poStats['Sent'] ?? 0, 'detail' => 'items to receive'],
        ];
        break;
    case 'project_staff':
        $kpiCards = [
            ['icon' => 'fas fa-project-diagram', 'label' => 'Active Projects', 'value' => $projectStats['Ongoing'] ?? 0, 'detail' => 'ongoing'],
            ['icon' => 'fas fa-tasks', 'label' => 'Tasks', 'value' => count($pendingTasks), 'detail' => 'pending tasks'],
            ['icon' => 'fas fa-check-square', 'label' => 'Completed', 'value' => $projectStats['Completed'] ?? 0, 'detail' => 'this month'],
        ];
        break;
    case 'mro_staff':
        $kpiCards = [
            ['icon' => 'fas fa-wrench', 'label' => 'Maintenance', 'value' => $maintenanceStats['total'] ?? 0, 'detail' => 'last 30 days'],
            ['icon' => 'fas fa-truck', 'label' => 'In Maintenance', 'value' => $fleetStats['Maintenance'] ?? 0, 'detail' => 'vehicles'],
            ['icon' => 'fas fa-check-circle', 'label' => 'Available', 'value' => $fleetStats['Available'] ?? 0, 'detail' => 'ready to use'],
        ];
        break;
    default:
        $kpiCards = [
            ['icon' => 'fas fa-truck', 'label' => 'Fleet', 'value' => $dash->countFleet(), 'detail' => 'total vehicles'],
            ['icon' => 'fas fa-shopping-bag', 'label' => 'Procurement', 'value' => $dash->countProcurement(), 'detail' => 'requests'],
            ['icon' => 'fas fa-box-open', 'label' => 'Inventory', 'value' => $dash->countInventory(), 'detail' => 'stock items'],
        ];
}
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>
<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>
<main class="main-content dashboard-page">
  <div class="content-area dashboard-overview">
    <div class="module-header">
      <div>
        <h1>Welcome back, <?= htmlspecialchars($userName) ?>!</h1>
        <p>Here's what's happening with your logistics operations today.</p>
      </div>
    </div>

    <div class="dashboard-grid">
      <?php foreach ($kpiCards as $card): ?>
      <?php $cardHref = $card['href'] ?? null; ?>
      <<?= $cardHref ? 'a' : 'div' ?> class="stat-card text-decoration-none text-reset"<?= $cardHref ? ' href="' . htmlspecialchars($cardHref) . '"' : '' ?>>
        <div class="card-header">
          <i class="<?= htmlspecialchars($card['icon']) ?> card-icon"></i>
          <span class="card-title"><?= htmlspecialchars($card['label']) ?></span>
        </div>
        <div class="card-value"><?= htmlspecialchars((string)$card['value']) ?></div>
        <div class="card-footer"><?= htmlspecialchars($card['detail']) ?></div>
      </<?= $cardHref ? 'a' : 'div' ?>>
      <?php endforeach; ?>
    </div>

    <div class="content-grid-2 mt-4">
      <div class="card">
        <div class="section-header">
          <h3><i class="fas fa-history"></i> Recent Activity</h3>
        </div>
        <?php if (count($recentActivities) > 0): ?>
          <ul class="activity-list">
            <?php foreach ($recentActivities as $activity): ?>
            <li>
              <span class="activity-dot"></span>
              <span><?= htmlspecialchars($activity['description'] ?? 'Activity logged') ?></span>
<?php $activityTs = strtotime((string)($activity['created_at'] ?? '')); ?>
              <small class="ms-auto text-muted js-time-ago" data-ts="<?= (int)$activityTs ?>" title="<?= $activityTs ? date('M d, Y h:i A', $activityTs) : '-' ?>"><?= time_ago_label($activity['created_at']) ?></small>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="empty-state">No recent activities</div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="section-header">
          <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="quick-actions">
          <?php if (in_array($userRole, ['admin', 'procurement_staff'], true)): ?>
          <button type="button" class="quick-action-btn text-decoration-none" data-modal-form="addProcurementRequestForm" data-modal-title="New Request">
            <i class="fas fa-plus-circle"></i>
            <span>Procurement</span>
          </button>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'project_staff'], true)): ?>
          <button type="button" class="quick-action-btn text-decoration-none" data-modal-form="addProjectForm" data-modal-title="Create Project">
            <i class="fas fa-folder-plus"></i>
            <span>Projects</span>
          </button>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'asset'], true)): ?>
          <button type="button" class="quick-action-btn text-decoration-none" data-modal-form="addAssetForm" data-modal-title="Add Asset">
            <i class="fas fa-boxes"></i>
            <span>Assets</span>
          </button>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'mro_staff'], true)): ?>
          <button type="button" class="quick-action-btn text-decoration-none" data-modal-form="addMaintenanceForm" data-modal-title="Log Maintenance / Repair">
            <i class="fas fa-tools"></i>
            <span>MRO</span>
          </button>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'warehouse_staff'], true)): ?>
          <button type="button" class="quick-action-btn text-decoration-none" data-modal-form="addReceivingForm" data-modal-title="Record Receiving">
            <i class="fas fa-warehouse"></i>
            <span>Warehousing</span>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card mt-4">
      <div class="section-header">
        <h3><i class="fas fa-tasks"></i> Pending Tasks</h3>
      </div>
      <?php if (count($pendingTasks) > 0): ?>
        <ul class="pending-tasks-list">
          <?php foreach ($pendingTasks as $idx => $task):
            $priorities = ['high', 'high', 'medium', 'medium', 'low'];
            $priority = $priorities[$idx % count($priorities)];
          ?>
          <?php $taskKey = sha1(($task['task'] ?? '') . '|' . ($task['due_date'] ?? '') . '|' . $idx); ?>
          <?php $taskUrl = pending_task_url($task['task'] ?? '', $userRole, $base); ?>
          <li class="pending-task-item" data-task-key="<?= htmlspecialchars($taskKey) ?>" data-task-url="<?= htmlspecialchars($taskUrl) ?>">
            <button type="button" class="task-checkbox" aria-label="Mark task complete" aria-pressed="false"></button>
            <div class="task-content">
              <div class="task-title"><?= htmlspecialchars($task['task']) ?></div>
              <div class="task-meta">Due: <?= date('M d, Y', strtotime($task['due_date'] ?? date('Y-m-d'))) ?></div>
            </div>
            <span class="task-priority <?= $priority ?>"><?= strtoupper($priority) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state">No pending tasks</div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php if ($canProcAdd): ?>
<div id="addProcurementRequestForm" style="display:none;">
  <form method="POST" action="<?= $base ?>/controllers/ProcurementController.php" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Budget Year</label>
      <select name="budget_year" class="form-select" required>
        <option value="">-- Select Year --</option>
        <?php foreach($budgetsQuick as $b): ?>
          <option value="<?= (int)$b['year'] ?>"><?= (int)$b['year'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label">Request Lines</label>
      <div class="procurement-lines-body d-grid gap-3">
        <div class="procurement-line-card" data-line-index="0">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 text-light">Line 1</h6>
            <button type="button" class="btn btn-sm btn-outline-danger request-line-remove" disabled><i class="bi bi-trash"></i></button>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Item</label>
              <select name="items[0][item_id]" class="form-select item-master-select">
                <option value="">-- Select Item --</option>
                <?php foreach($itemsMasterQuick as $im): ?>
                  <option value="<?= (int)$im['id'] ?>" data-name="<?= htmlspecialchars($im['item_name'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars($im['item_name']) ?><?= !empty($im['unit']) ? ' (' . htmlspecialchars($im['unit']) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="items[0][item_name]" class="form-control mt-2 item-master-name" placeholder="or type item name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Supplier</label>
              <select name="items[0][supplier]" class="form-select" required>
                <option value="">-- Select Supplier --</option>
                <?php foreach($suppliersQuick as $s): ?>
                  <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Quantity</label><input type="number" min="1" name="items[0][quantity]" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Est. Amount</label><input type="number" step="0.01" min="0" name="items[0][estimated_amount]" class="form-control" value="0"></div>
          </div>
        </div>
      </div>
      <div class="mt-3"><button type="button" class="btn btn-sm btn-outline-secondary request-line-add"><i class="bi bi-plus-circle"></i> Add Line</button></div>
    </div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-primary flex-grow-1" name="add"><i class="bi bi-plus-circle"></i> Request</button><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($canProjectAdd): ?>
<div id="addProjectForm" style="display:none;">
  <form method="POST" action="<?= $base ?>/controllers/ProjectController.php" class="row g-3">
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

<?php if ($canAssetAdd): ?>
<div id="addAssetForm" style="display:none;">
  <form method="POST" action="<?= $base ?>/controllers/AssetController.php" class="row g-3">
    <div class="col-md-4"><label class="form-label">Asset Tag *</label><input class="form-control" name="asset_tag" required></div>
    <div class="col-md-8"><label class="form-label">Asset Name *</label><input class="form-control" name="asset_name" required></div>
    <div class="col-md-6">
      <label class="form-label">Category *</label>
      <select class="form-select" name="asset_category" required>
        <option value="">- Select Category -</option>
        <?php foreach ($assetCategoryOptionsQuick as $catOpt): ?>
          <option value="<?= htmlspecialchars($catOpt) ?>"><?= htmlspecialchars($catOpt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><option>Active</option><option>In Use</option><option>Idle</option><option>Under Maintenance</option><option>Retired</option></select></div>
    <div class="col-md-4"><label class="form-label">Brand</label><input class="form-control" name="brand"></div>
    <div class="col-md-4"><label class="form-label">Model</label><input class="form-control" name="model"></div>
    <div class="col-md-4"><label class="form-label">Serial No</label><input class="form-control" name="serial_no"></div>
    <div class="col-md-4"><label class="form-label">Acquisition Date</label><input type="date" class="form-control" name="acquisition_date"></div>
    <div class="col-md-4"><label class="form-label">Purchase Cost</label><input type="number" step="0.01" class="form-control" name="purchase_cost" value="0"></div>
    <div class="col-md-4"><label class="form-label">Location</label><input class="form-control" name="location"></div>
    <div class="col-md-6"><label class="form-label">Assigned To</label><select class="form-select" name="assigned_to"><option value="">- None -</option><?php foreach ($usersQuick as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['role']) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-primary flex-grow-1" name="add_asset">Add Asset</button><button class="btn btn-secondary" type="button" onclick="closeModal()">Cancel</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($canMroAdd): ?>
<div id="addMaintenanceForm" style="display:none;">
  <form method="POST" action="<?= $base ?>/controllers/MaintenanceController.php" class="row g-3" id="mroForm">
    <div class="col-md-6"><label class="form-label">Fleet (optional)</label><select name="fleet_id" class="form-select" id="fleetSelect"><option value="">- Select Fleet -</option><?php foreach($fleetQuick as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['vehicle_name'].' ('.$f['plate_number'].')') ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Asset (optional)</label><select class="form-select" name="asset_id" id="assetSelect"><option value="">- Select Asset -</option><?php foreach ($assetsQuick as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['asset_tag']) ?> - <?= htmlspecialchars($a['asset_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Type</label><select name="type" class="form-select"><option value="Maintenance">Maintenance</option><option value="Repair">Repair</option></select></div>
    <div class="col-md-3"><label class="form-label">Cost</label><input type="number" step="0.01" name="cost" class="form-control" min="0" value="0"></div>
    <div class="col-md-3"><label class="form-label">Date Performed</label><input type="date" name="performed_at" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Status</label><input class="form-control" value="Auto (based on Date)" disabled></div>
    <div class="col-12"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="2" required></textarea></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-primary flex-grow-1" name="add"><i class="bi bi-wrench-adjustable"></i> Save Log</button><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($canWarehouseEdit): ?>
<div id="addReceivingForm" style="display:none;">
  <form method="POST" action="<?= $base ?>/controllers/ReceivingController.php" class="row g-3 receiving-modal-form" data-po-items-url="<?= $base ?>/controllers/get_po_items.php">
    <div class="col-md-4"><label class="form-label">Purchase Order Reference</label><select name="po_id" class="form-select receiving-po-select" required><option value="">Select</option><?php foreach($receivablePosQuick as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['po_number']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Quality Check</label><select name="qc_status" class="form-select receiving-qc-status" required><option value="PASS">PASS</option><option value="FAIL">FAIL</option></select></div>
    <div class="col-md-5"><label class="form-label">QC Notes (required if FAIL)</label><input name="qc_notes" class="form-control receiving-qc-notes" placeholder="Damaged, wrong item, missing parts, etc."></div>
    <div class="col-12"><table class="table table-bordered align-middle receiving-po-items-table"><thead class="table-light"><tr><th style="width: 45%;">Item</th><th style="width: 15%;">PO Qty</th><th style="width: 15%;">Remaining</th><th style="width: 25%;">Qty Received</th></tr></thead><tbody><tr><td colspan="4" class="text-muted text-center">Select a PO to load items</td></tr></tbody></table></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-primary flex-grow-1 receiving-save-btn" name="receive" disabled><i class="bi bi-box-arrow-in-down"></i> Save Receiving</button><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button></div>
  </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/layout/footer.php"; ?>



























