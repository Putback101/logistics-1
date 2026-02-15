<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/User.php";

requireLogin();
requireRole(['admin']);

$user = new User($pdo);
$users = $user->getAll();

$roleLabels = [
  'admin' => 'Admin',
  'manager' => 'Manager',
  'procurement_staff' => 'Procurement Staff',
  'project_staff' => 'Project Staff',
  'asset' => 'Assets Staff',
  'mro_staff' => 'MRO Staff',
  'warehouse_staff' => 'Warehouse Staff',
];
?>

<?php include "layout/header.php"; ?>
<?php include "layout/sidebar.php"; ?>
<?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <div class="module-header mb-3">
      <div>
        <h2 class="mb-1">User Management</h2>
        <p class="text-muted mb-0">Manage system users and roles.</p>
      </div>
    </div>

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
            <option value="procurement_staff">Procurement Staff</option>
            <option value="project_staff">Project Staff</option>
            <option value="asset">Assets Staff</option>
            <option value="mro_staff">MRO Staff</option>
            <option value="warehouse_staff">Warehouse Staff</option>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary" name="add">Add</button>
        </div>
      </form>
    </div>

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
            <td><?= htmlspecialchars($roleLabels[$u['role']] ?? ucfirst((string)$u['role'])) ?></td>
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

<?php include "layout/footer.php"; ?>
