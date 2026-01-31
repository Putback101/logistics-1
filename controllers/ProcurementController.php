<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../models/Procurement.php";

requireLogin();
requireRole(['admin','manager','procurement']);


$procurement = new Procurement($pdo);

/* ADD */
if (isset($_POST['add'])) {
    $item     = trim($_POST['item_name'] ?? '');
    $qty      = (int)($_POST['quantity'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');
    $status   = $_POST['status'] ?? 'Pending';

    if ($item === '' || $qty <= 0 || $supplier === '') {
        set_flash('error', 'Please fill out all fields correctly.');
        header("Location: ../views/procurement.php");
        exit;
    }

    $procurement->create($item, $qty, $supplier, $status);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Added procurement request ($item)"]);

    set_flash('success', 'Procurement request added successfully.');
    header("Location: ../views/procurement.php");
    exit;
}

/* UPDATE */
if (isset($_POST['update'])) {
    $id       = $_POST['id'] ?? '';
    $item     = trim($_POST['item_name'] ?? '');
    $qty      = (int)($_POST['quantity'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');
    $status   = $_POST['status'] ?? 'Pending';

    if (!ctype_digit((string)$id) || $item === '' || $qty <= 0 || $supplier === '') {
        set_flash('error', 'Invalid data. Please try again.');
        header("Location: ../views/procurement.php");
        exit;
    }

    $procurement->update((int)$id, $item, $qty, $supplier, $status);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Updated procurement request ($item)"]);

    set_flash('success', 'Procurement updated successfully.');
    header("Location: ../views/procurement.php");
    exit;
}

/* DELETE */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid request ID.');
        header("Location: ../views/procurement.php");
        exit;
    }

    $procurement->delete((int)$id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Deleted procurement request (ID: $id)"]);

    set_flash('success', 'Procurement deleted successfully.');
    header("Location: ../views/procurement.php");
    exit;
}

header("Location: ../views/procurement.php");
exit;
