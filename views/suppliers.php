<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Supplier.php";

requireLogin();

$role = $_SESSION['user']['role'] ?? '';
$canAdd = in_array($role, ['admin','manager'], true);
$isAdmin = ($role === 'admin');

$supplier = new Supplier($pdo);
$rows = $supplier->getAll();
?>

<?php require_once __DIR__ . "/layout/header.php"; ?>

<?php require_once __DIR__ . "/layout/sidebar.php"; ?>
<?php require_once __DIR__ . "/layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h2 class="mb-1">Suppliers</h2>
          <p class="text-muted mb-0">Manage supplier records used in procurement and purchase orders.</p>
        </div>

        <?php if ($canAdd): ?>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="bi bi-plus-circle"></i> Add Supplier
          </button>
        <?php endif; ?>
      </div>

      <div class="table-card mt-4">
        <h5 class="mb-3">Supplier List</h5>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Phone</th>
                <?php if ($isAdmin): ?>
                  <th style="width: 160px;">Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="<?= $isAdmin ? 5 : 4 ?>" class="text-center text-muted">
                    No suppliers found.
                  </td>
                </tr>
              <?php endif; ?>

              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td><?= htmlspecialchars($r['contact_person'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($r['email'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($r['phone'] ?? '-') ?></td>

                  <?php if ($isAdmin): ?>
                    <td class="d-flex gap-2">
                      <!-- EDIT (modal) -->
                      <button
                        class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#editSupplierModal"
                        data-id="<?= (int)$r['id'] ?>"
                        data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>"
                        data-contact="<?= htmlspecialchars($r['contact_person'] ?? '', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($r['email'] ?? '', ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($r['phone'] ?? '', ENT_QUOTES) ?>"
                      >
                        <i class="bi bi-pencil"></i>
                      </button>

                      <!-- DELETE (POST) -->
                      <form method="POST" action="../controllers/SupplierController.php"
                            onsubmit="return confirm('Delete this supplier? This cannot be undone.');">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" name="delete_supplier">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if (!$isAdmin): ?>
          <div class="small text-muted mt-2">
            You have read-only access. Only Admin can edit or delete suppliers.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ADD SUPPLIER MODAL -->
    <?php if ($canAdd): ?>
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="../controllers/SupplierController.php">
            <div class="modal-header">
              <h5 class="modal-title">Add Supplier</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Supplier Name</label>
                  <input name="name" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Contact Person</label>
                  <input name="contact_person" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input name="phone" class="form-control">
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button class="btn btn-primary" name="add_supplier">
                <i class="bi bi-plus-circle"></i> Add
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- EDIT SUPPLIER MODAL (ADMIN ONLY) -->
    <?php if ($isAdmin): ?>
    <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="../controllers/SupplierController.php">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
              <h5 class="modal-title">Edit Supplier</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Supplier Name</label>
                  <input name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Contact Person</label>
                  <input name="contact_person" id="edit_contact_person" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input name="phone" id="edit_phone" class="form-control">
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button class="btn btn-primary" name="update_supplier">
                <i class="bi bi-save"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <script>
      // Fill Edit Modal from button data-*
      const editModal = document.getElementById('editSupplierModal');
      editModal?.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;

        document.getElementById('edit_id').value = btn.getAttribute('data-id') || '';
        document.getElementById('edit_name').value = btn.getAttribute('data-name') || '';
        document.getElementById('edit_contact_person').value = btn.getAttribute('data-contact') || '';
        document.getElementById('edit_email').value = btn.getAttribute('data-email') || '';
        document.getElementById('edit_phone').value = btn.getAttribute('data-phone') || '';
      });
    </script>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . "/layout/footer.php"; ?>


