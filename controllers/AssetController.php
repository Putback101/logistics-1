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

function generate_next_asset_tag(PDO $pdo): string {
  $maxNo = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(asset_tag, 7) AS UNSIGNED)), 0) AS max_no FROM assets WHERE asset_tag REGEXP '^ASSET-[0-9]+$'")->fetchColumn();
  $next = max(1, $maxNo + 1);

  do {
    $tag = 'ASSET-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    $chk = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE asset_tag = ?");
    $chk->execute([$tag]);
    $exists = (int)$chk->fetchColumn() > 0;
    $next++;
  } while ($exists);

  return $tag;
}

if (isset($_POST['add_asset'])) {
  deny_if_no_permission($canAdd, 'You are not allowed to add assets.');

  $tag = trim($_POST['asset_tag'] ?? '');
  $name = trim($_POST['asset_name'] ?? '');
  $cat = trim($_POST['asset_category'] ?? '');

  if ($name === '' || $cat === '') {
    set_flash('error', 'Asset Name and Category are required.');
    header("Location: ../views/asset/asset.php?tab=registry"); exit;
  }

  if ($tag === '') {
    $tag = generate_next_asset_tag($pdo);
  }
  $_POST['asset_tag'] = $tag;

  $sourceInventoryId = isset($_POST['source_inventory_id']) && ctype_digit((string)$_POST['source_inventory_id'])
    ? (int)$_POST['source_inventory_id']
    : 0;
  $consumeFromInventory = isset($_POST['consume_from_inventory']) && $_POST['consume_from_inventory'] === '1';
  $consumeQty = max(1, (int)($_POST['consume_qty'] ?? 1));

  try {
    $pdo->beginTransaction();

    if ($sourceInventoryId > 0 && $consumeFromInventory) {
      $q = $pdo->prepare("SELECT i.id, i.item_name, i.stock, i.location, it.category AS item_category, it.unit AS item_unit FROM inventory i LEFT JOIN items it ON it.id = i.item_id WHERE i.id = ? LIMIT 1 FOR UPDATE");
      $q->execute([$sourceInventoryId]);
      $inv = $q->fetch(PDO::FETCH_ASSOC);

      if (!$inv) {
        throw new RuntimeException('Selected warehouse item no longer exists.');
      }
      $stockNow = (int)($inv['stock'] ?? 0);
      if ($stockNow < $consumeQty) {
        throw new RuntimeException('Insufficient warehouse stock for this conversion.');
      }

      $u = $pdo->prepare("UPDATE inventory SET stock = stock - ? WHERE id = ?");
      $u->execute([$consumeQty, $sourceInventoryId]);

      if (trim((string)($_POST['location'] ?? '')) === '') {
        $_POST['location'] = (string)($inv['location'] ?? 'Main Warehouse');
      }
      if (trim((string)($_POST['asset_name'] ?? '')) === '') {
        $_POST['asset_name'] = (string)($inv['item_name'] ?? 'Warehouse Item');
      }
      if (trim((string)($_POST['asset_category'] ?? '')) === '') {
        $_POST['asset_category'] = trim((string)($inv['item_category'] ?? 'Warehouse Item'));
      }
      if (trim((string)($_POST['notes'] ?? '')) === '') {
        $_POST['notes'] = 'Converted from warehouse item #' . (int)$sourceInventoryId;
      }
    }

    $name = trim((string)($_POST['asset_name'] ?? $name));
    $cat = trim((string)($_POST['asset_category'] ?? $cat));
    $newId = $asset->create($_POST);

    $action = "Added asset: $tag - $name";
    if ($sourceInventoryId > 0) {
      $action .= " (linked to warehouse item ID $sourceInventoryId";
      if ($consumeFromInventory) {
        $action .= ", consumed qty $consumeQty";
      }
      $action .= ")";
    }

    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?,?,?,?)")
        ->execute([$_SESSION['user']['id'], $action, 'assets', $newId]);

    $pdo->commit();
    set_flash('success', 'Asset added successfully.' . ($sourceInventoryId > 0 ? ' Warehouse link saved.' : ''));
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    set_flash('error', 'Failed to add asset. ' . $e->getMessage());
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

    $m->createAsset($assetId, 'Maintenance', $desc, 0, null, $_SESSION['user']['id']);
  }

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Added monitor log for asset ID: $assetId"]);

  set_flash('success', 'Monitoring log added.');
  header("Location: ../views/asset/asset.php?tab=monitoring"); exit;
}

header("Location: ../views/asset/asset.php?tab=registry");
exit;
