<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Maintenance.php";

requireLogin();
requireRole(['admin','manager','mro_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'mro', 'add');

$m = new Maintenance($pdo);

if (isset($_POST['add'])) {
  if (!$canAdd) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to add maintenance records.');
    header("Location: ../views/mro/maintenance.php"); exit;
  }

  $fleetId = $_POST['fleet_id'] ?? '';
  $assetId = $_POST['asset_id'] ?? '';
  $type = $_POST['type'] ?? 'Maintenance';
  $desc = trim($_POST['description'] ?? '');
  $cost = (float)($_POST['cost'] ?? 0);
  $date = $_POST['performed_at'] ?? null;

  $hasFleet = ctype_digit((string)$fleetId);
  $hasAsset = ctype_digit((string)$assetId);

  if ((!$hasFleet && !$hasAsset) || $desc === '') {
    set_flash('error', 'Select a Fleet OR an Asset, and provide a description.');
    header("Location: ../views/mro/maintenance.php"); exit;
  }

  if ($hasFleet) {
    $requestRef = 'MRO-' . date('Ymd-His') . '-' . random_int(100, 999);
    $m->createFleet((int)$fleetId, $type, $desc, $cost, $date, $_SESSION['user']['id'], $requestRef, 'Normal', 'mro', 'logistics-1', $requestRef, null);
    $pdo->prepare("UPDATE fleet SET status='Maintenance' WHERE id=?")->execute([(int)$fleetId]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Logged $type for fleet ID $fleetId"]);
  } else {
    $requestRef = 'MRO-' . date('Ymd-His') . '-' . random_int(100, 999);
    $m->createAsset((int)$assetId, $type, $desc, $cost, $date, $_SESSION['user']['id'], $requestRef, 'Normal', 'mro', 'logistics-1', $requestRef, null);
    $pdo->prepare("UPDATE assets SET status='Under Maintenance' WHERE id=?")->execute([(int)$assetId]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Logged $type for asset ID $assetId"]);
  }

  set_flash('success', 'Maintenance/Repair logged successfully.');
  header("Location: ../views/mro/maintenance.php"); exit;
}

header("Location: ../views/mro/maintenance.php");
exit;

