<?php
require "../config/database.php";
require "../config/integrations.php";
require "../models/Procurement.php";
require "../models/Budget.php";
require "../models/Item.php";

header('Content-Type: application/json; charset=UTF-8');

function api_json_response(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function api_bearer_token(): string {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    $auth = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim((string)$m[1]);
    }

    return trim((string)($headers['X-Procurement-Token'] ?? $headers['x-procurement-token'] ?? ''));
}

function api_budget_capacity_check(Budget $budgetModel, Procurement $procurement, int $year, float $needed, array $statuses, ?int $excludeId = null): array {
    $budgetRow = $budgetModel->getByYear($year);
    if (!$budgetRow) {
        return [false, 'No budget configured for selected year.'];
    }

    $allocated = (float)($budgetRow['allocated'] ?? 0);
    $spent = (float)($budgetRow['spent'] ?? 0);
    $requested = $procurement->getYearRequestedTotal($year, $statuses, $excludeId);
    $available = $allocated - $spent - $requested;

    if ($needed > $available) {
        return [false, 'Budget exceeded. Available for year ' . $year . ': PHP ' . number_format($available, 2) . '.'];
    }

    return [true, ''];
}

function api_resolve_item_line(array $line, Item $itemModel): array {
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_json_response(405, ['ok' => false, 'error' => 'Method not allowed. Use POST.']);
}

$configuredToken = procurement_intake_token();
if ($configuredToken === '') {
    api_json_response(503, [
        'ok' => false,
        'error' => 'Inbound procurement intake token is not configured. Set PROCUREMENT_INTAKE_TOKEN in environment.',
    ]);
}

$providedToken = api_bearer_token();
if (!hash_equals($configuredToken, $providedToken)) {
    api_json_response(401, ['ok' => false, 'error' => 'Unauthorized token.']);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
    api_json_response(400, ['ok' => false, 'error' => 'Invalid JSON payload.']);
}

$sourceModule = strtolower(trim((string)($payload['source_module'] ?? 'external')));
$sourceSystem = trim((string)($payload['source_system'] ?? ''));
$sourceReference = trim((string)($payload['source_reference'] ?? ''));
$budgetYear = ctype_digit((string)($payload['budget_year'] ?? '')) ? (int)$payload['budget_year'] : (int)date('Y');
$requestedBy = ctype_digit((string)($payload['requested_by'] ?? '')) ? (int)$payload['requested_by'] : null;
$lines = $payload['lines'] ?? [];

if ($sourceModule === '' || $sourceReference === '') {
    api_json_response(422, ['ok' => false, 'error' => 'source_module and source_reference are required.']);
}
if ($budgetYear <= 0) {
    api_json_response(422, ['ok' => false, 'error' => 'budget_year is required and must be a positive year.']);
}
if (!is_array($lines) || count($lines) === 0) {
    api_json_response(422, ['ok' => false, 'error' => 'lines is required and must contain at least one item.']);
}

$procurement = new Procurement($pdo);
$budgetModel = new Budget($pdo);
$itemModel = new Item($pdo);

$duplicateRows = $procurement->findBySource($sourceModule, $sourceReference);
if (!empty($duplicateRows)) {
    $requestRef = (string)($duplicateRows[0]['request_ref'] ?? '');
    api_json_response(200, [
        'ok' => true,
        'duplicate' => true,
        'message' => 'Request was already received.',
        'request_ref' => $requestRef,
        'request_ids' => array_values(array_map(static fn($r) => (int)($r['id'] ?? 0), $duplicateRows)),
    ]);
}

if (!$budgetModel->getByYear($budgetYear)) {
    api_json_response(422, ['ok' => false, 'error' => 'No budget configured for selected year.']);
}

$requestRef = 'EXT-' . date('Ymd-His') . '-' . random_int(100, 999);
$reservedInRequest = 0.0;
$createdIds = [];
$rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

foreach ($lines as $line) {
    if (!is_array($line)) {
        continue;
    }

    [$itemId, $itemName] = api_resolve_item_line($line, $itemModel);
    $quantity = (int)($line['quantity'] ?? 0);
    $supplier = trim((string)($line['supplier'] ?? ''));
    $estimated = (float)($line['estimated_amount'] ?? 0);

    if ($supplier === '') {
        $supplier = 'TBD';
    }

    if ($itemName === '' || $quantity <= 0) {
        continue;
    }

    [$okBudget, $budgetMsg] = api_budget_capacity_check(
        $budgetModel,
        $procurement,
        $budgetYear,
        $estimated + $reservedInRequest,
        ['Pending', 'Approved']
    );
    if (!$okBudget) {
        api_json_response(422, ['ok' => false, 'error' => $budgetMsg]);
    }

    $ok = $procurement->create(
        $itemName,
        $quantity,
        $supplier,
        'Pending',
        $budgetYear,
        $estimated,
        $requestRef,
        $itemId,
        $requestedBy,
        $sourceModule,
        $sourceSystem,
        $sourceReference,
        $rawPayload
    );

    if ($ok) {
        $createdIds[] = (int)$pdo->lastInsertId();
        $reservedInRequest += $estimated;
    }
}

if (empty($createdIds)) {
    api_json_response(422, ['ok' => false, 'error' => 'No valid request lines were received.']);
}

$auditUser = $requestedBy ?: null;
$action = "Inbound procurement request ($requestRef) from {$sourceModule} [{$sourceReference}] with " . count($createdIds) . " line(s)";
$stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta) VALUES (?,?,?,?,?)");
$stmt->execute([$auditUser, $action, 'procurement', $createdIds[0], $rawPayload ?: null]);

api_json_response(201, [
    'ok' => true,
    'duplicate' => false,
    'request_ref' => $requestRef,
    'created_count' => count($createdIds),
    'request_ids' => $createdIds,
    'status' => 'Pending',
]);
