<?php
require "../config/database.php";
require "../config/integrations.php";
require "../models/AssetRequest.php";

header('Content-Type: application/json; charset=UTF-8');

function asset_json_response(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function asset_bearer_token(): string {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    $auth = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim((string)$m[1]);
    }
    return trim((string)($headers['X-Asset-Token'] ?? $headers['x-asset-token'] ?? ''));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    asset_json_response(405, ['ok' => false, 'error' => 'Method not allowed. Use POST.']);
}

$configuredToken = asset_intake_token();
if ($configuredToken === '') {
    asset_json_response(503, ['ok' => false, 'error' => 'Inbound asset token is not configured.']);
}

$providedToken = asset_bearer_token();
if (!hash_equals($configuredToken, $providedToken)) {
    asset_json_response(401, ['ok' => false, 'error' => 'Unauthorized token.']);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
    asset_json_response(400, ['ok' => false, 'error' => 'Invalid JSON payload.']);
}

$sourceModule = strtolower(trim((string)($payload['source_module'] ?? 'external')));
$sourceSystem = trim((string)($payload['source_system'] ?? ''));
$sourceReference = trim((string)($payload['source_reference'] ?? ''));
$requestType = strtolower(trim((string)($payload['request_type'] ?? 'registration')));
$priority = trim((string)($payload['priority'] ?? 'Normal'));
$requestedBy = ctype_digit((string)($payload['requested_by'] ?? '')) ? (int)$payload['requested_by'] : null;
$notes = trim((string)($payload['notes'] ?? ''));
$lines = $payload['lines'] ?? [];

if ($sourceModule === '' || $sourceReference === '') {
    asset_json_response(422, ['ok' => false, 'error' => 'source_module and source_reference are required.']);
}
if (!is_array($lines) || count($lines) === 0) {
    asset_json_response(422, ['ok' => false, 'error' => 'lines is required and must contain at least one item.']);
}

$requestModel = new AssetRequest($pdo);
$duplicateRows = $requestModel->findBySource($sourceModule, $sourceReference);
if (!empty($duplicateRows)) {
    $requestRef = (string)($duplicateRows[0]['request_ref'] ?? '');
    asset_json_response(200, [
        'ok' => true,
        'duplicate' => true,
        'request_ref' => $requestRef,
        'request_ids' => array_values(array_map(static fn($r) => (int)($r['id'] ?? 0), $duplicateRows)),
    ]);
}

$requestRef = 'AST-EXT-' . date('Ymd-His') . '-' . random_int(100, 999);
$createdIds = [];
$rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

foreach ($lines as $line) {
    if (!is_array($line)) {
        continue;
    }

    $assetName = trim((string)($line['asset_name'] ?? ''));
    $assetTag = trim((string)($line['asset_tag'] ?? ''));
    $assetCategory = trim((string)($line['asset_category'] ?? ''));
    $quantity = (int)($line['quantity'] ?? 1);
    $lineNotes = trim((string)($line['notes'] ?? ''));
    $effectiveNotes = $lineNotes !== '' ? $lineNotes : ($notes !== '' ? $notes : null);

    if ($assetName === '' || $quantity <= 0) {
        continue;
    }

    $ok = $requestModel->create(
        $requestRef,
        $requestType,
        $assetTag !== '' ? $assetTag : null,
        $assetName,
        $assetCategory !== '' ? $assetCategory : null,
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
    asset_json_response(422, ['ok' => false, 'error' => 'No valid request lines were received.']);
}

$auditUser = $requestedBy ?: null;
$action = "Inbound asset request ($requestRef) type {$requestType} from {$sourceModule} [{$sourceReference}] with " . count($createdIds) . " line(s)";
$stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta) VALUES (?,?,?,?,?)");
$stmt->execute([$auditUser, $action, 'asset_requests', $createdIds[0], $rawPayload ?: null]);

asset_json_response(201, [
    'ok' => true,
    'duplicate' => false,
    'request_ref' => $requestRef,
    'request_type' => $requestType,
    'created_count' => count($createdIds),
    'request_ids' => $createdIds,
    'status' => 'Pending',
]);
