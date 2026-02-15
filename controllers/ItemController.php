<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/flash.php";
require_once __DIR__ . "/../config/permissions.php";
require_once __DIR__ . "/../models/Item.php";

requireLogin();
requireRole(['admin','manager','procurement_staff']);

$role = $_SESSION['user']['role'] ?? 'guest';
$canEdit = hasPermission($role, 'procurement', 'edit');
$canDelete = hasPermission($role, 'procurement', 'delete');

$itemModel = new Item($pdo);

if (isset($_POST['add_item_master']) || isset($_POST['add_item'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to add items.');
    header("Location: ../views/procurement/procurement.php?tab=items"); exit;
  }

  $itemName = trim($_POST['item_name'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $unit = trim($_POST['unit'] ?? '');

  if ($itemName === '') {
    set_flash('error', 'Item name is required.');
    header("Location: ../views/procurement/procurement.php?tab=items"); exit;
  }

  $itemModel->create($itemName, $category, $unit);
  set_flash('success', 'Item added.');
  header("Location: ../views/procurement/procurement.php?tab=items"); exit;
}

if (isset($_POST['update_item_master']) || isset($_POST['update_item'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to update items.');
    header("Location: ../views/procurement/procurement.php?tab=items"); exit;
  }

  $id = $_POST['id'] ?? '';
  $itemName = trim($_POST['item_name'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $unit = trim($_POST['unit'] ?? '');

  if (!ctype_digit((string)$id) || $itemName === '') {
    set_flash('error', 'Invalid item data.');
    header("Location: ../views/procurement/procurement.php?tab=items"); exit;
  }

  $itemModel->update((int)$id, $itemName, $category, $unit);
  set_flash('success', 'Item updated.');
  header("Location: ../views/procurement/procurement.php?tab=items"); exit;
}

if (isset($_POST['delete_item_master']) || isset($_POST['delete_item'])) {
  if (!$canDelete) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to delete items.');
    header("Location: ../views/procurement/procurement.php?tab=items"); exit;
  }

  $id = $_POST['id'] ?? '';
  if (!ctype_digit((string)$id)) {
    set_flash('error', 'Invalid item.');
    header("Location: ../views/procurement/procurement.php?tab=items"); exit;
  }

  if ($itemModel->isUsed((int)$id)) {
    set_flash('error', 'Cannot delete item: it is already used in requests or POs.');
    header("Location: ../views/procurement/procurement.php?tab=items"); exit;
  }

  $itemModel->delete((int)$id);
  set_flash('success', 'Item deleted.');
  header("Location: ../views/procurement/procurement.php?tab=items"); exit;
}

header("Location: ../views/procurement/procurement.php?tab=items");
exit;
