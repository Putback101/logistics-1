<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Budget.php";

requireLogin();
requireRole(['admin','manager','procurement_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canEdit = hasPermission($userRole, 'procurement', 'edit');

$budget = new Budget($pdo);

if (isset($_POST['save'])) {
  if (!$canEdit) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to update budgets.');
    header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
  }

  $year = (int)($_POST['year'] ?? 0);
  $allocated = (float)($_POST['allocated'] ?? 0);
  $spentRaw = trim((string)($_POST['spent'] ?? ''));
  $spent = ($spentRaw === '') ? null : (float)$spentRaw;

  if ($year < 2000 || $allocated < 0 || ($spent !== null && $spent < 0)) {
    set_flash('error', 'Invalid budget input.');
    header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
  }

  $budget->upsert($year, $allocated, $spent);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Updated budget for year $year"]);

  set_flash('success', 'Budget saved successfully.');
  header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
}

header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
