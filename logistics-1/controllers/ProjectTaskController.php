<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../models/ProjectTask.php";

requireLogin();
requireRole(['admin','manager','project']);

$task = new ProjectTask($pdo);

if (isset($_POST['add_task'])) {
  $projectId = $_POST['project_id'] ?? '';
  if (!ctype_digit((string)$projectId)) { header("Location: ../views/projects.php"); exit; }

  $title = trim($_POST['title'] ?? '');
  if ($title === '') {
    set_flash('error', 'Task title is required.');
    header("Location: ../views/project_view.php?id=".$projectId); exit;
  }

  $task->create($_POST);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Added project task to project ID $projectId"]);

  set_flash('success', 'Task added.');
  header("Location: ../views/project_view.php?id=".$projectId); exit;
}

if (isset($_GET['delete_task'], $_GET['project_id']) && ctype_digit($_GET['delete_task']) && ctype_digit($_GET['project_id'])) {
  $task->delete((int)$_GET['delete_task']);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Deleted project task ID ".$_GET['delete_task']]);

  set_flash('success', 'Task deleted.');
  header("Location: ../views/project_view.php?id=".$_GET['project_id']); exit;
}

header("Location: ../views/projects.php");
exit;
