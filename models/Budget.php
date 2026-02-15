<?php
class Budget {
  private PDO $pdo;

  public function __construct(PDO $pdo){
    $this->pdo = $pdo;
  }

  public function getAll(): array {
    return $this->pdo->query("SELECT * FROM budgets ORDER BY year DESC")->fetchAll();
  }

  public function getByYear(int $year) {
    $stmt = $this->pdo->prepare("SELECT * FROM budgets WHERE year=?");
    $stmt->execute([$year]);
    return $stmt->fetch();
  }

  public function upsert(int $year, float $allocated, ?float $spent = null): void {
    if ($spent === null) {
      $stmt = $this->pdo->prepare("\n        INSERT INTO budgets (year, allocated) VALUES (?,?)\n        ON DUPLICATE KEY UPDATE allocated=VALUES(allocated)\n      ");
      $stmt->execute([$year,$allocated]);
      return;
    }

    $stmt = $this->pdo->prepare("\n      INSERT INTO budgets (year, allocated, spent) VALUES (?,?,?)\n      ON DUPLICATE KEY UPDATE allocated=VALUES(allocated), spent=VALUES(spent)\n    ");
    $stmt->execute([$year,$allocated,$spent]);
  }

  public function addSpent(int $year, float $amount): void {
    $stmt = $this->pdo->prepare("UPDATE budgets SET spent = spent + ? WHERE year=?");
    $stmt->execute([$amount,$year]);
  }
}
