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
  $stmt = $pdo->prepare("\n    SELECT\n      i.id AS po_item_id,\n      i.item_id AS item_id,\n      i.item_name AS item_name,\n      i.quantity  AS po_qty,\n      COALESCE(SUM(r.quantity_received), 0) AS received_qty,\n      (i.quantity - COALESCE(SUM(r.quantity_received), 0)) AS remaining_qty\n    FROM purchase_order_items i\n    LEFT JOIN receiving r\n      ON r.po_id = i.po_id\n     AND r.item_name = i.item_name\n     AND (r.qc_status IS NULL OR r.qc_status = 'PASS')\n    WHERE i.po_id = ?\n    GROUP BY i.id, i.item_id, i.item_name, i.quantity\n    HAVING remaining_qty > 0\n    ORDER BY i.item_name ASC, i.id ASC\n  ");
  $stmt->execute([$poId]);

  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  header("Content-Type: application/json");
  echo json_encode($items);
} catch (Throwable $e) {
  http_response_code(500);
  header("Content-Type: application/json");
  echo json_encode(["error" => $e->getMessage()]);
}
