<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Budget.php";

requireLogin();
requireRole(['admin','manager','procurement_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'procurement', 'add');
$canEdit = hasPermission($userRole, 'procurement', 'edit');
$canApprove = hasPermission($userRole, 'procurement', 'approve');

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

if (isset($_POST['submit_budget_request'])) {
  if (!$canAdd) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to submit budget requests.');
    header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
  }

  $year = (int)($_POST['year'] ?? 0);
  $amount = (float)($_POST['requested_amount'] ?? 0);
  $purpose = trim((string)($_POST['purpose'] ?? ''));

  if ($year < 2000 || $amount <= 0 || $purpose === '') {
    set_flash('error', 'Please provide valid year, amount, and purpose.');
    header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
  }

  $budget->submitRequest($year, $amount, $purpose, (int)($_SESSION['user']['id'] ?? 0));

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "Submitted budget request for year $year (PHP " . number_format($amount, 2) . ")"]);

  set_flash('success', 'Budget request sent for finance review.');
  header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
}

if (isset($_POST['approve_budget_request']) || isset($_POST['reject_budget_request'])) {
  if (!$canApprove) {
    http_response_code(403);
    set_flash('error', 'You are not allowed to review budget requests.');
    header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
  }

  $id = $_POST['id'] ?? '';
  if (!ctype_digit((string)$id)) {
    set_flash('error', 'Invalid request.');
    header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
  }

  $status = isset($_POST['approve_budget_request']) ? 'Approved' : 'Rejected';
  $ok = $budget->decideRequest((int)$id, $status, (int)($_SESSION['user']['id'] ?? 0));

  if (!$ok) {
    set_flash('error', 'Request already processed or invalid.');
    header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
  }

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$_SESSION['user']['id'], "$status budget request ID $id"]);

  set_flash('success', "Budget request $status.");
  header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
}

header("Location: ../views/procurement/procurement.php?tab=budget"); exit;
