<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Fleet.php";

requireLogin();
requireRole(['admin','manager','project_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'projects', 'add');
$canEdit = hasPermission($userRole, 'projects', 'edit');
$canDelete = hasPermission($userRole, 'projects', 'delete');

$fleet = new Fleet($pdo);

/* ADD */
if (isset($_POST['add'])) {
    if (!$canAdd) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to add fleet vehicles.');
        header("Location: ../views/project/fleet.php");
        exit;
    }

    $vehicle = trim($_POST['vehicle_name'] ?? '');
    $plate   = trim($_POST['plate_number'] ?? '');
    $status  = $_POST['status'] ?? 'Available';

    if ($vehicle === '' || $plate === '') {
        set_flash('error', 'Vehicle name and plate number are required.');
        header("Location: ../views/project/fleet.php");
        exit;
    }

    $fleet->create($vehicle, $plate, $status);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Added fleet vehicle ($plate)"]);

    set_flash('success', 'Vehicle added successfully.');
    header("Location: ../views/project/fleet.php");
    exit;
}

/* UPDATE */
if (isset($_POST['update'])) {
    if (!$canEdit) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to edit fleet vehicles.');
        header("Location: ../views/project/fleet.php");
        exit;
    }

    $id      = $_POST['id'] ?? '';
    $vehicle = trim($_POST['vehicle_name'] ?? '');
    $plate   = trim($_POST['plate_number'] ?? '');
    $status  = $_POST['status'] ?? 'Available';

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid vehicle ID.');
        header("Location: ../views/project/fleet.php");
        exit;
    }

    $fleet->update((int)$id, $vehicle, $plate, $status);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Updated fleet vehicle ($plate)"]);

    set_flash('success', 'Vehicle updated successfully.');
    header("Location: ../views/project/fleet.php");
    exit;
}

/* DELETE */
if (isset($_GET['delete'])) {
    if (!$canDelete) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to delete fleet vehicles.');
        header("Location: ../views/project/fleet.php");
        exit;
    }

    $id = $_GET['delete'];

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid vehicle ID.');
        header("Location: ../views/project/fleet.php");
        exit;
    }

    $fleet->delete((int)$id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Deleted fleet vehicle (ID: $id)"]);

    set_flash('success', 'Vehicle deleted successfully.');
    header("Location: ../views/project/fleet.php");
    exit;
}

header("Location: ../views/project/fleet.php");
exit;

