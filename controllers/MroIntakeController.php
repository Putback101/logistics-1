<?php
require "../config/database.php";
require "../config/integrations.php";
require "../models/Maintenance.php";

header('Content-Type: application/json; charset=UTF-8');

function mro_json_response(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function mro_bearer_token(): string {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    $auth = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim((string)$m[1]);
    }
    return trim((string)($headers['X-Mro-Token'] ?? $headers['x-mro-token'] ?? ''));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mro_json_response(405, ['ok' => false, 'error' => 'Method not allowed. Use POST.']);
}

$configuredToken = mro_intake_token();
if ($configuredToken === '') {
    mro_json_response(503, ['ok' => false, 'error' => 'Inbound MRO token is not configured.']);
}

$providedToken = mro_bearer_token();
if (!hash_equals($configuredToken, $providedToken)) {
    mro_json_response(401, ['ok' => false, 'error' => 'Unauthorized token.']);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
    mro_json_response(400, ['ok' => false, 'error' => 'Invalid JSON payload.']);
}

$sourceModule = strtolower(trim((string)($payload['source_module'] ?? 'external')));
$sourceSystem = trim((string)($payload['source_system'] ?? ''));
$sourceReference = trim((string)($payload['source_reference'] ?? ''));
$priority = trim((string)($payload['priority'] ?? 'Normal'));
$type = (string)($payload['type'] ?? 'Maintenance');
$requestedBy = ctype_digit((string)($payload['requested_by'] ?? '')) ? (int)$payload['requested_by'] : null;
$lines = $payload['lines'] ?? [];

if ($sourceModule === '' || $sourceReference === '') {
    mro_json_response(422, ['ok' => false, 'error' => 'source_module and source_reference are required.']);
}
if (!is_array($lines) || count($lines) === 0) {
    mro_json_response(422, ['ok' => false, 'error' => 'lines is required and must contain at least one request line.']);
}

$maintenance = new Maintenance($pdo);
$duplicateRows = $maintenance->findBySource($sourceModule, $sourceReference);
if (!empty($duplicateRows)) {
    $requestRef = (string)($duplicateRows[0]['request_ref'] ?? '');
    mro_json_response(200, [
        'ok' => true,
        'duplicate' => true,
        'request_ref' => $requestRef,
        'request_ids' => array_values(array_map(static fn($r) => (int)($r['id'] ?? 0), $duplicateRows)),
    ]);
}

$requestRef = 'MRO-EXT-' . date('Ymd-His') . '-' . random_int(100, 999);
$createdIds = [];
$rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

foreach ($lines as $line) {
    if (!is_array($line)) {
        continue;
    }

    $fleetId = ctype_digit((string)($line['fleet_id'] ?? '')) ? (int)$line['fleet_id'] : 0;
    $assetId = ctype_digit((string)($line['asset_id'] ?? '')) ? (int)$line['asset_id'] : 0;
    $desc = trim((string)($line['description'] ?? ''));
    $cost = (float)($line['cost'] ?? 0);

    if (($fleetId <= 0 && $assetId <= 0) || $desc === '') {
        continue;
    }

    if ($fleetId > 0) {
        $maintenance->createFleet($fleetId, $type, $desc, $cost, null, $requestedBy, $requestRef, $priority, $sourceModule, $sourceSystem, $sourceReference, $rawPayload);
    } else {
        $maintenance->createAsset($assetId, $type, $desc, $cost, null, $requestedBy, $requestRef, $priority, $sourceModule, $sourceSystem, $sourceReference, $rawPayload);
    }

    $createdIds[] = (int)$pdo->lastInsertId();
}

if (empty($createdIds)) {
    mro_json_response(422, ['ok' => false, 'error' => 'No valid request lines were received.']);
}

$auditUser = $requestedBy ?: null;
$action = "Inbound MRO request ($requestRef) from {$sourceModule} [{$sourceReference}] with " . count($createdIds) . " line(s)";
$stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta) VALUES (?,?,?,?,?)");
$stmt->execute([$auditUser, $action, 'maintenance_logs', $createdIds[0], $rawPayload ?: null]);

mro_json_response(201, [
    'ok' => true,
    'duplicate' => false,
    'request_ref' => $requestRef,
    'created_count' => count($createdIds),
    'request_ids' => $createdIds,
    'status' => 'Pending',
]);
