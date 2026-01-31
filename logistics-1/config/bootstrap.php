<?php
define('BASE_PATH', realpath(__DIR__ . '/..'));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/auth.php';
