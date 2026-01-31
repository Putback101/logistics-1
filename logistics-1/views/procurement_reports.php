<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";

requireLogin();
requireRole(['admin','manager','procurement']);

$statuses = ["Approved","Sent","Received"];
$placeholders = implode(',', array_fill(0, count($statuses), '?'));

$sql = "
  SELECT
    COALESCE(s.name, po.supplier, 'Unknown Supplier') AS supplier,
    COUNT(*) AS po_count,
    SUM(po.total_amount) AS total
  FROM purchase_orders po
  LEFT JOIN suppliers s ON po.supplier_id = s.id
  WHERE po.status IN ($placeholders)
  GROUP BY supplier
  ORDER BY total DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($statuses);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grandTotal = 0;
foreach ($data as $d) $grandTotal += (float)$d['total'];
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>


  <?php require_once __DIR__ . "/layout/sidebar.php"; ?>

    <?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
      <h2 class="mb-2">Procurement Reports</h2>
      <p class="text-muted mb-4">
        Includes statuses: <?= htmlspecialchars(implode(', ', $statuses)) ?>
      </p>

      <div class="form-card mb-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Total Spend</div>
            <div class="fs-4 fw-bold">₱<?= number_format($grandTotal, 2) ?></div>
          </div>
          <div class="text-muted small">
            Suppliers: <?= (int)count($data) ?>
          </div>
        </div>
      </div>

      <div class="table-card">
        <h5 class="mb-3">Spend by Supplier</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Supplier</th>
                <th class="text-center">PO Count</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($data)): ?>
                <tr>
                  <td colspan="3" class="text-center text-muted">No data found.</td>
                </tr>
              <?php endif; ?>

              <?php foreach ($data as $d): ?>
                <tr>
                  <td><?= htmlspecialchars($d['supplier']) ?></td>
                  <td class="text-center"><?= (int)$d['po_count'] ?></td>
                  <td class="text-end">₱<?= number_format((float)$d['total'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <?php if (!empty($data)): ?>
              <tfoot>
                <tr>
                  <th>Total</th>
                  <th></th>
                  <th class="text-end">₱<?= number_format($grandTotal, 2) ?></th>
                </tr>
              </tfoot>
            <?php endif; ?>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<?php require_once __DIR__ . "/layout/footer.php"; ?>




