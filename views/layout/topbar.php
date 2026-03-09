<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../../config/app.php";
require_once __DIR__ . "/../../config/flash.php";

$base = app_base_url();

$name  = $_SESSION['user']['fullname'] ?? 'User';
$email = $_SESSION['user']['email'] ?? '';
$role  = $_SESSION['user']['role'] ?? 'guest';

$avatarPath = trim((string)($_SESSION['user']['avatar_path'] ?? ''));
$avatarSrc = $base . '/assets/img/profile.png';
if ($avatarPath !== '') {
    $avatarSrc = preg_match('/^https?:\/\//i', $avatarPath)
      ? $avatarPath
      : ($base . '/' . ltrim($avatarPath, '/'));
}

$success = get_flash('success');
$error   = get_flash('error');
?>

<header class="topbar">
  <button class="top-icon mobile-search-trigger" type="button" aria-label="Search" style="display:none;">
    <i class="fas fa-search"></i>
  </button>

  <div class="search-container">
    <i class="fas fa-search"></i>
    <input type="text" placeholder="Search for drivers, reports, or settings..." class="search-input">
  </div>

  <div class="topbar-actions">
    <div class="top-icon notification-icon notification-dropdown" id="notificationBell" role="button" tabindex="0" aria-label="View notifications">
      <i class="fas fa-bell"></i>
      <span class="notification-badge" id="notificationCount" style="display:none;">0</span>
      <div class="notification-menu" id="notificationDropdown">
        <div class="dropdown-header">
          <h3>Notifications</h3>
          <button class="btn-text" id="markAllRead" type="button">Mark all read</button>
        </div>
        <div class="notification-list" id="notificationList">
          <div class="notification-empty">
            <i class="fas fa-bell-slash"></i>
            <p>No new notifications</p>
          </div>
        </div>
      </div>
    </div>

    <a href="<?= $base ?>/views/user_edit.php" class="top-icon" title="Settings">
      <i class="fas fa-cog"></i>
    </a>

    <div class="profile-menu" id="profileMenu" role="button" tabindex="0" aria-label="Open profile menu">
      <img src="<?= htmlspecialchars($avatarSrc) ?>" class="profile-img" alt="Profile picture">

      <div class="profile-dropdown">
        <h3><?= htmlspecialchars($name) ?></h3>
        <p><?= htmlspecialchars($email) ?></p>

        <a href="<?= $base ?>/views/user_edit.php" class="profile-btn d-inline-block text-center text-decoration-none">Profile</a>
        <button class="logout-btn" type="button" data-logout-url="<?= $base ?>/auth/logout.php">Sign Out</button>
      </div>
    </div>
  </div>
</header>

<?php if ($success): ?>
  <div class="alert alert-success mx-3 mt-2 mb-0">
    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger mx-3 mt-2 mb-0">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<!-- Generic Add Modal Template -->
<div id="addModal" class="modal-overlay">
    <div class="app-modal-panel">
        <div class="app-modal-header">
            <h2 id="modalTitle">Add Item</h2>
        </div>
        <div class="app-modal-body" id="modalBody">
            <!-- Form will be injected here -->
        </div>
    </div>
</div>
