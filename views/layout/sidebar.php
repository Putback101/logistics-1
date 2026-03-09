<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../../config/app.php";
$base = app_base_url();

$role  = $_SESSION['user']['role'] ?? 'guest';
$name  = $_SESSION['user']['fullname'] ?? 'User';
$email = $_SESSION['user']['email'] ?? '';

if (!function_exists('expand_roles')) {
  function expand_roles(array $allowed): array {
    $aliases = [
      'procurement' => ['procurement_staff'],
      'project' => ['project_staff'],
      'mro' => ['mro_staff'],
      'warehouse' => ['warehouse_staff'],
    ];

    $out = $allowed;
    foreach ($allowed as $r) {
      if (isset($aliases[$r])) {
        foreach ($aliases[$r] as $a) {
          $out[] = $a;
        }
      }
    }

    return array_values(array_unique($out));
  }
}

if (!function_exists('can')) {
  function can(string $role, array $allowed): bool {
    if ($role === 'admin') return true;
    return in_array($role, expand_roles($allowed), true);
  }
}

$uri = $_SERVER['REQUEST_URI'] ?? '';
if (!function_exists('is_active')) {
  function is_active(string $uri, string $needle): bool {
    return ($needle !== '' && str_contains($uri, $needle));
  }
}

if (!function_exists('any_active')) {
  function any_active(string $uri, array $needles): bool {
    foreach ($needles as $n) {
      if ($n !== '' && str_contains($uri, $n)) return true;
    }
    return false;
  }
}
?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/layout.css">
<button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>

<nav class="sidebar collapsed" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-brand">
      <img src="<?= $base ?>/assets/logo.png" alt="ByaHERO Logo" class="logo-img">
      <h2>ByaHERO</h2>
    </div>
  </div>

  <ul class="nav-list">
    <li class="nav-item">
      <a class="nav-link no-green <?= is_active($uri, '/index.php') || is_active($uri, '/views/dashboard.php') ? 'active' : '' ?>" data-tooltip="Dashboard"
         href="<?= $base ?>/views/dashboard.php">
        <i class="fas fa-home"></i>
        <span class="nav-label">Dashboard</span>
      </a>
    </li>

    <?php if (can($role, ['manager', 'project_staff'])): ?>
      <li class="nav-item">
        <a class="nav-link" data-tooltip="Project Management"
           href="<?= $base ?>/views/project/projects.php">
          <i class="fas fa-project-diagram"></i>
          <span class="nav-label">Project Management</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (can($role, ['manager', 'procurement_staff'])): ?>
      <li class="nav-item">
        <a class="nav-link" data-tooltip="Procurement"
           href="<?= $base ?>/views/procurement/procurement.php">
          <i class="fas fa-dolly-flatbed"></i>
          <span class="nav-label">Procurement</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (can($role, ['manager', 'asset'])): ?>
      <li class="nav-item">
        <a class="nav-link" data-tooltip="Asset Management"
           href="<?= $base ?>/views/asset/asset.php?tab=registry">
          <i class="fas fa-boxes"></i>
          <span class="nav-label">Asset Management</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (can($role, ['manager', 'mro_staff'])): ?>
      <li class="nav-item">
        <a class="nav-link" data-tooltip="MRO"
           href="<?= $base ?>/views/mro/maintenance.php">
          <i class="fas fa-tools"></i>
          <span class="nav-label">MRO</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (can($role, ['manager', 'warehouse_staff'])): ?>
      <li class="nav-item">
        <a class="nav-link" data-tooltip="Warehousing"
           href="<?= $base ?>/views/warehousing/inventory.php">
          <i class="fas fa-warehouse"></i>
          <span class="nav-label">Warehousing</span>
        </a>
      </li>
    <?php endif; ?>

    <li class="nav-separator" aria-hidden="true"></li>

    <?php if ($role === 'admin'): ?>
      <li class="nav-item">
        <a class="nav-link no-green <?= is_active($uri, '/views/users.php') ? 'active' : '' ?>" data-tooltip="Users"
           href="<?= $base ?>/views/users.php">
          <i class="fas fa-user"></i>
          <span class="nav-label">Users</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link no-green <?= is_active($uri, '/views/audit_logs.php') ? 'active' : '' ?>" data-tooltip="Audit Logs"
           href="<?= $base ?>/views/audit_logs.php">
          <i class="fas fa-history"></i>
          <span class="nav-label">Audit Logs</span>
        </a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
