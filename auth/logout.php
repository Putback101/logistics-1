<?php
session_start();
require "../config/flash.php";
require "../config/app.php";

$base = app_base_url();

// keep name for goodbye (optional)
$name = $_SESSION['user']['fullname'] ?? '';

// destroy session
$_SESSION = [];
session_destroy();

if ($name !== '') {
    // Start session again just for flash message
    session_start();
    set_flash('success', "Logged out successfully.");
}

header("Location: $base/auth/login.php");
exit;
