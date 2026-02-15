<?php
class Item {
  private PDO $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function getAll(): array {
    $stmt = $this->pdo->query("SELECT * FROM items ORDER BY item_name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getById(int $id): array|false {
    $stmt = $this->pdo->prepare("SELECT * FROM items WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function getNameById(int $id): ?string {
    $row = $this->getById($id);
    return $row ? (string)$row['item_name'] : null;
  }

  public function create(string $itemName, string $category = '', string $unit = ''): int {
    $stmt = $this->pdo->prepare("INSERT INTO items (item_name, category, unit) VALUES (?,?,?)");
    $stmt->execute([$itemName, $category, $unit]);
    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, string $itemName, string $category = '', string $unit = ''): void {
    $stmt = $this->pdo->prepare("UPDATE items SET item_name=?, category=?, unit=? WHERE id=?");
    $stmt->execute([$itemName, $category, $unit, $id]);
  }

  public function delete(int $id): void {
    $stmt = $this->pdo->prepare("DELETE FROM items WHERE id=?");
    $stmt->execute([$id]);
  }

  public function isUsed(int $id): bool {
    $checks = [
      "SELECT COUNT(*) FROM procurement WHERE item_id=?",
      "SELECT COUNT(*) FROM purchase_order_items WHERE item_id=?",
    ];
    foreach ($checks as $sql) {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([$id]);
      if ((int)$stmt->fetchColumn() > 0) {
        return true;
      }
    }
    return false;
  }
}
