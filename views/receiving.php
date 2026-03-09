<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Receiving.php";

requireLogin();
requireRole(['admin','manager','warehouse']);

$recv = new Receiving($pdo);
$rows = $recv->getAll();

// Only receivable POs show up (Sent / Returned)
$pos = $pdo->query("
  SELECT id, po_number
  FROM purchase_orders
  WHERE status IN ('Sent','Returned')
  ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>

<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <h2 class="mb-2">Receiving</h2>
      <p class="text-muted mb-4">
        Select a PO to load all items. Enter received quantities per item, then save.
      </p>

      <!-- RECEIVING FORM -->
      <div class="form-card mb-4">
        <h5 class="mb-3">Record Receiving</h5>

        <form method="POST" action="../controllers/ReceivingController.php" class="row g-3">

          <!-- PO -->
          <div class="col-md-4">
            <label class="form-label">Purchase Order Reference</label>
            <select name="po_id" id="po_id" class="form-select" required>
              <option value="">Select</option>
              <?php foreach($pos as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['po_number']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- QC STATUS (applies to the whole receiving batch) -->
          <div class="col-md-3">
            <label class="form-label">Quality Check</label>
            <select name="qc_status" id="qc_status" class="form-select" required>
              <option value="PASS">PASS</option>
              <option value="FAIL">FAIL</option>
            </select>
          </div>

          <!-- QC NOTES -->
          <div class="col-md-5">
            <label class="form-label">QC Notes (required if FAIL)</label>
            <input
              name="qc_notes"
              id="qc_notes"
              class="form-control"
              placeholder="Damaged, wrong item, missing parts, etc."
            >
          </div>

          <!-- Items Table -->
          <div class="col-12">
            <table class="table table-bordered align-middle" id="po_items_table">
              <thead class="table-light">
                <tr>
                  <th style="width: 45%;">Item</th>
                  <th style="width: 15%;">PO Qty</th>
                  <th style="width: 15%;">Remaining</th>
                  <th style="width: 25%;">Qty Received</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="4" class="text-muted text-center">
                    Select a PO to load items
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="small text-muted">
              Tip: You can partially receive. Qty Received cannot exceed Remaining.
            </div>
          </div>

          <!-- SUBMIT -->
          <div class="col-12">
            <button class="btn btn-primary w-100" name="receive" id="save_btn" disabled>
              <i class="bi bi-box-arrow-in-down"></i> Save Receiving
            </button>
          </div>

        </form>
      </div>

      <!-- RECEIVING LOGS -->
      <div class="table-card">
        <h5 class="mb-3">Receiving Logs</h5>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>PO</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Quality</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted">
                    No receiving records yet.
                  </td>
                </tr>
              <?php endif; ?>

              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="text-muted small"><?= htmlspecialchars($r['received_at']) ?></td>
                  <td><?= htmlspecialchars($r['po_id'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($r['item_name']) ?></td>
                  <td><?= (int)$r['quantity_received'] ?></td>
                  <td>
                    <span class="badge <?= ($r['qc_status'] ?? 'PASS') === 'PASS' ? 'bg-success' : 'bg-danger' ?>">
                      <?= htmlspecialchars($r['qc_status'] ?? 'PASS') ?>
                    </span>
                  </td>
                  <td class="text-muted"><?= htmlspecialchars($r['qc_notes'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ONE SCRIPT ONLY (no duplicates) -->
    <script>
      const poSelect = document.getElementById('po_id');
      const tbody = document.querySelector('#po_items_table tbody');
      const saveBtn = document.getElementById('save_btn');
      const qcStatus = document.getElementById('qc_status');
      const qcNotes = document.getElementById('qc_notes');

      function setTableMessage(msg) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">${msg}</td></tr>`;
        saveBtn.disabled = true;
      }

      // QC notes required only if FAIL
      function enforceQcNotes() {
        if (qcStatus.value === 'FAIL') {
          qcNotes.required = true;
        } else {
          qcNotes.required = false;
        }
      }
      qcStatus.addEventListener('change', enforceQcNotes);
      enforceQcNotes();

      setTableMessage("Select a PO to load items");

      poSelect.addEventListener('change', async () => {
        const poId = poSelect.value;

        if (!poId) {
          setTableMessage("Select a PO to load items");
          return;
        }

        setTableMessage("Loading...");

        try {
          const res = await fetch(`../controllers/get_po_items.php?po_id=${encodeURIComponent(poId)}`);
          const contentType = res.headers.get("content-type") || "";

          if (!res.ok) {
            const txt = await res.text();
            console.error("Server error:", txt);
            setTableMessage("Failed to load items");
            return;
          }
          if (!contentType.includes("application/json")) {
            const txt = await res.text();
            console.error("Expected JSON, got:", txt);
            setTableMessage("Invalid server response");
            return;
          }

          const items = await res.json();

          if (!Array.isArray(items) || items.length === 0) {
            setTableMessage("All items already received");
            return;
          }

          tbody.innerHTML = items.map((it, idx) => `
            <tr>
              <td>
                ${it.item_name}
                <input type="hidden" name="items[${idx}][name]" value="${String(it.item_name).replaceAll('"','&quot;')}">
              </td>
              <td>${it.po_qty}</td>
              <td>${it.remaining_qty}</td>
              <td>
                <input
                  type="number"
                  name="items[${idx}][qty]"
                  class="form-control"
                  min="1"
                  max="${it.remaining_qty}"
                  value="${it.remaining_qty}"
                  required
                >
              </td>
            </tr>
          `).join('');

          saveBtn.disabled = false;

        } catch (e) {
          console.error(e);
          setTableMessage("Failed to load items");
        }
      });
    </script>

  </div>
</main>

<?php require_once __DIR__ . "/layout/footer.php"; ?>


