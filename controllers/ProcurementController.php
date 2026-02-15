<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";
require "../config/permissions.php";
require "../models/Procurement.php";
require "../models/Budget.php";
require "../models/Item.php";

requireLogin();
requireRole(['admin','manager','procurement_staff']);

$userRole = $_SESSION['user']['role'] ?? '';
$canAdd = hasPermission($userRole, 'procurement', 'add');
$canEdit = hasPermission($userRole, 'procurement', 'edit');
$canDelete = hasPermission($userRole, 'procurement', 'delete');
$canApprove = hasPermission($userRole, 'procurement', 'approve');

$procurement = new Procurement($pdo);
$budgetModel = new Budget($pdo);
$itemModel = new Item($pdo);

function budget_capacity_check(Budget $budgetModel, Procurement $procurement, int $year, float $needed, array $statuses, ?int $excludeId = null): array {
    $budgetRow = $budgetModel->getByYear($year);
    if (!$budgetRow) {
        return [false, 'No budget configured for selected year.'];
    }

    $allocated = (float)($budgetRow['allocated'] ?? 0);
    $spent = (float)($budgetRow['spent'] ?? 0);
    $requested = $procurement->getYearRequestedTotal($year, $statuses, $excludeId);
    $available = $allocated - $spent - $requested;

    if ($needed > $available) {
        return [false, 'Budget exceeded. Available for year ' . $year . ': ?' . number_format($available, 2) . '.'];
    }

    return [true, ''];
}

function resolve_item_line(array $line, Item $itemModel): array {
    $itemIdRaw = $line['item_id'] ?? '';
    $itemId = ctype_digit((string)$itemIdRaw) ? (int)$itemIdRaw : 0;
    $itemName = trim((string)($line['item_name'] ?? ''));

    if ($itemId > 0) {
        $masterName = $itemModel->getNameById($itemId);
        if ($masterName !== null && $masterName !== '') {
            $itemName = $masterName;
        } else {
            $itemId = 0;
        }
    }

    return [$itemId > 0 ? $itemId : null, $itemName];
}

/* ADD */
if (isset($_POST['add'])) {
    if (!$canAdd) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to add procurement requests.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $budgetYear = $_POST['budget_year'] ?? '';
    $budgetYear = ctype_digit((string)$budgetYear) ? (int)$budgetYear : 0;
    if ($budgetYear <= 0) {
        set_flash('error', 'Select a budget year.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    if (!$budgetModel->getByYear($budgetYear)) {
        set_flash('error', 'No budget configured for selected year.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $itemsInput = $_POST['items'] ?? [];
    $hasMultiItems = is_array($itemsInput) && count($itemsInput) > 0;
    $requestRef = 'REQ-' . date('Ymd-His') . '-' . random_int(100, 999);
    $created = 0;

    if ($hasMultiItems) {
        $reservedInRequest = 0.0;
        foreach ($itemsInput as $line) {
            [$itemId, $item] = resolve_item_line($line, $itemModel);
            $qty = (int)($line['quantity'] ?? 0);
            $supplier = trim((string)($line['supplier'] ?? ''));
            $estimated = (float)($line['estimated_amount'] ?? 0);
            if ($item === '' || $qty <= 0 || $supplier === '') {
                continue;
            }

            [$okBudget, $budgetMsg] = budget_capacity_check(
                $budgetModel,
                $procurement,
                $budgetYear,
                $estimated + $reservedInRequest,
                ['Pending','Approved']
            );
            if (!$okBudget) {
                set_flash('error', $budgetMsg);
                header("Location: ../views/procurement/procurement.php");
                exit;
            }

            $procurement->create($item, $qty, $supplier, 'Pending', $budgetYear, $estimated, $requestRef, $itemId);
            $reservedInRequest += $estimated;
            $created++;
        }
    } else {
        [$itemId, $item] = resolve_item_line($_POST, $itemModel);
        $qty      = (int)($_POST['quantity'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $estimated = (float)($_POST['estimated_amount'] ?? 0);

        if ($item !== '' && $qty > 0 && $supplier !== '') {
            [$okBudget, $budgetMsg] = budget_capacity_check(
                $budgetModel,
                $procurement,
                $budgetYear,
                $estimated,
                ['Pending','Approved']
            );
            if (!$okBudget) {
                set_flash('error', $budgetMsg);
                header("Location: ../views/procurement/procurement.php");
                exit;
            }

            $procurement->create($item, $qty, $supplier, 'Pending', $budgetYear, $estimated, $requestRef, $itemId);
            $created = 1;
        }
    }

    if ($created === 0) {
        set_flash('error', 'Please add at least one valid request line.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Added procurement request group ($requestRef) with $created line(s)"]);

    set_flash('success', 'Procurement request added successfully.');
    header("Location: ../views/procurement/procurement.php");
    exit;
}

/* UPDATE */
if (isset($_POST['update'])) {
    if (!$canEdit) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to edit procurement requests.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $id       = $_POST['id'] ?? '';
    [$itemId, $item] = resolve_item_line($_POST, $itemModel);
    $qty      = (int)($_POST['quantity'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');
    $status   = $_POST['status'] ?? 'Pending';
    $budgetYear = $_POST['budget_year'] ?? '';
    $budgetYear = ctype_digit((string)$budgetYear) ? (int)$budgetYear : 0;
    $estimated = (float)($_POST['estimated_amount'] ?? 0);

    if (!ctype_digit((string)$id) || $item === '' || $qty <= 0 || $supplier === '' || $budgetYear <= 0) {
        set_flash('error', 'Invalid data. Please try again.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $row = $procurement->getById((int)$id);
    if (!$row) {
        set_flash('error', 'Procurement request not found.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    if (!empty($row['po_number'])) {
        set_flash('error', 'Request already linked to a Purchase Order and can no longer be edited.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    [$okBudget, $budgetMsg] = budget_capacity_check(
        $budgetModel,
        $procurement,
        $budgetYear,
        $estimated,
        ['Pending','Approved'],
        (int)$id
    );
    if (!$okBudget) {
        set_flash('error', $budgetMsg);
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $procurement->update((int)$id, $item, $qty, $supplier, $status, $budgetYear, $estimated, $itemId);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Updated procurement request ($item)"]);

    set_flash('success', 'Procurement updated successfully.');
    header("Location: ../views/procurement/procurement.php");
    exit;
}

/* APPROVE REQUEST (Manager/Admin) */
if (isset($_POST['approve_request'])) {
    if (!$canApprove) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to approve procurement requests.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $id = $_POST['id'] ?? '';
    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid request ID.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $row = $procurement->getById((int)$id);
    if (!$row) {
        set_flash('error', 'Procurement request not found.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }
    if (($row['status'] ?? '') === 'Approved') {
        set_flash('success', 'Request already approved.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $budgetYear = (int)($row['budget_year'] ?? 0);
    $estimated = (float)($row['estimated_amount'] ?? 0);
    if ($budgetYear <= 0) {
        set_flash('error', 'Request has no budget year. Please edit it first.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    [$okBudget, $budgetMsg] = budget_capacity_check(
        $budgetModel,
        $procurement,
        $budgetYear,
        $estimated,
        ['Approved'],
        (int)$id
    );
    if (!$okBudget) {
        set_flash('error', $budgetMsg);
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $procurement->setStatus((int)$id, 'Approved');
    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Approved procurement request (ID: $id)"]);
    set_flash('success', 'Procurement request approved.');
    header("Location: ../views/procurement/procurement.php");
    exit;
}

/* DELETE */
if (isset($_GET['delete'])) {
    if (!$canDelete) {
        http_response_code(403);
        set_flash('error', 'You are not allowed to delete procurement requests.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $id = $_GET['delete'];

    if (!ctype_digit((string)$id)) {
        set_flash('error', 'Invalid request ID.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $row = $procurement->getById((int)$id);
    if (!$row) {
        set_flash('error', 'Request not found.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    if (!empty($row['po_number'])) {
        set_flash('error', 'Cannot delete request already linked to a Purchase Order.');
        header("Location: ../views/procurement/procurement.php");
        exit;
    }

    $procurement->delete((int)$id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$_SESSION['user']['id'], "Deleted procurement request (ID: $id)"]);

    set_flash('success', 'Procurement deleted successfully.');
    header("Location: ../views/procurement/procurement.php");
    exit;
}

header("Location: ../views/procurement/procurement.php");
exit;
