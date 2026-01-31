<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/User.php";

requireLogin();
requireRole(['admin']);

$user = new User($pdo);
$users = $user->getAll();
?>

<?php include "layout/header.php"; ?>

<?php include "layout/sidebar.php"; ?>
<?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

    <h2 class="mb-3">User Management</h2>
      <p class="text-muted mb-4">Manage system users and roles.</p>

      <!-- ADD USER -->
      <div class="form-card mb-4">
        <h5 class="mb-3">Add User</h5>

        <form method="POST" action="../controllers/UserController.php" class="row g-3">
          <div class="col-md-3">
            <input class="form-control" name="fullname" placeholder="Full Name" required>
          </div>
          <div class="col-md-3">
            <input class="form-control" type="email" name="email" placeholder="Email" required>
          </div>
          <div class="col-md-2">
            <input class="form-control" type="password" name="password" placeholder="Password" required>
          </div>
          <div class="col-md-2">
            <select class="form-select" name="role" required>
              <option value="" disabled selected>Select Role</option>
              <option value="admin">Admin</option>
              <option value="manager">Manager</option>
              <option value="procurement">Procurement</option>
              <option value="warehouse">Warehouse</option>
              <option value="mro">MRO</option>
              <option value="asset">Asset</option>
              <option value="project">Project Manager</option>
              <option value="staff">Staff</option>
            </select>

          </div>
          <div class="col-md-2 d-grid">
            <button class="btn btn-primary" name="add">Add</button>
          </div>
        </form>
      </div>

      <!-- TABLE -->
      <div class="table-card">
        <h5 class="mb-3">Users List</h5>

        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['fullname']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= ucfirst($u['role']) ?></td>
              <td><?= $u['created_at'] ?></td>
              <td class="text-end">
                <a href="user_edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="../controllers/UserController.php?delete=<?= $u['id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Delete user?')">
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>

<?php include "layout/footer.php"; ?>



