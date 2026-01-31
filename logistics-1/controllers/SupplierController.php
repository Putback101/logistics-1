<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/flash.php";
require_once __DIR__ . "/../models/Supplier.php";

requireLogin();

$role = $_SESSION['user']['role'] ?? 'guest';
$isAdmin = ($role === 'admin');
$canAdd = in_array($role, ['admin','manager'], true);

$s = new Supplier($pdo);

// ADD (admin/manager)
if (isset($_POST['add_supplier'])) {
  if (!$canAdd) { http_response_code(403); exit; }

  $name = trim($_POST['name'] ?? '');
  $contact = trim($_POST['contact_person'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');

  if ($name === '') {
    set_flash('error', 'Supplier name is required.');
    header("Location: ../views/suppliers.php"); exit;
  }

  $s->create($name, $contact, $email, $phone);
  set_flash('success', 'Supplier added.');
  header("Location: ../views/suppliers.php"); exit;
}

// UPDATE (admin only)
if (isset($_POST['update_supplier'])) {
  if (!$isAdmin) { http_response_code(403); exit; }

  $id = $_POST['id'] ?? '';
  $name = trim($_POST['name'] ?? '');
  $contact = trim($_POST['contact_person'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');

  if (!ctype_digit((string)$id) || $name === '') {
    set_flash('error', 'Invalid supplier data.');
    header("Location: ../views/suppliers.php"); exit;
  }

  $s->update((int)$id, $name, $contact, $email, $phone);
  set_flash('success', 'Supplier updated.');
  header("Location: ../views/suppliers.php"); exit;
}

// DELETE (admin only)
if (isset($_POST['delete_supplier'])) {
  if (!$isAdmin) { http_response_code(403); exit; }

  $id = $_POST['id'] ?? '';
  if (!ctype_digit((string)$id)) {
    set_flash('error', 'Invalid supplier.');
    header("Location: ../views/suppliers.php"); exit;
  }

  // Optional safety: prevent delete if used in purchase_orders
  if ($s->isUsedInPurchaseOrders((int)$id)) {
    set_flash('error', 'Cannot delete supplier: it is used in purchase orders.');
    header("Location: ../views/suppliers.php"); exit;
  }

  $s->delete((int)$id);
  set_flash('success', 'Supplier deleted.');
  header("Location: ../views/suppliers.php"); exit;
}

header("Location: ../views/suppliers.php");
exit;
