<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../models/User.php";

requireLogin();
requireRole(['admin']);
$allowedRoles = ['admin','manager','procurement','warehouse','mro','asset','project','staff'];
if (!in_array($_POST['role'] ?? '', $allowedRoles, true)) {
    set_flash('error', 'Invalid role.');
    header("Location: ../views/users.php");
    exit;
}

$user = new User($pdo);

// ADD USER
if (isset($_POST['add'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'staff';

    if ($fullname === '' || $email === '' || $password === '') {
        set_flash('error', 'Please fill out all fields.');
        header("Location: ../views/users.php");
        exit;
    }

    try {
        $user->create($fullname, $email, $password, $role);

        $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
            ->execute([$_SESSION['user']['id'], "Created user account ($email)"]);

        set_flash('success', 'User account created successfully.');
    } catch (PDOException $e) {
        set_flash('error', 'Email already exists. Try another.');
    }

    header("Location: ../views/users.php");
    exit;
}

// UPDATE USER
if (isset($_POST['update'])) {
    $id       = $_POST['id'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'staff';

    if (!ctype_digit((string)$id) || $fullname === '' || $email === '') {
        set_flash('error', 'Invalid data. Please try again.');
        header("Location: ../views/users.php");
        exit;
    }

    try {
        $user->update((int)$id, $fullname, $email, $role);

        $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
            ->execute([$_SESSION['user']['id'], "Updated user account ($email)"]);

        set_flash('success', 'User account updated successfully.');
    } catch (PDOException $e) {
        set_flash('error', 'Email already exists. Try another.');
    }

    header("Location: ../views/users.php");
    exit;
}

// DELETE USER
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid user ID.');
        header("Location: ../views/users.php");
        exit;
    }

    if ((int)$id === (int)$_SESSION['user']['id']) {
        set_flash('error', 'You cannot delete your own account.');
        header("Location: ../views/users.php");
        exit;
    }

    $user->delete((int)$id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Deleted user account (ID: $id)"]);

    set_flash('success', 'User account deleted successfully.');
    header("Location: ../views/users.php");
    exit;
}

header("Location: ../views/users.php");
exit;
