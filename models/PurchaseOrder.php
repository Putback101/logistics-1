<?php
class PurchaseOrder {
  private $pdo;
  private ?bool $hasSupplierIdCol = null;
  private ?bool $hasProcurementIdCol = null;

  public function __construct($pdo){ $this->pdo = $pdo; }

  private function hasSupplierId(): bool {
    if ($this->hasSupplierIdCol !== null) return $this->hasSupplierIdCol;

    try {
      $stmt = $this->pdo->prepare("
        SELECT COUNT(*) c
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'purchase_orders'
          AND COLUMN_NAME = 'supplier_id'
      ");
      $stmt->execute();
      $this->hasSupplierIdCol = ((int)$stmt->fetch()['c']) > 0;
    } catch (Exception $e) {
      $this->hasSupplierIdCol = false;
    }
    return $this->hasSupplierIdCol;
  }

  public function getAll(): array {
    return $this->pdo->query(
      "SELECT po.*,
              s.name AS supplier_name
       FROM purchase_orders po
       LEFT JOIN suppliers s ON po.supplier_id = s.id
       ORDER BY po.created_at DESC"
    )->fetchAll();
  }

  private function hasProcurementId(): bool {
    if ($this->hasProcurementIdCol !== null) return $this->hasProcurementIdCol;
    try {
      $stmt = $this->pdo->prepare("
        SELECT COUNT(*) c
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'purchase_orders'
          AND COLUMN_NAME = 'procurement_id'
      ");
      $stmt->execute();
      $this->hasProcurementIdCol = ((int)$stmt->fetch()['c']) > 0;
    } catch (Exception $e) {
      $this->hasProcurementIdCol = false;
    }
    return $this->hasProcurementIdCol;
  }

  public function nextNumber(): string {
    $year = date('Y');
    $prefix = "PO-$year-";
    $stmt = $this->pdo->prepare(
      "SELECT po_number
       FROM purchase_orders
       WHERE po_number LIKE ?
       ORDER BY id DESC
       LIMIT 1"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    $seq = 1;
    if (is_string($last) && str_starts_with($last, $prefix)) {
      $parts = explode('-', $last);
      $lastSeq = (int)end($parts);
      if ($lastSeq > 0) $seq = $lastSeq + 1;
    }

    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
  }

  public function create(string $poNumber, int $supplierId, string $supplierName, int $createdBy): int {
    if ($this->hasSupplierId()) {
      $stmt = $this->pdo->prepare(
        "INSERT INTO purchase_orders (po_number, supplier_id, supplier, total_amount, status, created_by)
         VALUES (?, ?, ?, 0, 'Draft', ?)"
      );
      $stmt->execute([$poNumber, $supplierId ?: null, $supplierName, $createdBy]);
    } else {
      $stmt = $this->pdo->prepare(
        "INSERT INTO purchase_orders (po_number, supplier, total_amount, status, created_by)
         VALUES (?, ?, 0, 'Draft', ?)"
      );
      $stmt->execute([$poNumber, $supplierName, $createdBy]);
    }

    return (int)$this->pdo->lastInsertId();
  }

  public function createFromProcurement(
    string $poNumber,
    int $supplierId,
    string $supplierName,
    int $createdBy,
    int $procurementId
  ): int {
    if ($this->hasSupplierId() && $this->hasProcurementId()) {
      $stmt = $this->pdo->prepare(
        "INSERT INTO purchase_orders (po_number, supplier_id, supplier, total_amount, status, created_by, procurement_id)
         VALUES (?, ?, ?, 0, 'Draft', ?, ?)"
      );
      $stmt->execute([$poNumber, $supplierId ?: null, $supplierName, $createdBy, $procurementId]);
    } elseif ($this->hasSupplierId()) {
      $stmt = $this->pdo->prepare(
        "INSERT INTO purchase_orders (po_number, supplier_id, supplier, total_amount, status, created_by)
         VALUES (?, ?, ?, 0, 'Draft', ?)"
      );
      $stmt->execute([$poNumber, $supplierId ?: null, $supplierName, $createdBy]);
    } else {
      $stmt = $this->pdo->prepare(
        "INSERT INTO purchase_orders (po_number, supplier, total_amount, status, created_by)
         VALUES (?, ?, 0, 'Draft', ?)"
      );
      $stmt->execute([$poNumber, $supplierName, $createdBy]);
    }
    return (int)$this->pdo->lastInsertId();
  }

  public function getByProcurementId(int $procurementId): array|false {
    if (!$this->hasProcurementId()) {
      return false;
    }

    $stmt = $this->pdo->prepare("
      SELECT *
      FROM purchase_orders
      WHERE procurement_id = ?
      ORDER BY id DESC
      LIMIT 1
    ");
    $stmt->execute([$procurementId]);
    return $stmt->fetch();
  }

  public function getById(int $id): array|false {
    $stmt = $this->pdo->prepare(
      "SELECT po.*,
              s.name AS supplier_name,
              COALESCE(s.name, po.supplier) AS supplier
       FROM purchase_orders po
       LEFT JOIN suppliers s ON po.supplier_id = s.id
       WHERE po.id = ?
       LIMIT 1"
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
  }

  public function getItems(int $poId): array {
    $stmt = $this->pdo->prepare(
      "SELECT poi.*, i.item_name AS master_item_name, i.category AS item_category, i.unit AS item_unit
       FROM purchase_order_items poi
       LEFT JOIN items i ON i.id = poi.item_id
       WHERE poi.po_id = ?
       ORDER BY poi.id ASC"
    );
    $stmt->execute([$poId]);
    return $stmt->fetchAll();
  }

  public function addItem(int $poId, string $item, int $qty, float $unit, ?int $itemId = null): void {
    $stmt = $this->pdo->prepare(
      "INSERT INTO purchase_order_items (po_id, item_name, quantity, unit_cost, item_id)
       VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$poId, $item, $qty, $unit, $itemId]);

    $this->recomputeTotal($poId);
  }

  public function updateItem(int $itemId, string $item, int $qty, float $unit, ?int $masterItemId = null): void {
    $stmt = $this->pdo->prepare(
      "UPDATE purchase_order_items
       SET item_name = ?, quantity = ?, unit_cost = ?, item_id = ?
       WHERE id = ?"
    );
    $stmt->execute([$item, $qty, $unit, $masterItemId, $itemId]);
  }

  public function recomputeTotal(int $poId): void {
    $stmt = $this->pdo->prepare(
      "SELECT COALESCE(SUM(quantity * unit_cost),0) total
       FROM purchase_order_items
       WHERE po_id = ?"
    );
    $stmt->execute([$poId]);
    $total = (float)($stmt->fetch()['total'] ?? 0);

    $u = $this->pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");
    $u->execute([$total, $poId]);
  }

  public function setStatus(int $poId, string $status): void {
    $stmt = $this->pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $poId]);
  }

  public function updateSupplier(int $poId, int $supplierId, string $supplierName): void {
    $stmt = $this->pdo->prepare(
      "UPDATE purchase_orders
       SET supplier_id = ?, supplier = ?
       WHERE id = ?"
    );
    $stmt->execute([$supplierId ?: null, $supplierName, $poId]);
  }

  public function delete(int $id): void {
    $this->pdo->prepare("DELETE FROM purchase_order_items WHERE po_id = ?")->execute([$id]);
    $this->pdo->prepare("DELETE FROM purchase_orders WHERE id = ?")->execute([$id]);
  }
}
