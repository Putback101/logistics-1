<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        return $this->pdo->query(
            "SELECT id, fullname, email, role, created_at FROM users ORDER BY id DESC"
        )->fetchAll();
    }

    public function create($name, $email, $password, $role) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (fullname, email, password, role)
             VALUES (?,?,?,?)"
        );
        return $stmt->execute([$name, $email, $hashed, $role]);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $name, $email, $role) {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET fullname=?, email=?, role=? WHERE id=?"
        );
        return $stmt->execute([$name, $email, $role, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id=?");
        return $stmt->execute([$id]);
    }
}
