<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../models/Fleet.php";

requireLogin();
requireRole(['admin','manager','project']);


$fleet = new Fleet($pdo);

/* ADD */
if (isset($_POST['add'])) {
    $vehicle = trim($_POST['vehicle_name'] ?? '');
    $plate   = trim($_POST['plate_number'] ?? '');
    $status  = $_POST['status'] ?? 'Available';

    if ($vehicle === '' || $plate === '') {
        set_flash('error', 'Vehicle name and plate number are required.');
        header("Location: ../views/fleet.php");
        exit;
    }

    $fleet->create($vehicle, $plate, $status);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Added fleet vehicle ($plate)"]);

    set_flash('success', 'Vehicle added successfully.');
    header("Location: ../views/fleet.php");
    exit;
}

/* UPDATE */
if (isset($_POST['update'])) {
    $id      = $_POST['id'] ?? '';
    $vehicle = trim($_POST['vehicle_name'] ?? '');
    $plate   = trim($_POST['plate_number'] ?? '');
    $status  = $_POST['status'] ?? 'Available';

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid vehicle ID.');
        header("Location: ../views/fleet.php");
        exit;
    }

    $fleet->update((int)$id, $vehicle, $plate, $status);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Updated fleet vehicle ($plate)"]);

    set_flash('success', 'Vehicle updated successfully.');
    header("Location: ../views/fleet.php");
    exit;
}

/* DELETE */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid vehicle ID.');
        header("Location: ../views/fleet.php");
        exit;
    }

    $fleet->delete((int)$id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Deleted fleet vehicle (ID: $id)"]);

    set_flash('success', 'Vehicle deleted successfully.');
    header("Location: ../views/fleet.php");
    exit;
}

header("Location: ../views/fleet.php");
exit;
