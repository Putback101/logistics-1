<?php
class Inventory {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        return $this->pdo->query(
            "SELECT * FROM inventory ORDER BY id DESC"
        )->fetchAll();
    }

    public function create($item, $stock, $location) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO inventory (item_name, stock, location)
             VALUES (?,?,?)"
        );
        return $stmt->execute([$item, $stock, $location]);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM inventory WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $item, $stock, $location) {
        $stmt = $this->pdo->prepare(
            "UPDATE inventory
             SET item_name=?, stock=?, location=?
             WHERE id=?"
        );
        return $stmt->execute([$item, $stock, $location, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM inventory WHERE id=?");
        return $stmt->execute([$id]);
    }
}
