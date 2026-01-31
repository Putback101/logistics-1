<?php
require "../config/auth.php";
require "../config/database.php";
require "../models/Approval.php";
requireLogin();
requireRole(['admin']);

$a = new Approval($pdo);
$rows = $a->listPending();
require "../views/approvals.php";
