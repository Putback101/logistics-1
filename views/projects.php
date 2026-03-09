<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/permissions.php";
require_once __DIR__ . "/../models/Project.php";
require_once __DIR__ . "/helpers/badges.php";

requireLogin();
requireRole(['admin','manager','project','project_staff']);

$project = new Project($pdo);
$projects = $project->getAll();
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'projects';

$userRole = $_SESSION['user']['role'] ?? 'staff';
$canAdd = hasPermission($userRole, 'projects', 'add');
$canEdit = hasPermission($userRole, 'projects', 'edit');
$canDelete = hasPermission($userRole, 'projects', 'delete');
?>
<?php require_once __DIR__ . "/layout/header.php"; ?>

<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <h2 class="mb-2">Project Management</h2>
    <p class="text-muted mb-4">Create projects, manage timelines, and allocate resources.</p>

    <!-- TAB NAVIGATION -->
    <div class="tab-navigation mb-4">
      <a href="?tab=projects" class="tab-btn <?= $activeTab === 'projects' ? 'active' : '' ?>">
        <i class="bi bi-kanban"></i> Projects
      </a>
      <a href="?tab=tasks" class="tab-btn <?= $activeTab === 'tasks' ? 'active' : '' ?>">
        <i class="bi bi-check2-square"></i> Tasks
      </a>
      <a href="?tab=resources" class="tab-btn <?= $activeTab === 'resources' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Resources
      </a>
      <a href="?tab=reports" class="tab-btn <?= $activeTab === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Reports
      </a>
    </div>

    <!-- PROJECTS TAB -->
    <?php if ($activeTab === 'projects'): ?>
      <!-- ACTION BAR -->
      <div class="mb-4">
        <?php if ($canAdd): ?>
        <button class="btn btn-primary" onclick="openAddProjectModal()">
          <i class="bi bi-plus-circle"></i> Create Project
        </button>
        <?php endif; ?>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Projects List</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Name</th><th>Type</th><th>Timeline</th><th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($projects as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['type']) ?></td>
                <td class="text-muted small">
                  <?= htmlspecialchars($p['start_date'] ?? '-') ?> → <?= htmlspecialchars($p['end_date'] ?? '-') ?>
                </td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($p['status']) ?></span></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="project_view.php?id=<?= $p['id'] ?>">
                    <i class="bi bi-kanban"></i> Open
                  </a>
                  <?php if ($canEdit): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="project_edit.php?id=<?= $p['id'] ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                    <a class="btn btn-sm btn-outline-danger"
                       href="../controllers/ProjectController.php?delete=<?= $p['id'] ?>"
                       onclick="return confirm('Delete this project?')">
                       <i class="bi bi-trash"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($canAdd): ?>
      <!-- ADD PROJECT FORM (Hidden by default, shown in modal) -->
      <div id="addProjectForm" style="display: none;">
        <form method="POST" action="../controllers/ProjectController.php" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Project Name</label>
            <input name="name" class="form-control" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
              <option value="General">General</option>
              <option value="Fleet Expansion">Fleet Expansion</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Start</label>
            <input type="date" name="start_date" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">End</label>
            <input type="date" name="end_date" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option>Planned</option><option>Ongoing</option><option>Completed</option><option>On Hold</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" name="add" class="btn btn-primary flex-grow-1">
              <i class="bi bi-plus-circle"></i> Create Project
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- TASKS TAB -->
    <?php if ($activeTab === 'tasks'): ?>
      <div class="table-card">
        <h5 class="mb-3">Project Tasks</h5>
        <p class="text-muted">View and manage tasks across all projects</p>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Task</th><th>Project</th><th>Status</th><th>Due Date</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="5" class="text-muted text-center py-4">No tasks available</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- RESOURCES TAB -->
    <?php if ($activeTab === 'resources'): ?>
      <div class="table-card">
        <h5 class="mb-3">Project Resources</h5>
        <p class="text-muted">Manage resources allocated to projects</p>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Resource</th><th>Project</th><th>Type</th><th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="5" class="text-muted text-center py-4">No resources allocated</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- REPORTS TAB -->
    <?php if ($activeTab === 'reports'): ?>
      <div class="table-card">
        <h5 class="mb-3">Project Reports</h5>
        <p class="text-muted">Project analytics and reporting coming soon</p>
      </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . "/layout/footer.php"; ?>


