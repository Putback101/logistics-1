<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  header("Content-Type: application/json");
  echo json_encode(["error" => "Not logged in"]);
  exit;
}

if (!isset($_GET['po_id']) || !ctype_digit($_GET['po_id'])) {
  http_response_code(400);
  header("Content-Type: application/json");
  echo json_encode(["error" => "Invalid po_id"]);
  exit;
}

$poId = (int)$_GET['po_id'];

try {
  /**
   * IMPORTANT:
   * - Your PO items table is: purchase_order_items
   * - Your foreign key column is: po_id
   * - We alias fields to EXACT names used by JS:
   *   po_qty, received_qty, remaining_qty
   */
  $stmt = $pdo->prepare("
    SELECT
      i.item_name AS item_name,
      i.quantity  AS po_qty,
      COALESCE(SUM(r.quantity_received), 0) AS received_qty,
      (i.quantity - COALESCE(SUM(r.quantity_received), 0)) AS remaining_qty
    FROM purchase_order_items i
    LEFT JOIN receiving r
      ON r.po_id = i.po_id
     AND r.item_name = i.item_name
     AND (r.qc_status IS NULL OR r.qc_status = 'PASS') -- count only PASS if qc exists
    WHERE i.po_id = ?
    GROUP BY i.item_name, i.quantity
    HAVING remaining_qty > 0
    ORDER BY i.item_name ASC
  ");
  $stmt->execute([$poId]);

  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  header("Content-Type: application/json");
  echo json_encode($items);
} catch (Throwable $e) {
  http_response_code(500);
  header("Content-Type: application/json");
  echo json_encode(["error" => $e->getMessage()]);
}
