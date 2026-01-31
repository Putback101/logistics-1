<?php
class Supplier {
  private PDO $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function getAll(): array {
    $stmt = $this->pdo->query("SELECT * FROM suppliers ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function create(string $name, string $contactPerson = '', string $email = '', string $phone = ''): int {
    $stmt = $this->pdo->prepare("
      INSERT INTO suppliers (name, contact_person, email, phone)
      VALUES (?,?,?,?)
    ");
    $stmt->execute([$name, $contactPerson, $email, $phone]);
    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, string $name, string $contactPerson = '', string $email = '', string $phone = ''): void {
    $stmt = $this->pdo->prepare("
      UPDATE suppliers
      SET name=?, contact_person=?, email=?, phone=?
      WHERE id=?
    ");
    $stmt->execute([$name, $contactPerson, $email, $phone, $id]);
  }

  public function delete(int $id): void {
    $stmt = $this->pdo->prepare("DELETE FROM suppliers WHERE id=?");
    $stmt->execute([$id]);
  }

  public function isUsedInPurchaseOrders(int $supplierId): bool {
    // if your PO table uses supplier_id, this works.
    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=?");
    $stmt->execute([$supplierId]);
    return (int)$stmt->fetchColumn() > 0;
  }

  // (optional) helper used in your PO creation logic
  public function getNameById(int $id): ?string {
    $stmt = $this->pdo->prepare("SELECT name FROM suppliers WHERE id=?");
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();
    return $name ? (string)$name : null;
  }
}
