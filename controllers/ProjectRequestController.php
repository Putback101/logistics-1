<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Procurement.php";
require "../models/Budget.php";
require "../models/Item.php";
require "../models/Project.php";

requireLogin();
requireRole(['admin','manager','project_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canEditProjects = hasPermission($userRole, 'projects', 'edit');

if (!$canEditProjects) {
  http_response_code(403);
  set_flash('error', 'You are not allowed to submit project requests.');
  header("Location: ../views/project/projects.php");
  exit;
}

if (!isset($_POST['create_project_request'])) {
  header("Location: ../views/project/projects.php");
  exit;
}

$projectId = $_POST['project_id'] ?? '';
$requestKind = strtolower(trim((string)($_POST['request_kind'] ?? 'material')));
$budgetYearRaw = $_POST['budget_year'] ?? '';
$estimatedAmount = (float)($_POST['estimated_amount'] ?? 0);
$notes = trim((string)($_POST['notes'] ?? ''));

if (!ctype_digit((string)$projectId)) {
  set_flash('error', 'Invalid project.');
  header("Location: ../views/project/projects.php");
  exit;
}
$projectId = (int)$projectId;

if (!ctype_digit((string)$budgetYearRaw) || (int)$budgetYearRaw <= 0) {
  set_flash('error', 'Select a valid budget year.');
  header("Location: ../views/project/projects.php?tab=planning&project_id={$projectId}");
  exit;
}
$budgetYear = (int)$budgetYearRaw;

$projectModel = new Project($pdo);
$project = $projectModel->getById($projectId);
if (!$project) {
  set_flash('error', 'Project not found.');
  header("Location: ../views/project/projects.php");
  exit;
}

$budgetModel = new Budget($pdo);
if (!$budgetModel->getByYear($budgetYear)) {
  set_flash('error', 'No budget configured for selected year.');
  header("Location: ../views/project/projects.php?tab=planning&project_id={$projectId}");
  exit;
}

$procurement = new Procurement($pdo);
$itemModel = new Item($pdo);

$itemId = null;
$itemName = '';
$quantity = 0;
$supplier = 'TBD';
$workforceRole = '';

if ($requestKind === 'workforce') {
  $workforceRole = trim((string)($_POST['workforce_role'] ?? ''));
  $quantity = (int)($_POST['workforce_count'] ?? 0);
  if ($workforceRole === '' || $quantity <= 0) {
    set_flash('error', 'Workforce role and headcount are required.');
    header("Location: ../views/project/projects.php?tab=planning&project_id={$projectId}");
    exit;
  }
  $itemName = "Contracted Workers - {$workforceRole}";
  $supplier = 'External Workforce';
} else {
  $itemIdRaw = $_POST['item_id'] ?? '';
  $quantity = (int)($_POST['material_qty'] ?? 0);
  $supplierInput = trim((string)($_POST['supplier'] ?? ''));
  if (ctype_digit((string)$itemIdRaw)) {
    $itemId = (int)$itemIdRaw;
    $masterName = $itemModel->getNameById($itemId);
    if ($masterName !== null && $masterName !== '') {
      $itemName = $masterName;
    } else {
      $itemId = null;
    }
  }

  if ($itemName === '') {
    $itemName = trim((string)($_POST['material_name'] ?? ''));
  }
  if ($itemName === '' || $quantity <= 0) {
    set_flash('error', 'Material item and quantity are required.');
    header("Location: ../views/project/projects.php?tab=planning&project_id={$projectId}");
    exit;
  }
  if ($supplierInput !== '') {
    $supplier = $supplierInput;
  }
}

if ($estimatedAmount < 0) {
  set_flash('error', 'Estimated amount must be zero or higher.');
  header("Location: ../views/project/projects.php?tab=planning&project_id={$projectId}");
  exit;
}

$allocated = (float)($budgetModel->getByYear($budgetYear)['allocated'] ?? 0);
$spent = (float)($budgetModel->getByYear($budgetYear)['spent'] ?? 0);
$requested = $procurement->getYearRequestedTotal($budgetYear, ['Pending', 'Approved']);
$available = $allocated - $spent - $requested;
if ($estimatedAmount > $available) {
  set_flash('error', 'Budget exceeded. Available for selected year: PHP ' . number_format($available, 2) . '.');
  header("Location: ../views/project/projects.php?tab=planning&project_id={$projectId}");
  exit;
}

$requestRef = 'PRJ-REQ-' . date('Ymd-His') . '-' . random_int(100, 999);
$sourceReference = 'PROJECT-' . $projectId;
$sourcePayload = json_encode([
  'request_kind' => $requestKind,
  'workforce_role' => $workforceRole,
  'notes' => $notes,
], JSON_UNESCAPED_SLASHES);

$ok = $procurement->create(
  $itemName,
  $quantity,
  $supplier,
  'Pending',
  $budgetYear,
  $estimatedAmount,
  $requestRef,
  $itemId,
  (int)($_SESSION['user']['id'] ?? 0),
  'project_management',
  'logistics-1',
  $sourceReference,
  $sourcePayload,
  $projectId
);

if (!$ok) {
  set_flash('error', 'Failed to submit project request.');
  header("Location: ../views/project/projects.php?tab=planning&project_id={$projectId}");
  exit;
}

$pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
  ->execute([
    $_SESSION['user']['id'],
    "Submitted {$requestKind} project request ({$requestRef}) for project ID {$projectId}"
  ]);

set_flash('success', 'Project request submitted to Procurement.');
header("Location: ../views/project/projects.php?tab=planning&project_id={$projectId}");
exit;
