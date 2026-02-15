<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Audit.php";

requireLogin();
requireRole(['admin']);

$audit = new Audit($pdo);
$logs = $audit->getAll();
?>

<?php include "layout/header.php"; ?>

<?php include "layout/sidebar.php"; ?>

<?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

    <div class="module-header mb-3">
      <div>
        <h2 class="mb-1">Audit Trail</h2>
        <p class="text-muted mb-0">System activity logs (read-only).</p>
      </div>
    </div>

      <div class="table-card">
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Action</th>
                <th>Date & Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= htmlspecialchars($l['fullname'] ?? 'System') ?></td>
                <td><?= ucfirst((string)($l['role'] ?? 'n/a')) ?></td>
                <td><?= htmlspecialchars($l['action']) ?></td>
                <td><?= htmlspecialchars((string)($l['log_time'] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

<?php include "layout/footer.php"; ?>



