<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../models/Budget.php";

requireLogin();
requireRole(['admin','manager','procurement']);


$budget = new Budget($pdo);

if (isset($_POST['save'])) {
  $year = (int)($_POST['year'] ?? 0);
  $allocated = (float)($_POST['allocated'] ?? 0);

  if ($year < 2000 || $allocated <= 0) {
    set_flash('error', 'Invalid budget input.');
    header("Location: ../views/budgets.php"); exit;
  }

  $budget->upsert($year, $allocated);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Updated budget for year $year"]);

  set_flash('success', 'Budget saved successfully.');
  header("Location: ../views/budgets.php"); exit;
}

header("Location: ../views/budgets.php"); exit;
