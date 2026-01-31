<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../models/Inventory.php";

requireLogin();
requireRole(['admin','manager','warehouse']);


$inventory = new Inventory($pdo);

// ADD
if (isset($_POST['add'])) {
    $item     = trim($_POST['item_name'] ?? '');
    $stock    = (int)($_POST['stock'] ?? -1);
    $location = trim($_POST['location'] ?? '');

    if ($item === '' || $stock < 0 || $location === '') {
        set_flash('error', 'Please fill out all fields correctly.');
        header("Location: ../views/inventory.php");
        exit;
    }

    $inventory->create($item, $stock, $location);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Added inventory item ($item)"]);

    set_flash('success', 'Inventory item added successfully.');
    header("Location: ../views/inventory.php");
    exit;
}

// UPDATE
if (isset($_POST['update'])) {
    $id       = $_POST['id'] ?? '';
    $item     = trim($_POST['item_name'] ?? '');
    $stock    = (int)($_POST['stock'] ?? -1);
    $location = trim($_POST['location'] ?? '');

    if (!ctype_digit((string)$id) || $item === '' || $stock < 0 || $location === '') {
        set_flash('error', 'Invalid data. Please try again.');
        header("Location: ../views/inventory.php");
        exit;
    }

    $inventory->update((int)$id, $item, $stock, $location);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Updated inventory item ($item)"]);

    set_flash('success', 'Inventory item updated successfully.');
    header("Location: ../views/inventory.php");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid item ID.');
        header("Location: ../views/inventory.php");
        exit;
    }

    $inventory->delete((int)$id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Deleted inventory item (ID: $id)"]);

    set_flash('success', 'Inventory item deleted successfully.');
    header("Location: ../views/inventory.php");
    exit;
}

header("Location: ../views/inventory.php");
exit;
