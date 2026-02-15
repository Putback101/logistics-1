$path = 'c:\xampp\htdocs\logistics-1\views\project\projects.php'
$content = Get-Content -Raw -Path $path

$content = $content.Replace(
  '<tr><th>Name</th><th>Type</th><th>Timeline</th><th>Status</th><th class="text-end">Actions</th></tr>',
  '<tr><th>Project</th><th>Scope Snapshot</th><th>Planning Window</th><th>Status</th><th class="text-end">Actions</th></tr>'
)

$content = $content.Replace(
  '<td><?= htmlspecialchars($p[''type'']) ?></td>',
  '<td><?php $scope = trim((string)($p[''description''] ?? '''')); $scope = $scope === '''' ? ''No charter summary'' : (function_exists(''mb_strimwidth'') ? mb_strimwidth($scope, 0, 70, ''...'') : (strlen($scope) > 70 ? substr($scope, 0, 70) . ''...'' : $scope)); ?><?= htmlspecialchars($scope) ?></td>'
)

$insertBefore = '<div class="row g-3">'
$planningBlock = @"
<div class=\"table-card mb-3\">
  <h5 class=\"mb-3\">Planning Essentials</h5>
  <div class=\"row g-3\">
    <div class=\"col-lg-7\">
      <div class=\"p-3 rounded-3 border h-100\" style=\"border-color: rgba(255,255,255,.12) !important; background: rgba(255,255,255,.02);\">
        <div class=\"text-uppercase small text-muted mb-2\">Project Charter</div>
        <div class=\"fw-semibold mb-2\"><?= htmlspecialchars($selectedProject['name']) ?> (<?= htmlspecialchars($selectedProject['type']) ?>)</div>
        <div class=\"text-muted small mb-2\">Window: <?= htmlspecialchars($selectedProject['start_date'] ?? '-') ?> -> <?= htmlspecialchars($selectedProject['end_date'] ?? '-') ?></div>
        <div><?= htmlspecialchars(trim((string)($selectedProject['description'] ?? '')) !== '' ? (string)$selectedProject['description'] : 'Define objective, scope boundaries, deliverables, and key success criteria.') ?></div>
      </div>
    </div>
    <div class=\"col-lg-5\">
      <div class=\"p-3 rounded-3 border h-100\" style=\"border-color: rgba(255,255,255,.12) !important; background: rgba(255,255,255,.02);\">
        <div class=\"text-uppercase small text-muted mb-2\">Planning Checklist</div>
        <?php
          $planningChecks = [
            'Charter summary defined' => trim((string)($selectedProject['description'] ?? '')) !== '',
            'Start and end dates defined' => !empty($selectedProject['start_date']) && !empty($selectedProject['end_date']),
            'Initial milestone/tasks created' => count($projectTasks) > 0,
            'Resources allocated' => count($projectResources) > 0,
          ];
        ?>
        <?php foreach ($planningChecks as $label => $ok): ?>
          <div class=\"d-flex justify-content-between align-items-center border-bottom py-2\" style=\"border-color: rgba(255,255,255,.08) !important;\">
            <span class=\"small\"><?= htmlspecialchars($label) ?></span>
            <span class=\"badge <?= $ok ? 'bg-success' : 'bg-secondary' ?>\"><?= $ok ? 'Done' : 'Pending' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

$insertBefore
"@

$content = [regex]::Replace(
  $content,
  '(?s)(<\?php if \(\$selectedProjectId > 0 && \$selectedProject\): \?>\s*<div class="module-header mb-3">.*?</div>\s*</div>\s*)<div class="row g-3">',
  '$1' + $planningBlock,
  1
)

$content = $content.Replace('Scheduling: Add Task', 'Work Breakdown and Milestones')
$content = $content.Replace('Task Schedule', 'Milestone and Task Plan')
$content = $content.Replace('Resource Allocation', 'Capacity Planning')
$content = $content.Replace('Allocated Resources', 'Planned Resource Assignments')

Set-Content -Path $path -Value $content -NoNewline
