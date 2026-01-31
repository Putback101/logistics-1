<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../../config/app.php";
require_once __DIR__ . "/../../config/flash.php";

$base = app_base_url();

$name  = $_SESSION['user']['fullname'] ?? 'User';
$email = $_SESSION['user']['email'] ?? '';
$role  = $_SESSION['user']['role'] ?? 'guest';

$success = get_flash('success');
$error   = get_flash('error');
?>

<header class="topbar">
        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search for drivers, reports, or settings…" class="search-input">
        </div>

        <div class="topbar-actions">

            <div class="top-icon notification-icon">
                <i class="fas fa-bell"></i>
            </div>

            <a href="<?= $base ?>/views/user_edit.php" class="top-icon" title="Settings">
                <i class="fas fa-cog"></i>
            </a>

            <div class="profile-menu" id="profileMenu">
                <img src="<?= $base ?>/assets/img/profile.png" class="profile-img">

                <div class="profile-dropdown">
                    <h3><?= htmlspecialchars($name) ?></h3>
                    <p><?= htmlspecialchars($email) ?></p>

                    <button class="profile-btn">Profile</button>
                    <button class="logout-btn">Sign Out</button>
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
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add Item</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Form will be injected here -->
        </div>
    </div>
</div>

<script src="<?= $base ?>/assets/js/script.js"></script>
