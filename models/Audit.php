<?php
class Audit {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $sql = "
            SELECT audit_logs.*, users.fullname, users.role
            FROM audit_logs
            JOIN users ON audit_logs.user_id = users.id
            ORDER BY audit_logs.log_time DESC
        ";
        return $this->pdo->query($sql)->fetchAll();
    }
}
