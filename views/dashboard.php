<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Dashboard.php";
require_once __DIR__ . "/../models/DashboardCharts.php";

requireLogin();

$dash = new Dashboard($pdo);
$charts = new DashboardCharts($pdo);
$userRole = $_SESSION['user']['role'] ?? 'staff';
$userName = $_SESSION['user']['fullname'] ?? 'User';

// Get role-based stats
$fleetStats = $dash->getFleetStats();
$procStats = $dash->getProcurementStats();
$poStats = $dash->getPurchaseOrderStats();
$invStats = $dash->getInventoryStats();
$projectStats = $dash->getProjectStats();
$maintenanceStats = $dash->getMaintenanceStats();
$recentActivities = $dash->getRecentActivities(5, $userRole);
$pendingTasks = $dash->getPendingTasks($userRole);

// Prepare KPI card data based on role
$kpiCards = [];
switch($userRole) {
    case 'admin':
        $kpiCards = [
            ['icon' => 'bi-truck-front', 'label' => 'Total Fleet', 'value' => $dash->countFleet(), 'detail' => 'vehicles managed'],
            ['icon' => 'bi-bag-check', 'label' => 'Procurement', 'value' => $dash->countProcurement(), 'detail' => 'requests'],
            ['icon' => 'bi-box-seam', 'label' => 'Inventory', 'value' => $dash->countInventory(), 'detail' => 'stock items'],
            ['icon' => 'bi-people', 'label' => 'Users', 'value' => $dash->countUsers(), 'detail' => 'team members']
        ];
        break;
    case 'procurement':
        $kpiCards = [
            ['icon' => 'bi-hourglass-split', 'label' => 'Pending', 'value' => $procStats['Pending'] ?? 0, 'detail' => 'requests to review'],
            ['icon' => 'bi-check-circle', 'label' => 'Approved', 'value' => $procStats['Approved'] ?? 0, 'detail' => 'in progress'],
            ['icon' => 'bi-bag-check', 'label' => 'Purchase Orders', 'value' => $poStats['Approved'] ?? 0, 'detail' => 'awaiting send'],
        ];
        break;
    case 'warehouse':
        $kpiCards = [
            ['icon' => 'bi-box-seam', 'label' => 'Total Stock', 'value' => $invStats['total'] ?? 0, 'detail' => 'inventory items'],
            ['icon' => 'bi-exclamation-triangle', 'label' => 'Low Stock', 'value' => $invStats['low_stock'] ?? 0, 'detail' => 'below minimum'],
            ['icon' => 'bi-arrow-down-right', 'label' => 'Receiving', 'value' => $poStats['Sent'] ?? 0, 'detail' => 'items to receive'],
        ];
        break;
    case 'project':
        $kpiCards = [
            ['icon' => 'bi-diagram-3', 'label' => 'Active Projects', 'value' => $projectStats['Active'] ?? 0, 'detail' => 'ongoing'],
            ['icon' => 'bi-list-check', 'label' => 'Tasks', 'value' => $projectStats['Pending'] ?? 0, 'detail' => 'pending tasks'],
            ['icon' => 'bi-check2-square', 'label' => 'Completed', 'value' => $projectStats['Completed'] ?? 0, 'detail' => 'this month'],
        ];
        break;
    case 'mro':
        $kpiCards = [
            ['icon' => 'bi-wrench', 'label' => 'Maintenance', 'value' => $maintenanceStats['total'] ?? 0, 'detail' => 'last 30 days'],
            ['icon' => 'bi-truck-front', 'label' => 'In Maintenance', 'value' => $fleetStats['Maintenance'] ?? 0, 'detail' => 'vehicles'],
            ['icon' => 'bi-check-circle', 'label' => 'Available', 'value' => $fleetStats['Available'] ?? 0, 'detail' => 'ready to use'],
        ];
        break;
    default:
        $kpiCards = [
            ['icon' => 'bi-truck-front', 'label' => 'Fleet', 'value' => $dash->countFleet(), 'detail' => 'total vehicles'],
            ['icon' => 'bi-bag-check', 'label' => 'Procurement', 'value' => $dash->countProcurement(), 'detail' => 'requests'],
            ['icon' => 'bi-box-seam', 'label' => 'Inventory', 'value' => $dash->countInventory(), 'detail' => 'stock items'],
        ];
}
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
<style>
.dashboard-header {
  margin-bottom: 2rem;
}

.welcome-text {
  font-size: 1.75rem;
  font-weight: 600;
  color: #fff;
  margin-bottom: 0.5rem;
}

.welcome-subtext {
  color: #a0aec0;
  font-size: 0.95rem;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.kpi-card {
  background: #1a202c;
  border: 1px solid #2d3748;
  border-radius: 0.75rem;
  padding: 1.5rem;
  transition: all 0.3s ease;
  cursor: pointer;
  position: relative;
  overflow: hidden;
}

.kpi-card:hover {
  border-color: #48bb78;
  box-shadow: 0 0 20px rgba(72, 187, 120, 0.1);
  transform: translateY(-2px);
}

.kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, #48bb78, #38a169);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.kpi-card:hover::before {
  opacity: 1;
}

.kpi-icon {
  font-size: 2rem;
  color: #48bb78;
  margin-bottom: 1rem;
}

.kpi-value {
  font-size: 2.5rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 0.5rem;
  line-height: 1;
}

.kpi-detail {
  color: #a0aec0;
  font-size: 0.85rem;
}

.kpi-label {
  color: #cbd5e0;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.75rem;
  font-weight: 600;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 1200px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}

.section-card {
  background: #1a202c;
  border: 1px solid #2d3748;
  border-radius: 0.75rem;
  padding: 1.5rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #2d3748;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #fff;
  margin: 0;
}

.section-subtitle {
  color: #a0aec0;
  font-size: 0.8rem;
}

.activity-list, .task-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.activity-item, .task-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 0;
  border-bottom: 1px solid #2d3748;
  color: #e2e8f0;
  font-size: 0.95rem;
}

.activity-item:last-child, .task-item:last-child {
  border-bottom: none;
}

.activity-type {
  display: inline-block;
  background: rgba(72, 187, 120, 0.1);
  color: #48bb78;
  padding: 0.25rem 0.75rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  margin-right: 0.75rem;
}

.activity-time {
  color: #a0aec0;
  font-size: 0.85rem;
  white-space: nowrap;
}

.task-priority {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.priority-high {
  background: rgba(245, 101, 101, 0.1);
  color: #f56565;
}

.priority-medium {
  background: rgba(237, 137, 54, 0.1);
  color: #ed8936;
}

.priority-low {
  background: rgba(72, 187, 120, 0.1);
  color: #48bb78;
}

.empty-state {
  text-align: center;
  padding: 2rem 1rem;
  color: #a0aec0;
}

.empty-state-icon {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
  opacity: 0.5;
}
</style>

<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>
<main class="main-content">
  <div class="content-area">
    
    <!-- WELCOME HEADER -->
    <div class="dashboard-header" style="border-left: 5px solid #48bb78; padding-left: 1.5rem; margin-bottom: 2rem;">
      <div class="welcome-text">Welcome back, <?= htmlspecialchars($userName) ?>!</div>
      <div class="welcome-subtext">Here's what's happening with your logistics operations today.</div>
    </div>

    <!-- KPI CARDS (4 column layout) -->
    <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
      <?php foreach ($kpiCards as $card): ?>
      <div class="kpi-card">
        <div class="kpi-label"><?= htmlspecialchars($card['label']) ?></div>
        <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem;">
          <div class="kpi-icon" style="margin-bottom: 0;">
            <i class="bi <?= $card['icon'] ?>"></i>
          </div>
          <div>
            <div class="kpi-value" style="margin-bottom: 0;"><?= $card['value'] ?></div>
            <div class="kpi-detail"><?= htmlspecialchars($card['detail']) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- RECENT ACTIVITY & QUICK ACTIONS -->
    <div class="content-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 2rem;">
      <!-- RECENT ACTIVITY -->
      <div class="section-card">
        <div class="section-header">
          <div>
            <h5 class="section-title">
              <i class="bi bi-clock-history" style="margin-right: 0.5rem; color: #48bb78;"></i>
              Recent Activity
            </h5>
          </div>
        </div>
        <?php if (count($recentActivities) > 0): ?>
          <ul class="activity-list">
            <?php foreach ($recentActivities as $activity): ?>
            <li class="activity-item" style="display: flex; align-items: flex-start; padding: 0.75rem 0; border-bottom: 1px solid #2d3748;">
              <span style="color: #48bb78; margin-right: 0.75rem; margin-top: 2px; font-size: 1.2rem; line-height: 1;">•</span>
              <div style="flex: 1;">
                <div style="font-size: 0.95rem; line-height: 1.4;"><?= htmlspecialchars($activity['description'] ?? 'Activity logged') ?></div>
              </div>
              <div class="activity-time" style="white-space: nowrap; margin-left: 1rem; font-size: 0.85rem; color: #a0aec0;"><?= date('M d', strtotime($activity['created_at'])) ?></div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <div>No recent activities</div>
          </div>
        <?php endif; ?>
      </div>

      <!-- QUICK ACTIONS (2x2 grid) -->
      <div class="section-card">
        <div class="section-header">
          <h5 class="section-title">
            <i class="bi bi-lightning-fill" style="margin-right: 0.5rem; color: #48bb78;"></i>
            Quick Actions
          </h5>
        </div>
        <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem;">
          <?php if (in_array($userRole, ['admin', 'procurement'])): ?>
          <a href="<?= $base ?>/views/procurement.php" class="action-btn" style="padding: 1rem; text-align: left; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 1.75rem; min-width: 2rem; text-align: center;"><i class="bi bi-bag-check" style="color: #48bb78;"></i></div>
            <div style="font-weight: 600; font-size: 0.95rem;">Procurement</div>
          </a>
          <?php endif; ?>
          
          <?php if (in_array($userRole, ['admin', 'project'])): ?>
          <a href="<?= $base ?>/views/projects.php" class="action-btn" style="padding: 1rem; text-align: left; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 1.75rem; min-width: 2rem; text-align: center;"><i class="bi bi-diagram-3" style="color: #48bb78;"></i></div>
            <div style="font-weight: 600; font-size: 0.95rem;">Projects</div>
          </a>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'asset'])): ?>
          <a href="<?= $base ?>/views/fleet.php" class="action-btn" style="padding: 1rem; text-align: left; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 1.75rem; min-width: 2rem; text-align: center;"><i class="bi bi-box-seam" style="color: #48bb78;"></i></div>
            <div style="font-weight: 600; font-size: 0.95rem;">Assets</div>
          </a>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'mro'])): ?>
          <a href="<?= $base ?>/views/maintenance.php" class="action-btn" style="padding: 1rem; text-align: left; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 1.75rem; min-width: 2rem; text-align: center;"><i class="bi bi-wrench" style="color: #48bb78;"></i></div>
            <div style="font-weight: 600; font-size: 0.95rem;">MRO</div>
          </a>
          <?php endif; ?>

          <?php if (in_array($userRole, ['admin', 'warehouse'])): ?>
          <a href="<?= $base ?>/views/inventory.php" class="action-btn" style="padding: 1rem; text-align: left; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 1.75rem; min-width: 2rem; text-align: center;"><i class="bi bi-building" style="color: #48bb78;"></i></div>
            <div style="font-weight: 600; font-size: 0.95rem;">Warehousing</div>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- PENDING TASKS -->
    <div class="section-card">
      <div class="section-header">
        <h5 class="section-title">
          <i class="bi bi-list-check" style="margin-right: 0.5rem; color: #48bb78;"></i>
          Pending Tasks
        </h5>
      </div>
      <?php if (count($pendingTasks) > 0): ?>
        <ul class="task-list">
          <?php foreach ($pendingTasks as $idx => $task): 
            $priorities = ['high', 'high', 'medium', 'medium', 'low'];
            $priority = $priorities[$idx % count($priorities)];
          ?>
          <li class="task-item">
            <div style="flex: 1;">
              <div style="margin-bottom: 0.25rem;"><?= htmlspecialchars($task['task']) ?></div>
              <div style="color: #a0aec0; font-size: 0.85rem;">Due: <?= date('M d, Y', strtotime($task['due_date'] ?? date('Y-m-d'))) ?></div>
            </div>
            <span class="task-priority priority-<?= $priority ?>"><?= strtoupper($priority) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-state-icon">✓</div>
          <div>No pending tasks</div>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<script>
  document.querySelectorAll('.action-btn').forEach(btn => {
    btn.addEventListener('mouseenter', function() {
      this.style.background = '#374151';
      this.style.borderColor = '#48bb78';
    });
    btn.addEventListener('mouseleave', function() {
      this.style.background = '#2d3748';
      this.style.borderColor = '#4a5568';
    });
  });
</script>

<?php require_once __DIR__ . "/layout/footer.php"; ?>


