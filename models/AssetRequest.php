<?php
class AssetRequest {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAll(): array {
        $stmt = $this->pdo->query("SELECT * FROM asset_requests ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySource(string $sourceModule, string $sourceReference): array {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM asset_requests
            WHERE source_module = ?
              AND source_reference = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$sourceModule, $sourceReference]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        string $requestRef,
        string $requestType,
        ?string $assetTag,
        string $assetName,
        ?string $assetCategory,
        int $quantity,
        string $status,
        string $priority,
        ?int $requestedBy,
        string $sourceModule,
        ?string $sourceSystem,
        string $sourceReference,
        ?string $notes,
        ?string $sourcePayload
    ): bool {
        $allowedTypes = ['registration', 'allocation', 'status_update'];
        if (!in_array($requestType, $allowedTypes, true)) {
            $requestType = 'registration';
        }

        $allowedStatus = ['Pending', 'Processing', 'Fulfilled', 'Rejected'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'Pending';
        }

        $allowedPriority = ['Low', 'Normal', 'High', 'Urgent'];
        if (!in_array($priority, $allowedPriority, true)) {
            $priority = 'Normal';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO asset_requests (
                request_ref,
                request_type,
                asset_tag,
                asset_name,
                asset_category,
                quantity,
                status,
                priority,
                requested_by,
                source_module,
                source_system,
                source_reference,
                notes,
                source_payload
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        return $stmt->execute([
            $requestRef,
            $requestType,
            $assetTag,
            $assetName,
            $assetCategory,
            $quantity,
            $status,
            $priority,
            $requestedBy,
            $sourceModule,
            $sourceSystem,
            $sourceReference,
            $notes,
            $sourcePayload,
        ]);
    }
}
