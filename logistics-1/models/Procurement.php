<?php
class Procurement {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        return $this->pdo->query(
            "SELECT * FROM procurement ORDER BY id DESC"
        )->fetchAll();
    }

    public function create($item, $qty, $supplier, $status) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO procurement (item_name, quantity, supplier, status)
             VALUES (?,?,?,?)"
        );
        return $stmt->execute([$item, $qty, $supplier, $status]);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM procurement WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $item, $qty, $supplier, $status) {
        $stmt = $this->pdo->prepare(
            "UPDATE procurement
             SET item_name=?, quantity=?, supplier=?, status=?
             WHERE id=?"
        );
        return $stmt->execute([$item, $qty, $supplier, $status, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM procurement WHERE id=?");
        return $stmt->execute([$id]);
    }
}
