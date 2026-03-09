<?php
class WarehousingRequest {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAll(): array {
        $stmt = $this->pdo->query("
            SELECT wr.*, i.item_name AS master_item_name
            FROM warehousing_requests wr
            LEFT JOIN items i ON i.id = wr.item_id
            ORDER BY wr.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySource(string $sourceModule, string $sourceReference): array {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM warehousing_requests
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
        ?int $itemId,
        string $itemName,
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
        $allowedTypes = ['replenishment', 'spare_parts', 'stock_transfer', 'issuance', 'asset_item'];
        if (!in_array($requestType, $allowedTypes, true)) {
            $requestType = 'replenishment';
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
            INSERT INTO warehousing_requests (
                request_ref,
                request_type,
                item_id,
                item_name,
                quantity,
                status,
                priority,
                requested_by,
                source_module,
                source_system,
                source_reference,
                notes,
                source_payload
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        return $stmt->execute([
            $requestRef,
            $requestType,
            $itemId,
            $itemName,
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
