<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/ProjectTask.php";

requireLogin();
requireRole(['admin','manager','project_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canEdit = hasPermission($userRole, 'projects', 'edit');

$task = new ProjectTask($pdo);

if (isset($_POST['add_task'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to add project tasks.');
    header("Location: ../views/project/projects.php"); exit;
  }

  $projectId = $_POST['project_id'] ?? '';
  if (!ctype_digit((string)$projectId)) { header("Location: ../views/project/projects.php"); exit; }

  $title = trim($_POST['title'] ?? '');
  if ($title === '') {
    set_flash('error', 'Task title is required.');
    header("Location: ../views/project/projects.php?tab=planning&project_id=".$projectId."&open_modal=planningBoardSwitchForm&open_modal_title=Choose%20Planning%20Section&planning_section=work-breakdown"); exit;
  }

  $task->create($_POST);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Added project task to project ID $projectId"]);

  set_flash('success', 'Task added.');
  header("Location: ../views/project/projects.php?tab=planning&project_id=".$projectId."&open_modal=planningBoardSwitchForm&open_modal_title=Choose%20Planning%20Section&planning_section=work-breakdown"); exit;
}

if (isset($_GET['delete_task'], $_GET['project_id']) && ctype_digit($_GET['delete_task']) && ctype_digit($_GET['project_id'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to delete project tasks.');
    header("Location: ../views/project/projects.php?tab=planning&project_id=".$_GET['project_id']."&open_modal=planningBoardSwitchForm&open_modal_title=Choose%20Planning%20Section&planning_section=work-breakdown"); exit;
  }

  $task->delete((int)$_GET['delete_task']);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Deleted project task ID ".$_GET['delete_task']]);

  set_flash('success', 'Task deleted.');
  header("Location: ../views/project/projects.php?tab=planning&project_id=".$_GET['project_id']."&open_modal=planningBoardSwitchForm&open_modal_title=Choose%20Planning%20Section&planning_section=work-breakdown"); exit;
}

header("Location: ../views/project/projects.php");
exit;


