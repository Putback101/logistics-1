<?php
class Project {
  private $pdo;
  public function __construct($pdo){ $this->pdo = $pdo; }

  public function getAll(): array {
    return $this->pdo->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll();
  }

  public function getById(int $id) {
    $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch();
  }

  public function create($name,$type,$desc,$start,$end,$status,$createdBy): void {
    $stmt = $this->pdo->prepare("INSERT INTO projects (name,type,description,start_date,end_date,status,created_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$name,$type,$desc,$start ?: null,$end ?: null,$status,$createdBy]);
  }

  public function update($id,$name,$type,$desc,$start,$end,$status): void {
    $stmt = $this->pdo->prepare("UPDATE projects SET name=?, type=?, description=?, start_date=?, end_date=?, status=? WHERE id=?");
    $stmt->execute([$name,$type,$desc,$start ?: null,$end ?: null,$status,$id]);
  }

  public function delete(int $id): void {
    $stmt = $this->pdo->prepare("DELETE FROM projects WHERE id=?");
    $stmt->execute([$id]);
  }
}
