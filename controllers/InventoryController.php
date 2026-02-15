<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Inventory.php";

requireLogin();
requireRole(['admin','manager','warehouse_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'warehousing', 'add');
$canEdit = hasPermission($userRole, 'warehousing', 'edit');
$canDelete = hasPermission($userRole, 'warehousing', 'delete');

$inventory = new Inventory($pdo);

// ADD
if (isset($_POST['add'])) {
    if (!$canAdd) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to add inventory items.');
        header("Location: ../views/warehousing/inventory.php");
        exit;
    }

    $item     = trim($_POST['item_name'] ?? '');
    $stock    = (int)($_POST['stock'] ?? -1);
    $location = trim($_POST['location'] ?? '');

    if ($item === '' || $stock < 0 || $location === '') {
        set_flash('error', 'Please fill out all fields correctly.');
        header("Location: ../views/warehousing/inventory.php");
        exit;
    }

    $inventory->create($item, $stock, $location);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Added inventory item ($item)"]);

    set_flash('success', 'Inventory item added successfully.');
    header("Location: ../views/warehousing/inventory.php");
    exit;
}

// UPDATE
if (isset($_POST['update'])) {
    if (!$canEdit) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to edit inventory items.');
        header("Location: ../views/warehousing/inventory.php");
        exit;
    }

    $id       = $_POST['id'] ?? '';
    $item     = trim($_POST['item_name'] ?? '');
    $stock    = (int)($_POST['stock'] ?? -1);
    $location = trim($_POST['location'] ?? '');

    if (!ctype_digit((string)$id) || $item === '' || $stock < 0 || $location === '') {
        set_flash('error', 'Invalid data. Please try again.');
        header("Location: ../views/warehousing/inventory.php");
        exit;
    }

    $inventory->update((int)$id, $item, $stock, $location);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Updated inventory item ($item)"]);

    set_flash('success', 'Inventory item updated successfully.');
    header("Location: ../views/warehousing/inventory.php");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    if (!$canDelete) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to delete inventory items.');
        header("Location: ../views/warehousing/inventory.php");
        exit;
    }

    $id = $_GET['delete'];

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid item ID.');
        header("Location: ../views/warehousing/inventory.php");
        exit;
    }

    $inventory->delete((int)$id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Deleted inventory item (ID: $id)"]);

    set_flash('success', 'Inventory item deleted successfully.');
    header("Location: ../views/warehousing/inventory.php");
    exit;
}

header("Location: ../views/warehousing/inventory.php");
exit;

