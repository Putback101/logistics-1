<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/ProjectResource.php";

requireLogin();
requireRole(['admin','manager','project_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canEdit = hasPermission($userRole, 'projects', 'edit');

$res = new ProjectResource($pdo);

if (isset($_POST['add_resource'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to allocate project resources.');
    header("Location: ../views/project/projects.php"); exit;
  }

  $projectId = $_POST['project_id'] ?? '';
  $type = $_POST['resource_type'] ?? 'User';
  $resourceId = $_POST['resource_id'] ?? '';
  $roleLabel = trim($_POST['role_label'] ?? '');
  $from = $_POST['allocated_from'] ?? null;
  $to   = $_POST['allocated_to'] ?? null;
  $notes= trim($_POST['notes'] ?? '');

  if (!ctype_digit((string)$projectId) || !ctype_digit((string)$resourceId)) {
    set_flash('error', 'Invalid resource data.');
    header("Location: ../views/project/projects.php"); exit;
  }

  $res->create((int)$projectId,$type,(int)$resourceId,$roleLabel,$from,$to,$notes);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Added resource ($type:$resourceId) to project ID $projectId"]);

  set_flash('success', 'Resource allocated successfully.');
  header("Location: ../views/project/projects.php?tab=planning&project_id=$projectId&open_modal=planningBoardSwitchForm&open_modal_title=Choose%20Planning%20Section&planning_section=capacity-planning"); exit;
}

if (isset($_GET['delete_resource'])) {
  if (!$canEdit) {
    http_response_code(403);
    $projectId = $_GET['project_id'] ?? '';
    if (ctype_digit((string)$projectId)) {
      set_flash('error', 'You are not allowed to remove project resources.');
      header("Location: ../views/project/projects.php?tab=planning&project_id=$projectId&open_modal=planningBoardSwitchForm&open_modal_title=Choose%20Planning%20Section&planning_section=capacity-planning"); exit;
    }
    header("Location: ../views/project/projects.php"); exit;
  }

  $id = $_GET['delete_resource'];
  $projectId = $_GET['project_id'] ?? '';
  if (!ctype_digit((string)$id) || !ctype_digit((string)$projectId)) {
    header("Location: ../views/project/projects.php"); exit;
  }

  $res->delete((int)$id);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Deleted project resource (ID: $id)"]);

  set_flash('success', 'Resource removed successfully.');
  header("Location: ../views/project/projects.php?tab=planning&project_id=$projectId&open_modal=planningBoardSwitchForm&open_modal_title=Choose%20Planning%20Section&planning_section=capacity-planning"); exit;
}

header("Location: ../views/project/projects.php"); exit;


