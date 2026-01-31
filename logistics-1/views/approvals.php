<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Approval.php";

requireLogin();

$role   = $_SESSION['user']['role'] ?? 'guest';
$userId = (int)($_SESSION['user']['id'] ?? 0);

// Allowed roles to view this page (matches your sidebar "Procurement" group)
if (!in_array($role, ['admin','manager','procurement'], true)) {
  http_response_code(403);
     exit;
}

$a = new Approval($pdo);

// Detect approval table columns (your Approval model already adapts to schemas)
$cols = $pdo->query("SHOW COLUMNS FROM approvals")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_map(fn($r) => $r['Field'], $cols);

$hasRequestedBy = in_array('requested_by', $colNames, true);
$hasActedBy     = in_array('acted_by', $colNames, true);
$hasActedAt     = in_array('acted_at', $colNames, true);
$hasRemarks     = in_array('remarks', $colNames, true);

function badgeClass(string $status): string {
  return match ($status) {
    'Approved' => 'bg-success',
    'Rejected' => 'bg-danger',
    'Pending'  => 'bg-warning text-dark',
    default    => 'bg-secondary'
  };
}

/**
 * ADMIN VIEW:
 * Show "Pending Approval" purchase orders with Approve/Reject buttons.
 * Your workflow uses:
 * - approvals.status = 'Pending'
 * - purchase_orders.status = 'Pending Approval'
 */
$pendingPOs = [];
if ($role === 'admin') {
  // Build select fields based on optional cols
  $sel = "
    ap.id AS approval_id,
    ap.module,
    ap.record_id,
    ap.status AS approval_status,
    po.id AS po_id,
    po.po_number,
    po.total_amount,
    po.status AS po_status,
    po.created_at
  ";
  if ($hasRequestedBy) $sel .= ", ap.requested_by";
  if ($hasActedBy)     $sel .= ", ap.acted_by";
  if ($hasActedAt)     $sel .= ", ap.acted_at";
  if ($hasRemarks)     $sel .= ", ap.remarks";

  $sql = "
    SELECT $sel
    FROM approvals ap
    JOIN purchase_orders po ON po.id = ap.record_id
    WHERE ap.module = 'purchase_orders'
      AND ap.status = 'Pending'
      AND po.status = 'Pending Approval'
    ORDER BY ap.id DESC
  ";

  $pendingPOs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * REQUESTER VIEW (MANAGER/PROCUREMENT):
 * Show "My Requests" so the Approvals page is still useful even without buttons.
 * If approvals table has no requested_by, we show a read-only list of latest requests instead.
 */
$myRequests = [];
if (in_array($role, ['manager','procurement'], true)) {
  $sel = "
    ap.id AS approval_id,
    ap.module,
    ap.record_id,
    ap.status AS approval_status,
    po.id AS po_id,
    po.po_number,
    po.total_amount,
    po.status AS po_status,
    po.created_at
  ";
  if ($hasRequestedBy) $sel .= ", ap.requested_by";
  if ($hasActedBy)     $sel .= ", ap.acted_by";
  if ($hasActedAt)     $sel .= ", ap.acted_at";
  if ($hasRemarks)     $sel .= ", ap.remarks";

  if ($hasRequestedBy) {
    $stmt = $pdo->prepare("
      SELECT $sel
      FROM approvals ap
      JOIN purchase_orders po ON po.id = ap.record_id
      WHERE ap.module = 'purchase_orders'
        AND ap.requested_by = ?
      ORDER BY ap.id DESC
    ");
    $stmt->execute([$userId]);
    $myRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    // Fallback if requested_by isn't available: show latest approvals (read-only)
    $myRequests = $pdo->query("
      SELECT $sel
      FROM approvals ap
      JOIN purchase_orders po ON po.id = ap.record_id
      WHERE ap.module = 'purchase_orders'
      ORDER BY ap.id DESC
      LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>


  <?php require_once __DIR__ . "/layout/sidebar.php"; ?>

    <?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <h2 class="mb-2">Approval Workflows</h2>
      <p class="text-muted mb-4">
        <?php if ($role === 'admin'): ?>
          Review pending approvals and take action.
        <?php else: ?>
          Track approval status for your submitted requests.
        <?php endif; ?>
      </p>

      <?php if ($role === 'admin'): ?>
        <div class="table-card mb-4">
          <h5 class="mb-3">Pending Purchase Order Approvals</h5>

          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>PO #</th>
                  <th>PO Status</th>
                  <th class="text-end">Total</th>
                  <th>Approval</th>
                  <th style="width: 380px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pendingPOs)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted">No pending approvals.</td>
                  </tr>
                <?php endif; ?>

                <?php foreach ($pendingPOs as $row): ?>
                  <tr>
                    <td>
                      <a href="purchase_order_view.php?id=<?= (int)$row['po_id'] ?>">
                        <?= htmlspecialchars($row['po_number']) ?>
                      </a>
                    </td>
                    <td>
                      <span class="badge bg-warning text-dark">
                        <?= htmlspecialchars($row['po_status']) ?>
                      </span>
                    </td>
                    <td class="text-end">₱<?= number_format((float)$row['total_amount'], 2) ?></td>
                    <td>
                      <span class="badge <?= badgeClass($row['approval_status']) ?>">
                        <?= htmlspecialchars($row['approval_status']) ?>
                      </span>
                    </td>
                    <td>
                      <form method="POST" action="../controllers/PurchaseOrderController.php" class="d-flex gap-2">
                        <input type="hidden" name="po_id" value="<?= (int)$row['po_id'] ?>">
                        <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
                        <button class="btn btn-success" name="approve" title="Approve">
                          <i class="bi bi-check2"></i>
                        </button>
                        <button class="btn btn-danger" name="reject" title="Reject">
                          <i class="bi bi-x"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="small text-muted mt-2">
            Approving locks the PO and continues the workflow (Approved → Sent → Received).
          </div>
        </div>
      <?php endif; ?>

      <?php if (in_array($role, ['manager','procurement'], true)): ?>
        <div class="table-card">
          <h5 class="mb-3">My Requests</h5>

          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>PO #</th>
                  <th>Approval</th>
                  <th>PO Status</th>
                  <th class="text-end">Total</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($myRequests)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted">No requests yet.</td>
                  </tr>
                <?php endif; ?>

                <?php foreach ($myRequests as $row): ?>
                  <tr>
                    <td>
                      <a href="purchase_order_view.php?id=<?= (int)$row['po_id'] ?>">
                        <?= htmlspecialchars($row['po_number']) ?>
                      </a>
                    </td>
                    <td>
                      <span class="badge <?= badgeClass($row['approval_status']) ?>">
                        <?= htmlspecialchars($row['approval_status']) ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($row['po_status']) ?></td>
                    <td class="text-end">₱<?= number_format((float)$row['total_amount'], 2) ?></td>
                    <td class="text-muted">
                      <?= htmlspecialchars(($hasRemarks && isset($row['remarks']) && $row['remarks'] !== '') ? $row['remarks'] : '-') ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="small text-muted mt-2">
            Only Admin can approve/reject. This page lets you monitor progress.
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</main>

<?php require_once __DIR__ . "/layout/footer.php"; ?>




