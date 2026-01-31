<?php
class PurchaseOrder {
  private $pdo;
  private ?bool $hasSupplierIdCol = null;

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
    // Works with both schemas:
    // - New: supplier_id + suppliers table
    // - Old: purchase_orders.supplier text
    return $this->pdo->query(
      "SELECT po.*,
              s.name AS supplier_name
       FROM purchase_orders po
       LEFT JOIN suppliers s ON po.supplier_id = s.id
       ORDER BY po.created_at DESC"
    )->fetchAll();
  }

  // $supplierId can be 0 if using legacy supplier text only
  public function create(string $poNumber, int $supplierId, string $supplierName, int $createdBy): int {
    if ($this->hasSupplierId()) {
      // store both: supplier_id (new) + supplier (legacy compatibility)
      $stmt = $this->pdo->prepare(
        "INSERT INTO purchase_orders (po_number, supplier_id, supplier, total_amount, status, created_by)
         VALUES (?, ?, ?, 0, 'Draft', ?)"
      );
      $stmt->execute([$poNumber, $supplierId ?: null, $supplierName, $createdBy]);
    } else {
      // legacy schema: supplier text only
      $stmt = $this->pdo->prepare(
        "INSERT INTO purchase_orders (po_number, supplier, total_amount, status, created_by)
         VALUES (?, ?, 0, 'Draft', ?)"
      );
      $stmt->execute([$poNumber, $supplierName, $createdBy]);
    }

    return (int)$this->pdo->lastInsertId();
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
      "SELECT * FROM purchase_order_items
       WHERE po_id = ?
       ORDER BY id ASC"
    );
    $stmt->execute([$poId]);
    return $stmt->fetchAll();
  }

  public function addItem(int $poId, string $item, int $qty, float $unit): void {
    $stmt = $this->pdo->prepare(
      "INSERT INTO purchase_order_items (po_id, item_name, quantity, unit_cost)
       VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$poId, $item, $qty, $unit]);

    $this->recomputeTotal($poId);
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

  public function delete(int $id): void {
    // items table has FK cascade in your SQL, but this is safe even without it
    $this->pdo->prepare("DELETE FROM purchase_order_items WHERE po_id = ?")->execute([$id]);
    $this->pdo->prepare("DELETE FROM purchase_orders WHERE id = ?")->execute([$id]);
  }
}
