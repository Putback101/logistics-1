<?php
class Fleet {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        return $this->pdo->query("SELECT * FROM fleet ORDER BY id DESC")->fetchAll();
    }

    public function create($vehicle, $plate, $status) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO fleet (vehicle_name, plate_number, status) VALUES (?,?,?)"
        );
        return $stmt->execute([$vehicle, $plate, $status]);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM fleet WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $vehicle, $plate, $status) {
        $stmt = $this->pdo->prepare(
            "UPDATE fleet SET vehicle_name=?, plate_number=?, status=? WHERE id=?"
        );
        return $stmt->execute([$vehicle, $plate, $status, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM fleet WHERE id=?");
        return $stmt->execute([$id]);
    }
}
