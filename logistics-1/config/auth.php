<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function base_url(): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (str_ends_with($dir, '/views')) $dir = substr($dir, 0, -5);
    if (str_ends_with($dir, '/auth')) $dir = substr($dir, 0, -5);
    if (str_ends_with($dir, '/controllers')) $dir = substr($dir, 0, -12);
    return rtrim($dir, '/');
}

function requireLogin(): void {
    if (!isset($_SESSION['user'])) {
        header("Location: " . base_url() . "/auth/login.php");
        exit;
    }
}

function requireRole($roles): void {
    requireLogin();
    $roles = (array)$roles;

    if (($_SESSION['user']['role'] ?? '') === 'admin') return;

    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        exit;
    }
}

