<?php

class Receiving {
  private PDO $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  /**
   * Create a receiving log entry.
   * Compatible with your system + supports QC fields (PASS/FAIL).
   */
  public function create($poId, string $itemName, int $qtyReceived, int $receivedBy, string $qcStatus = 'PASS', ?string $qcNotes = null): void {
    $qcStatus = strtoupper(trim($qcStatus));
    if (!in_array($qcStatus, ['PASS', 'FAIL'], true)) $qcStatus = 'PASS';

    $stmt = $this->pdo->prepare("
      INSERT INTO receiving (po_id, item_name, quantity_received, received_by, qc_status, qc_notes, received_at)
      VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
      $poId === '' ? null : $poId,
      $itemName,
      $qtyReceived,
      $receivedBy,
      $qcStatus,
      $qcNotes
    ]);
  }

  /**
   * Fetch all receiving logs for the receiving page.
   */
  public function getAll(): array {
    $stmt = $this->pdo->query("
      SELECT
        r.po_id,
        po.po_number,
        item_name,
        quantity_received,
        qc_status,
        qc_notes,
        received_at
      FROM receiving r
      LEFT JOIN purchase_orders po ON po.id = r.po_id
      ORDER BY received_at DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
