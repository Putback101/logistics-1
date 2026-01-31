<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../models/ProjectResource.php";

requireLogin();
requireRole(['admin','manager','project']);


$res = new ProjectResource($pdo);

if (isset($_POST['add_resource'])) {
  $projectId = $_POST['project_id'] ?? '';
  $type = $_POST['resource_type'] ?? 'User';
  $resourceId = $_POST['resource_id'] ?? '';
  $roleLabel = trim($_POST['role_label'] ?? '');
  $from = $_POST['allocated_from'] ?? null;
  $to   = $_POST['allocated_to'] ?? null;
  $notes= trim($_POST['notes'] ?? '');

  if (!ctype_digit((string)$projectId) || !ctype_digit((string)$resourceId)) {
    set_flash('error', 'Invalid resource data.');
    header("Location: ../views/projects.php"); exit;
  }

  $res->create((int)$projectId,$type,(int)$resourceId,$roleLabel,$from,$to,$notes);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Added resource ($type:$resourceId) to project ID $projectId"]);

  set_flash('success', 'Resource allocated successfully.');
  header("Location: ../views/project_view.php?id=$projectId"); exit;
}

if (isset($_GET['delete_resource'])) {
  $id = $_GET['delete_resource'];
  $projectId = $_GET['project_id'] ?? '';
  if (!ctype_digit((string)$id) || !ctype_digit((string)$projectId)) {
    header("Location: ../views/projects.php"); exit;
  }

  $res->delete((int)$id);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Deleted project resource (ID: $id)"]);

  set_flash('success', 'Resource removed successfully.');
  header("Location: ../views/project_view.php?id=$projectId"); exit;
}

header("Location: ../views/projects.php"); exit;
