<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/User.php";

requireLogin();

$sessionUser = $_SESSION['user'] ?? [];
$sessionRole = (string)($sessionUser['role'] ?? '');
$sessionUserId = (int)($sessionUser['id'] ?? 0);
$isAdmin = ($sessionRole === 'admin');

$requestedId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$targetUserId = ($isAdmin && $requestedId > 0) ? $requestedId : $sessionUserId;

if ($targetUserId <= 0) {
  header("Location: ../auth/login.php");
  exit;
}

$userModel = new User($pdo);
$data = $userModel->getById($targetUserId);
if (!$data) {
  if ($isAdmin) {
    header("Location: users.php");
  } else {
    header("Location: ../auth/logout.php");
  }
  exit;
}

$roles = [
  'admin' => 'Admin',
  'manager' => 'Manager',
  'procurement_staff' => 'Procurement Staff',
  'project_staff' => 'Project Staff',
  'asset' => 'Assets Staff',
  'mro_staff' => 'MRO Staff',
  'warehouse_staff' => 'Warehouse Staff',
];

$section = isset($_GET['section']) ? (string)$_GET['section'] : 'profile';
if (!in_array($section, ['profile', 'password', 'logs'], true)) {
  $section = 'profile';
}

$logsStmt = $pdo->prepare("SELECT action, log_time FROM audit_logs WHERE user_id = ? ORDER BY log_time DESC LIMIT 25");
$logsStmt->execute([$targetUserId]);
$activityLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

$displayRole = $roles[$data['role']] ?? ucfirst((string)$data['role']);
$initials = '';
$parts = preg_split('/\s+/', trim((string)$data['fullname'])) ?: [];
foreach ($parts as $p) {
  if ($p !== '') {
    $initials .= strtoupper($p[0]);
  }
  if (strlen($initials) >= 2) {
    break;
  }
}
if ($initials === '') {
  $initials = 'U';
}

$baseQuery = $isAdmin ? ('id=' . (int)$targetUserId . '&') : '';
$avatarPath = trim((string)($data['avatar_path'] ?? ''));
$avatarUrl = '';
if ($avatarPath !== '') {
  $avatarUrl = preg_match('/^https?:\/\//i', $avatarPath)
    ? $avatarPath
    : (app_base_url() . '/' . ltrim($avatarPath, '/'));
}
?>

<?php include "layout/header.php"; ?>
<?php include "layout/sidebar.php"; ?>
<?php include "layout/topbar.php"; ?>
<style>
.profile-wrap .table-card {
  background: linear-gradient(120deg, rgba(31, 33, 46, 0.96), rgba(36, 39, 54, 0.96));
  border: 1px solid rgba(255, 255, 255, 0.12);
}
.profile-wrap.profile-view {
  padding-top: 22px;
}
.profile-shell {
  width: min(780px, 100%);
  margin: 0 auto 40px;
  display: grid;
  gap: 24px;
}
.profile-shell .table-card {
  margin-bottom: 0 !important;
  padding: 24px;
  border-radius: 16px;
}
.profile-shell h4 {
  margin-bottom: 14px !important;
}
.profile-shell hr {
  margin: 0 0 22px;
  opacity: .22;
}
.profile-tabs {
  display: inline-flex;
  gap: 6px;
  padding: 4px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,.16);
  background: rgba(8,11,22,.8);
}
.profile-tabs .btn {
  border-radius: 999px;
}
.profile-hero {
  min-height: 340px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  position: relative;
}
.profile-avatar {
  width: 102px;
  height: 102px;
  border-radius: 50%;
  background: var(--color-accent);
  color: #06101a;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 2.1rem;
  font-weight: 800;
  overflow: hidden;
}
.profile-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.profile-photo-btn {
  margin-top: 14px;
  border-radius: 999px;
  cursor: pointer;
}
.profile-accent { display: none; }
.profile-status {
  margin-top: 12px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid rgba(0,230,118,.45);
  background: rgba(0,230,118,.12);
  color: #e9fff3;
  font-weight: 600;
}
.profile-status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #fff;
}
.profile-info-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 20px;
}
.profile-info-label {
  color: var(--color-text-secondary);
  font-size: .82rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: 6px;
}
.profile-info-value {
  color: var(--color-text-primary);
  font-size: 1.05rem;
  font-weight: 600;
}
.profile-activity-item {
  background: rgba(8, 11, 22, 0.72);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 12px;
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.profile-activity-content {
  display: grid;
  gap: 4px;
}
.profile-activity-time {
  color: var(--color-text-secondary);
  font-size: .97rem;
}
.profile-activity-dot {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(0, 230, 118, 0.12);
  border: 1px solid rgba(0, 230, 118, 0.2);
  color: var(--color-accent);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 42px;
  font-size: 1.1rem;
}
@media (max-width: 900px) {
  .profile-shell {
    width: min(780px, calc(100vw - 72px));
  }
  .profile-info-grid {
    grid-template-columns: 1fr 1fr;
  }
}
@media (max-width: 640px) {
  .profile-wrap.profile-view {
    padding-top: 12px;
  }
  .profile-shell {
    width: calc(100vw - 28px);
    gap: 18px;
  }
  .profile-shell .table-card {
    padding: 18px;
    border-radius: 14px;
  }
  .profile-hero {
    min-height: 300px;
  }
  .profile-accent { display: none; }
}
</style>

<main class="main-content">
  <div class="content-area profile-wrap <?= $section === 'profile' ? 'profile-view' : '' ?>">
    <?php if ($section !== 'profile'): ?>
      <div class="module-header mb-3">
        <div>
          <h2 class="mb-1">Profile</h2>
          <div class="text-muted mb-0">Manage your account details and recent account activity.</div>
        </div>
        <?php if ($isAdmin): ?>
          <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <?php endif; ?>
      </div>
      <div class="mb-3">
        <div class="profile-tabs">
          <a href="user_edit.php?<?= $baseQuery ?>section=profile" class="btn <?= $section === 'profile' ? 'btn-primary' : 'btn-outline-secondary' ?>">Profile</a>
          <a href="user_edit.php?<?= $baseQuery ?>section=password" class="btn <?= $section === 'password' ? 'btn-primary' : 'btn-outline-secondary' ?>">Update Password</a>
          <a href="user_edit.php?<?= $baseQuery ?>section=logs" class="btn <?= $section === 'logs' ? 'btn-primary' : 'btn-outline-secondary' ?>">Activity Logs</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($section === 'profile'): ?>
      <div class="profile-shell">
        <div class="table-card profile-hero">
          <div>
            <div class="profile-avatar">
              <?php if ($avatarUrl !== ''): ?>
                <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Profile Photo">
              <?php else: ?>
                <?= htmlspecialchars($initials) ?>
              <?php endif; ?>
            </div>
            <form method="POST" action="../controllers/ProfileController.php" enctype="multipart/form-data" id="photoUploadForm">
              <input type="hidden" name="action" value="update_photo">
              <input type="hidden" name="user_id" value="<?= (int)$targetUserId ?>">
              <input type="file" name="photo" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
              <button type="button" class="btn btn-outline-secondary profile-photo-btn" id="profilePhotoBtn">
                <i class="bi bi-camera"></i> Change Photo
              </button>
            </form>
            <h3 class="mt-3 mb-1"><?= htmlspecialchars((string)$data['fullname']) ?></h3>
            <div class="text-muted"><?= htmlspecialchars($displayRole) ?></div>
            <div class="profile-status"><span class="profile-status-dot"></span> Active Employee</div>
                      </div>
        </div>

        <div class="table-card">
          <h4><i class="bi bi-person-circle"></i> Personal Information</h4>
          <hr>
          <div class="profile-info-grid">
            <div>
              <div class="profile-info-label">Full Name</div>
              <div class="profile-info-value"><?= htmlspecialchars((string)$data['fullname']) ?></div>
            </div>
            <div>
              <div class="profile-info-label">Email</div>
              <div class="profile-info-value"><?= htmlspecialchars((string)$data['email']) ?></div>
            </div>
            <div>
              <div class="profile-info-label">Employee ID</div>
              <div class="profile-info-value">EMP-<?= str_pad((string)(int)$data['id'], 4, '0', STR_PAD_LEFT) ?></div>
            </div>
            <div>
              <div class="profile-info-label">Department</div>
              <div class="profile-info-value"><?= htmlspecialchars($isAdmin ? 'Administration' : 'Operations') ?></div>
            </div>
            <div>
              <div class="profile-info-label">Role</div>
              <div class="profile-info-value"><?= htmlspecialchars($displayRole) ?></div>
            </div>
          </div>
        </div>

        <div class="table-card">
          <h4><i class="bi bi-arrow-counterclockwise"></i> Recent Activity</h4>
          <hr>
          <?php foreach (array_slice($activityLogs, 0, 5) as $log): ?>
            <div class="profile-activity-item mb-2">
              <span class="profile-activity-dot"><i class="bi bi-box-arrow-in-right"></i></span>
              <div class="profile-activity-content">
                <div class="fw-semibold"><?= htmlspecialchars((string)$log['action']) ?></div>
                <div class="profile-activity-time"><?= htmlspecialchars((string)$log['log_time']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (count($activityLogs) === 0): ?>
            <div class="text-muted">No activity logs found.</div>
          <?php endif; ?>
        </div>
      </div>

    <?php elseif ($section === 'password'): ?>
      <div class="table-card">
        <h5 class="mb-3">Update Password</h5>
        <form method="POST" action="../controllers/ProfileController.php" class="row g-3">
          <input type="hidden" name="action" value="update_password">
          <input type="hidden" name="user_id" value="<?= (int)$targetUserId ?>">

          <?php if (!$isAdmin || $targetUserId === $sessionUserId): ?>
          <div class="col-12">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <?php endif; ?>

          <div class="col-md-6">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="6" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
          </div>

          <div class="col-12 d-flex gap-2 justify-content-end">
            <button type="submit" class="btn btn-primary"><i class="bi bi-key"></i> Update Password</button>
          </div>
        </form>
      </div>

    <?php else: ?>
      <div class="table-card">
        <h5 class="mb-3">Activity Logs</h5>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Action</th>
                <th style="width:220px;">Date / Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activityLogs as $log): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$log['action']) ?></td>
                  <td><?= htmlspecialchars((string)$log['log_time']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (count($activityLogs) === 0): ?>
                <tr><td colspan="2" class="text-muted">No activity logs found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>




<script>
document.addEventListener('DOMContentLoaded', function () {
  var photoBtn = document.getElementById('profilePhotoBtn');
  var photoInput = document.getElementById('profilePhotoInput');
  var photoForm = document.getElementById('photoUploadForm');
  if (!photoBtn || !photoInput || !photoForm) return;

  photoBtn.addEventListener('click', function () {
    photoInput.click();
  });

  photoInput.addEventListener('change', function () {
    if (!photoInput.files || !photoInput.files.length) return;
    photoForm.submit();
  });
});
</script>

<?php include "layout/footer.php"; ?>
