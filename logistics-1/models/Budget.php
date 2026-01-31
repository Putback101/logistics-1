<?php
class Budget {
  private $pdo;
  public function __construct($pdo){ $this->pdo = $pdo; }

  public function getAll(): array {
    return $this->pdo->query("SELECT * FROM budgets ORDER BY year DESC")->fetchAll();
  }

  public function getByYear(int $year) {
    $stmt = $this->pdo->prepare("SELECT * FROM budgets WHERE year=?");
    $stmt->execute([$year]);
    return $stmt->fetch();
  }

  public function upsert(int $year, float $allocated): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO budgets (year, allocated) VALUES (?,?)
      ON DUPLICATE KEY UPDATE allocated=VALUES(allocated)
    ");
    $stmt->execute([$year,$allocated]);
  }

  public function addSpent(int $year, float $amount): void {
    $stmt = $this->pdo->prepare("UPDATE budgets SET spent = spent + ? WHERE year=?");
    $stmt->execute([$amount,$year]);
  }
}
