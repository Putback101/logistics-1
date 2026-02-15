<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Asset.php";

requireLogin();
requireRole(['admin','manager','asset']);

$role = $_SESSION['user']['role'] ?? 'guest';
$canAdd = hasPermission($role, 'assets', 'add');
$canEdit = hasPermission($role, 'assets', 'edit');
$canDelete = hasPermission($role, 'assets', 'delete');

$asset = new Asset($pdo);

function deny_if_no_permission(bool $allowed, string $msg): void {
  if (!$allowed) {
    http_response_code(403);
    set_flash('error', $msg);
    header("Location: ../views/asset/asset.php?tab=registry");
    exit;
  }
}

if (isset($_POST['add_asset'])) {
  deny_if_no_permission($canAdd, 'You are not allowed to add assets.');

  $tag = trim($_POST['asset_tag'] ?? '');
  $name = trim($_POST['asset_name'] ?? '');
  $cat = trim($_POST['asset_category'] ?? '');

  if ($tag === '' || $name === '' || $cat === '') {
    set_flash('error', 'Asset Tag, Name, and Category are required.');
    header("Location: ../views/asset/asset.php?tab=registry"); exit;
  }

  try {
    $asset->create($_POST);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Added asset: $tag - $name"]);
    set_flash('success', 'Asset added successfully.');
  } catch (Throwable $e) {
    set_flash('error', 'Failed to add asset. (Asset Tag must be unique)');
  }

  header("Location: ../views/asset/asset.php?tab=registry"); exit;
}

if (isset($_POST['update_asset'])) {
  deny_if_no_permission($canEdit, 'You are not allowed to edit assets.');

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    set_flash('error', 'Invalid asset.');
    header("Location: ../views/asset/asset.php?tab=registry"); exit;
  }

  try {
    $asset->update($id, $_POST);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Updated asset ID: $id"]);
    set_flash('success', 'Asset updated successfully.');
  } catch (Throwable $e) {
    set_flash('error', 'Failed to update asset. (Asset Tag must be unique)');
  }

  header("Location: ../views/asset/asset.php?tab=registry"); exit;
}

if (isset($_POST['delete_asset'])) {
  deny_if_no_permission($canDelete, 'You are not allowed to delete assets.');

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    set_flash('error', 'Invalid asset.');
    header("Location: ../views/asset/asset.php?tab=registry"); exit;
  }

  $asset->delete($id);
  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Deleted asset ID: $id"]);

  set_flash('success', 'Asset deleted.');
  header("Location: ../views/asset/asset.php?tab=registry"); exit;
}

if (isset($_POST['transfer_asset'])) {
  deny_if_no_permission($canEdit, 'You are not allowed to transfer assets.');

  $assetId = (int)($_POST['asset_id'] ?? 0);
  if ($assetId <= 0) {
    set_flash('error', 'Invalid asset.');
    header("Location: ../views/asset/asset.php?tab=tracking&asset_id=$assetId"); exit;
  }

  $_POST['moved_by'] = $_SESSION['user']['id'];

  $asset->recordMovement($_POST);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Recorded movement for asset ID: $assetId"]);

  set_flash('success', 'Movement recorded successfully.');
  header("Location: ../views/asset/asset.php?tab=tracking"); exit;
}

if (isset($_POST['add_monitor'])) {
  deny_if_no_permission($canEdit, 'You are not allowed to add monitor logs.');

  $assetId = (int)($_POST['asset_id'] ?? 0);
  if ($assetId <= 0) {
    set_flash('error', 'Invalid asset.');
    header("Location: ../views/asset/asset.php?tab=monitoring&asset_id=$assetId"); exit;
  }

  $_POST['recorded_by'] = $_SESSION['user']['id'];
  $asset->addMonitorLog($_POST);

  if (($_POST['condition_status'] ?? '') === 'Needs Maintenance') {
    require_once __DIR__ . "/../models/Maintenance.php";
    $m = new Maintenance($pdo);

    $assetId = (int)$_POST['asset_id'];
    $desc = "AUTO REQUEST: Asset marked as Needs Maintenance. " . trim($_POST['remarks'] ?? '');

    // performed_at NULL = pending/request
    $m->createAsset($assetId, 'Maintenance', $desc, 0, null, $_SESSION['user']['id']);
  }

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Added monitor log for asset ID: $assetId"]);

  set_flash('success', 'Monitoring log added.');
  header("Location: ../views/asset/asset.php?tab=monitoring"); exit;
}

header("Location: ../views/asset/asset.php?tab=registry");
exit;

