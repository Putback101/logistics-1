<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Project.php";

requireLogin();
requireRole(['admin','manager','project_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'projects', 'add');
$canEdit = hasPermission($userRole, 'projects', 'edit');
$canDelete = hasPermission($userRole, 'projects', 'delete');

$project = new Project($pdo);

if (isset($_POST['add'])) {
  if (!$canAdd) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to add projects.');
    header("Location: ../views/project/projects.php"); exit;
  }

  $name = trim($_POST['name'] ?? '');
  $type = $_POST['type'] ?? 'General';
  $desc = trim($_POST['description'] ?? '');
  $start = $_POST['start_date'] ?? null;
  $end = $_POST['end_date'] ?? null;
  $status = $_POST['status'] ?? 'Planned';

  if ($name === '') {
    set_flash('error', 'Project name is required.');
    header("Location: ../views/project/projects.php"); exit;
  }

  $project->create($name,$type,$desc,$start,$end,$status,$_SESSION['user']['id']);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Created project ($name)"]);

  set_flash('success', 'Project created successfully.');
  header("Location: ../views/project/projects.php"); exit;
}

if (isset($_POST['update'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to edit projects.');
    header("Location: ../views/project/projects.php"); exit;
  }

  $id = $_POST['id'] ?? '';
  if (!ctype_digit((string)$id)) { header("Location: ../views/project/projects.php"); exit; }

  $name = trim($_POST['name'] ?? '');
  $type = $_POST['type'] ?? 'General';
  $desc = trim($_POST['description'] ?? '');
  $start = $_POST['start_date'] ?? null;
  $end = $_POST['end_date'] ?? null;
  $status = $_POST['status'] ?? 'Planned';

  $project->update((int)$id,$name,$type,$desc,$start,$end,$status);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Updated project ($name)"]);

  set_flash('success', 'Project updated successfully.');
  header("Location: ../views/project/projects.php"); exit;
}

if (isset($_GET['delete'])) {
  if (!$canDelete) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to delete projects.');
    header("Location: ../views/project/projects.php"); exit;
  }

  $id = $_GET['delete'];
  if (!ctype_digit((string)$id)) { header("Location: ../views/project/projects.php"); exit; }

  $project->delete((int)$id);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Deleted project (ID: $id)"]);

  set_flash('success', 'Project deleted successfully.');
  header("Location: ../views/project/projects.php"); exit;
}

header("Location: ../views/project/projects.php"); exit;

