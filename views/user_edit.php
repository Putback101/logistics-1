<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/User.php";

requireLogin();
requireRole(['admin']);

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  header("Location: users.php");
  exit;
}

$user = new User($pdo);
$data = $user->getById($_GET['id']);

if (!$data) {
  header("Location: users.php");
  exit;
}
?>

<?php include "layout/header.php"; ?>


  <?php include "layout/sidebar.php"; ?>


    <?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h2 class="mb-1">Edit User</h2>
          <div class="text-muted">Update user details and role.</div>
        </div>
        <a href="users.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Back
        </a>
      </div>

      <div class="form-card">
        <form method="POST" action="../controllers/UserController.php" class="row g-3">
          <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">

          <div class="col-md-4">
            <label class="form-label">Full Name</label>
            <input type="text" name="fullname" class="form-control"
                   value="<?= htmlspecialchars($data['fullname']) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="<?= htmlspecialchars($data['email']) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
              <?php
                $roles = ['admin','manager','procurement','warehouse','mro','asset','project','staff'];
                foreach ($roles as $r) {
                  $sel = ($data['role'] === $r) ? 'selected' : '';
                  echo "<option value=\"".htmlspecialchars($r)."\" $sel>".htmlspecialchars(ucfirst($r))."</option>";
                }
              ?>
            </select>
          </div>

          <div class="col-12 d-flex gap-2 justify-content-end">
            <a href="users.php" class="btn btn-light border">Cancel</a>
            <button type="submit" name="update" class="btn btn-primary">
              <i class="bi bi-save"></i> Save Changes
            </button>
          </div>
        </form>

        <hr class="my-4">

        <!-- OPTIONAL: Reset Password (if you want this feature) -->
        <div class="text-muted small">
          Note: Password is not edited here. (Optional: create a separate “Reset Password” feature.)
        </div>
      </div>
    </div>

  </main>
</div>

<?php include "layout/footer.php"; ?>




