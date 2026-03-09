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

<style>
.flash-toast-stack {
  position: fixed;
  bottom: 18px;
  right: 18px;
  z-index: 3200;
  display: grid;
  gap: 10px;
  width: min(360px, calc(100vw - 24px));
}

.flash-toast {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(12, 16, 28, 0.96);
  box-shadow: 0 14px 26px rgba(0, 0, 0, 0.42);
  color: #e9eef9;
  transform: translateY(-8px);
  opacity: 0;
  pointer-events: none;
  transition: opacity .22s ease, transform .22s ease;
}

.flash-toast.show {
  opacity: 1;
  transform: translateY(0);
  pointer-events: auto;
}

.flash-toast-icon {
  width: 22px;
  line-height: 22px;
  text-align: center;
  flex: 0 0 22px;
}

.flash-toast-message {
  flex: 1;
  font-size: 0.92rem;
  font-weight: 600;
  line-height: 1.35;
}

.flash-toast-close {
  border: 0;
  background: transparent;
  color: rgba(234, 240, 255, 0.72);
  cursor: pointer;
  font-size: 0.95rem;
  line-height: 1;
  padding: 2px;
}

.flash-toast-close:hover {
  color: #ffffff;
}

.flash-toast-success {
  border-left: 4px solid #00e676;
}

.flash-toast-success .flash-toast-icon {
  color: #00e676;
}

.flash-toast-error {
  border-left: 4px solid #ff6666;
}

.flash-toast-error .flash-toast-icon {
  color: #ff6666;
}
</style>

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

    <a href="<?= $base ?>/views/user_edit.php?section=password" class="top-icon" title="Change Password">
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

<?php if ($success || $error): ?>
  <div class="flash-toast-stack" id="flashToastStack">
    <?php if ($success): ?>
      <div class="flash-toast flash-toast-success" data-flash-toast>
        <span class="flash-toast-icon"><i class="bi bi-check-circle-fill"></i></span>
        <div class="flash-toast-message"><?= htmlspecialchars($success) ?></div>
        <button type="button" class="flash-toast-close" data-flash-close aria-label="Close notification">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="flash-toast flash-toast-error" data-flash-toast>
        <span class="flash-toast-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
        <div class="flash-toast-message"><?= htmlspecialchars($error) ?></div>
        <button type="button" class="flash-toast-close" data-flash-close aria-label="Close notification">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    <?php endif; ?>
  </div>
  <script>
    (function () {
      var toasts = document.querySelectorAll('[data-flash-toast]');
      if (!toasts.length) return;

      toasts.forEach(function (toast, index) {
        setTimeout(function () {
          toast.classList.add('show');
        }, 60 + (index * 80));

        var closeBtn = toast.querySelector('[data-flash-close]');
        if (closeBtn) {
          closeBtn.addEventListener('click', function () {
            toast.classList.remove('show');
            setTimeout(function () {
              toast.remove();
            }, 220);
          });
        }

        setTimeout(function () {
          toast.classList.remove('show');
          setTimeout(function () {
            toast.remove();
          }, 220);
        }, 4800 + (index * 300));
      });
    })();
  </script>
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
