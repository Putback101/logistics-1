<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/app.php";
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
$recentActivities = $dash->getRecentActivities(5, $userRole);
$pendingTasks = $dash->getPendingTasks($userRole);
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
          <a href="<?= $base ?>/views/procurement/procurement.php?tab=purchase-orders&open_modal=addProcurementRequestForm&open_modal_title=New%20Request" class="quick-action-btn text-decoration-none">
            <i class="fas fa-plus-circle"></i>
            <span>Procurement</span>
          </a>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'project_staff'], true)): ?>
          <a href="<?= $base ?>/views/project/projects.php?tab=planning&open_modal=addProjectForm&open_modal_title=Create%20Project" class="quick-action-btn text-decoration-none">
            <i class="fas fa-folder-plus"></i>
            <span>Projects</span>
          </a>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'asset'], true)): ?>
          <a href="<?= $base ?>/views/asset/asset.php?tab=registry&open_modal=addAssetForm&open_modal_title=Add%20Asset" class="quick-action-btn text-decoration-none">
            <i class="fas fa-boxes"></i>
            <span>Assets</span>
          </a>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'mro_staff'], true)): ?>
          <a href="<?= $base ?>/views/mro/maintenance.php?tab=maintenance&open_modal=addMaintenanceForm&open_modal_title=Log%20Maintenance%20%2F%20Repair" class="quick-action-btn text-decoration-none">
            <i class="fas fa-tools"></i>
            <span>MRO</span>
          </a>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'warehouse_staff'], true)): ?>
          <a href="<?= $base ?>/views/warehousing/inventory.php?tab=inventory&open_modal=addReceivingForm&open_modal_title=Record%20Receiving" class="quick-action-btn text-decoration-none">
            <i class="fas fa-warehouse"></i>
            <span>Warehousing</span>
          </a>
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

<?php require_once __DIR__ . "/layout/footer.php"; ?>





















