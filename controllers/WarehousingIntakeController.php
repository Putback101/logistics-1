<?php
require "../config/database.php";
require "../config/integrations.php";
require "../models/Item.php";
require "../models/WarehousingRequest.php";

header('Content-Type: application/json; charset=UTF-8');

function ware_json_response(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function ware_bearer_token(): string {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    $auth = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim((string)$m[1]);
    }

    return trim((string)($headers['X-Warehousing-Token'] ?? $headers['x-warehousing-token'] ?? ''));
}

function ware_resolve_item(array $line, Item $itemModel): array {
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
    ware_json_response(405, ['ok' => false, 'error' => 'Method not allowed. Use POST.']);
}

$configuredToken = warehousing_intake_token();
if ($configuredToken === '') {
    ware_json_response(503, [
        'ok' => false,
        'error' => 'Inbound warehousing intake token is not configured. Set WAREHOUSING_INTAKE_TOKEN.',
    ]);
}

$providedToken = ware_bearer_token();
if (!hash_equals($configuredToken, $providedToken)) {
    ware_json_response(401, ['ok' => false, 'error' => 'Unauthorized token.']);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
    ware_json_response(400, ['ok' => false, 'error' => 'Invalid JSON payload.']);
}

$sourceModule = strtolower(trim((string)($payload['source_module'] ?? 'external')));
$sourceSystem = trim((string)($payload['source_system'] ?? ''));
$sourceReference = trim((string)($payload['source_reference'] ?? ''));
$requestType = strtolower(trim((string)($payload['request_type'] ?? 'replenishment')));
$priority = trim((string)($payload['priority'] ?? 'Normal'));
$requestedBy = ctype_digit((string)($payload['requested_by'] ?? '')) ? (int)$payload['requested_by'] : null;
$notes = trim((string)($payload['notes'] ?? ''));
$lines = $payload['lines'] ?? [];

if ($sourceModule === '' || $sourceReference === '') {
    ware_json_response(422, ['ok' => false, 'error' => 'source_module and source_reference are required.']);
}
if (!is_array($lines) || count($lines) === 0) {
    ware_json_response(422, ['ok' => false, 'error' => 'lines is required and must contain at least one item.']);
}

$requestModel = new WarehousingRequest($pdo);
$itemModel = new Item($pdo);
$duplicateRows = $requestModel->findBySource($sourceModule, $sourceReference);
if (!empty($duplicateRows)) {
    $requestRef = (string)($duplicateRows[0]['request_ref'] ?? '');
    ware_json_response(200, [
        'ok' => true,
        'duplicate' => true,
        'message' => 'Request was already received.',
        'request_ref' => $requestRef,
        'request_ids' => array_values(array_map(static fn($r) => (int)($r['id'] ?? 0), $duplicateRows)),
    ]);
}

$requestRef = 'WH-EXT-' . date('Ymd-His') . '-' . random_int(100, 999);
$createdIds = [];
$rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

foreach ($lines as $line) {
    if (!is_array($line)) {
        continue;
    }

    [$itemId, $itemName] = ware_resolve_item($line, $itemModel);
    $quantity = (int)($line['quantity'] ?? 0);
    $lineNotes = trim((string)($line['notes'] ?? ''));
    $effectiveNotes = $lineNotes !== '' ? $lineNotes : ($notes !== '' ? $notes : null);

    if ($itemName === '' || $quantity <= 0) {
        continue;
    }

    $ok = $requestModel->create(
        $requestRef,
        $requestType,
        $itemId,
        $itemName,
        $quantity,
        'Pending',
        $priority,
        $requestedBy,
        $sourceModule,
        $sourceSystem,
        $sourceReference,
        $effectiveNotes,
        $rawPayload
    );

    if ($ok) {
        $createdIds[] = (int)$pdo->lastInsertId();
    }
}

if (empty($createdIds)) {
    ware_json_response(422, ['ok' => false, 'error' => 'No valid request lines were received.']);
}

$auditUser = $requestedBy ?: null;
$action = "Inbound warehousing request ($requestRef) type {$requestType} from {$sourceModule} [{$sourceReference}] with " . count($createdIds) . " line(s)";
$stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta) VALUES (?,?,?,?,?)");
$stmt->execute([$auditUser, $action, 'warehousing_requests', $createdIds[0], $rawPayload ?: null]);

ware_json_response(201, [
    'ok' => true,
    'duplicate' => false,
    'request_ref' => $requestRef,
    'request_type' => $requestType,
    'created_count' => count($createdIds),
    'request_ids' => $createdIds,
    'status' => 'Pending',
]);
