<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../../config/app.php";
$base = app_base_url();

$role  = $_SESSION['user']['role'] ?? 'guest';
$name  = $_SESSION['user']['fullname'] ?? 'User';
$email = $_SESSION['user']['email'] ?? '';

/** Role helper */
if (!function_exists('can')) {
  function can(string $role, array $allowed): bool {
    if ($role === 'admin') return true;
    return in_array($role, $allowed, true);
  }
}

$uri = $_SERVER['REQUEST_URI'] ?? '';
if (!function_exists('is_active')) {
  function is_active(string $uri, string $needle): bool {
    return ($needle !== '' && str_contains($uri, $needle));
  }
}

/** Grouped active helpers for dropdown parents */
if (!function_exists('any_active')) {
  function any_active(string $uri, array $needles): bool {
    foreach ($needles as $n) {
      if ($n !== '' && str_contains($uri, $n)) return true;
    }
    return false;
  }
}
?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/design.css">
<!-- Hamburger should be a sibling of sidebar/topbar for CSS/JS to work -->
<button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>

<nav class="sidebar collapsed" id="sidebar">
  <!-- Sidebar Header -->
  <div class="sidebar-header">
    <div class="logo-wrapper">
      <img src="<?= $base ?>/assets/logo1.png" alt="Logo" class="logo-icon">
      <span class="logo-text">byaHERO</span>
    </div>
    <button class="sidebar-close" id="sidebarClose"><i class="bi bi-x-lg"></i></button>
  </div>

  <!-- Navigation List -->
  <ul class="nav-list">
    <!-- DASHBOARD -->
    <li class="nav-item">
      <a class="nav-link no-green <?= is_active($uri, '/index.php') || is_active($uri, '/views/dashboard.php') ? 'active' : '' ?>"
         href="<?= $base ?>/views/dashboard.php">
        <i class="bi bi-speedometer2"></i>
        <span class="nav-label">Dashboard</span>
      </a>
    </li>

    <!-- PROCUREMENT MODULE -->
    <?php if (can($role, ['admin', 'manager', 'procurement'])): ?>
      <li class="nav-item">
        <a class="nav-link"
           href="<?= $base ?>/views/procurement.php">
          <i class="bi bi-bag-check"></i>
          <span class="nav-label">Procurement</span>
        </a>
      </li>
    <?php endif; ?>

    <!-- PROJECTS MODULE -->
    <?php if (can($role, ['admin', 'manager', 'project'])): ?>
      <li class="nav-item">
        <a class="nav-link"
           href="<?= $base ?>/views/projects.php">
          <i class="bi bi-diagram-3"></i>
          <span class="nav-label">Projects</span>
        </a>
      </li>
    <?php endif; ?>

    <!-- ASSETS MODULE -->
    <?php if (can($role, ['admin', 'manager', 'asset'])): ?>
      <li class="nav-item">
        <a class="nav-link"
           href="<?= $base ?>/views/fleet.php">
          <i class="bi bi-box-seam"></i>
          <span class="nav-label">Assets</span>
        </a>
      </li>
    <?php endif; ?>

    <!-- MRO MODULE -->
    <?php if (can($role, ['admin', 'manager', 'mro'])): ?>
      <li class="nav-item">
        <a class="nav-link"
           href="<?= $base ?>/views/maintenance.php">
          <i class="bi bi-wrench"></i>
          <span class="nav-label">MRO</span>
        </a>
      </li>
    <?php endif; ?>

    <!-- WAREHOUSING MODULE -->
    <?php if (can($role, ['admin', 'manager', 'warehouse'])): ?>
      <li class="nav-item">
        <a class="nav-link"
           href="<?= $base ?>/views/inventory.php">
          <i class="bi bi-building"></i>
          <span class="nav-label">Warehousing</span>
        </a>
      </li>
    <?php endif; ?>
    <li class="nav-separator" aria-hidden="true"></li>
  </ul>

  <!-- Sidebar Footer (Settings) -->
  <?php if ($role === 'admin'): ?>
    <div class="sidebar-footer">
      <li class="nav-item">
          <a class="nav-link no-green <?= is_active($uri, '/views/users.php') ? 'active' : '' ?>"
            href="<?= $base ?>/views/users.php">
          <i class="bi bi-people"></i>
          <span class="nav-label">Users</span>
        </a>
      </li>
      <li class="nav-item">
          <a class="nav-link no-green <?= is_active($uri, '/views/audit_logs.php') ? 'active' : '' ?>"
            href="<?= $base ?>/views/audit_logs.php">
          <i class="bi bi-clock-history"></i>
          <span class="nav-label">Audit Logs</span>
        </a>
      </li>
    </div>
  <?php endif; ?>
</nav>
