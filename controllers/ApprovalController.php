<?php
require "../config/auth.php";
require "../config/database.php";

requireLogin();
requireRole(['admin']);

header("Location: ../views/procurement/procurement.php?tab=approvals");
exit;
