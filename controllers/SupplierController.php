<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/flash.php";
require_once __DIR__ . "/../config/permissions.php";
require_once __DIR__ . "/../models/Supplier.php";

requireLogin();
requireRole(['admin','manager','procurement_staff']);

$role = $_SESSION['user']['role'] ?? 'guest';
$canEdit = hasPermission($role, 'procurement', 'edit');
$canDelete = hasPermission($role, 'procurement', 'delete');

$s = new Supplier($pdo);

if (isset($_POST['add_supplier']) || isset($_POST['add'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to add suppliers.');
    header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
  }

  $name = trim($_POST['name'] ?? '');
  $contact = trim($_POST['contact_person'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');

  if ($name === '') {
    set_flash('error', 'Supplier name is required.');
    header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
  }

  $s->create($name, $contact, $email, $phone);
  set_flash('success', 'Supplier added.');
  header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
}

if (isset($_POST['update_supplier'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to update suppliers.');
    header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
  }

  $id = $_POST['id'] ?? '';
  $name = trim($_POST['name'] ?? '');
  $contact = trim($_POST['contact_person'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');

  if (!ctype_digit((string)$id) || $name === '') {
    set_flash('error', 'Invalid supplier data.');
    header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
  }

  $s->update((int)$id, $name, $contact, $email, $phone);
  set_flash('success', 'Supplier updated.');
  header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
}

if (isset($_POST['delete_supplier'])) {
  if (!$canDelete) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to delete suppliers.');
    header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
  }

  $id = $_POST['id'] ?? '';
  if (!ctype_digit((string)$id)) {
    set_flash('error', 'Invalid supplier.');
    header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
  }

  if ($s->isUsedInPurchaseOrders((int)$id)) {
    set_flash('error', 'Cannot delete supplier: it is used in purchase orders.');
    header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
  }

  $s->delete((int)$id);
  set_flash('success', 'Supplier deleted.');
  header("Location: ../views/procurement/procurement.php?tab=suppliers"); exit;
}

header("Location: ../views/procurement/procurement.php?tab=suppliers");
exit;


