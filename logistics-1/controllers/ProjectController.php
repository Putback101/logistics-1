<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../models/Project.php";

requireLogin();
requireRole(['admin','manager','project']);


$project = new Project($pdo);

if (isset($_POST['add'])) {
  $name = trim($_POST['name'] ?? '');
  $type = $_POST['type'] ?? 'General';
  $desc = trim($_POST['description'] ?? '');
  $start = $_POST['start_date'] ?? null;
  $end = $_POST['end_date'] ?? null;
  $status = $_POST['status'] ?? 'Planned';

  if ($name === '') {
    set_flash('error', 'Project name is required.');
    header("Location: ../views/projects.php"); exit;
  }

  $project->create($name,$type,$desc,$start,$end,$status,$_SESSION['user']['id']);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Created project ($name)"]);

  set_flash('success', 'Project created successfully.');
  header("Location: ../views/projects.php"); exit;
}

if (isset($_POST['update'])) {
  $id = $_POST['id'] ?? '';
  if (!ctype_digit((string)$id)) { header("Location: ../views/projects.php"); exit; }

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
  header("Location: ../views/projects.php"); exit;
}

if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  if (!ctype_digit((string)$id)) { header("Location: ../views/projects.php"); exit; }

  $project->delete((int)$id);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Deleted project (ID: $id)"]);

  set_flash('success', 'Project deleted successfully.');
  header("Location: ../views/projects.php"); exit;
}

header("Location: ../views/projects.php"); exit;
