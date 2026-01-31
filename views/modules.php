<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/app.php";

requireLogin();

$base = app_base_url();
$role = $_SESSION['user']['role'] ?? 'staff';
?>

<?php include "layout/header.php"; ?>
<?php include "layout/sidebar.php"; ?>
<?php include "layout/topbar.php"; ?>

<main class="main-content">
  <div class="content-area">

    <h2 class="mb-1" style="font-size: 2rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Logistics Management System</h2>
    <p class="text-muted mb-4">Select a module to manage your operations.</p>

    <!-- MODULES GRID -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 2rem;">

      <!-- PROCUREMENT MODULE -->
      <?php if (in_array($role, ['admin', 'manager', 'procurement'])): ?>
      <div style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); border: 2px solid #48bb78; border-radius: 1rem; padding: 2rem; transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 0 30px rgba(72, 187, 120, 0.3)'; this.style.transform='translateY(-5px)';" onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
        <div style="font-size: 3rem; margin-bottom: 1rem; color: #48bb78;">📋</div>
        <h3 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Procurement</h3>
        <p style="color: #a0aec0; font-size: 0.95rem; margin-bottom: 1.5rem;">Manage purchase orders, suppliers, budgets, and procurement reports.</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
          <a href="<?= $base ?>/views/purchase_orders.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Purchase Orders</a>
          <a href="<?= $base ?>/views/suppliers.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Suppliers</a>
          <a href="<?= $base ?>/views/budgets.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Budget</a>
          <a href="<?= $base ?>/views/procurement_reports.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Reports</a>
        </div>
      </div>
      <?php endif; ?>

      <!-- PROJECTS MODULE -->
      <?php if (in_array($role, ['admin', 'manager', 'project'])): ?>
      <div style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); border: 2px solid #48bb78; border-radius: 1rem; padding: 2rem; transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 0 30px rgba(72, 187, 120, 0.3)'; this.style.transform='translateY(-5px)';" onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
        <div style="font-size: 3rem; margin-bottom: 1rem; color: #48bb78;">📊</div>
        <h3 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Projects</h3>
        <p style="color: #a0aec0; font-size: 0.95rem; margin-bottom: 1.5rem;">Manage projects, timelines, tasks, and resource allocation.</p>
        <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem;">
          <a href="<?= $base ?>/views/projects.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Project Management</a>
        </div>
      </div>
      <?php endif; ?>

      <!-- ASSETS MODULE -->
      <?php if (in_array($role, ['admin', 'manager', 'asset'])): ?>
      <div style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); border: 2px solid #48bb78; border-radius: 1rem; padding: 2rem; transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 0 30px rgba(72, 187, 120, 0.3)'; this.style.transform='translateY(-5px)';" onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
        <div style="font-size: 3rem; margin-bottom: 1rem; color: #48bb78;">🚗</div>
        <h3 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Assets</h3>
        <p style="color: #a0aec0; font-size: 0.95rem; margin-bottom: 1.5rem;">Manage fleet, track assets, and monitor equipment performance.</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
          <a href="<?= $base ?>/views/fleet.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Fleet</a>
          <a href="<?= $base ?>/views/asset_tracking.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Tracking</a>
          <a href="<?= $base ?>/views/asset_monitoring.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Monitoring</a>
        </div>
      </div>
      <?php endif; ?>

      <!-- MRO MODULE -->
      <?php if (in_array($role, ['admin', 'manager', 'mro'])): ?>
      <div style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); border: 2px solid #48bb78; border-radius: 1rem; padding: 2rem; transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 0 30px rgba(72, 187, 120, 0.3)'; this.style.transform='translateY(-5px)';" onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
        <div style="font-size: 3rem; margin-bottom: 1rem; color: #48bb78;">🔧</div>
        <h3 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">MRO</h3>
        <p style="color: #a0aec0; font-size: 0.95rem; margin-bottom: 1.5rem;">Manage maintenance and repair operations for equipment.</p>
        <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem;">
          <a href="<?= $base ?>/views/maintenance.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Maintenance</a>
        </div>
      </div>
      <?php endif; ?>

      <!-- WAREHOUSING MODULE -->
      <?php if (in_array($role, ['admin', 'manager', 'warehouse'])): ?>
      <div style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); border: 2px solid #48bb78; border-radius: 1rem; padding: 2rem; transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 0 30px rgba(72, 187, 120, 0.3)'; this.style.transform='translateY(-5px)';" onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
        <div style="font-size: 3rem; margin-bottom: 1rem; color: #48bb78;">📦</div>
        <h3 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Warehousing</h3>
        <p style="color: #a0aec0; font-size: 0.95rem; margin-bottom: 1.5rem;">Manage inventory, receiving, and stock reconciliation.</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
          <a href="<?= $base ?>/views/inventory.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Inventory</a>
          <a href="<?= $base ?>/views/receiving.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Receiving</a>
          <a href="<?= $base ?>/views/stock_reconciliation.php" style="padding: 0.75rem; background: #2d3748; border: 1px solid #4a5568; border-radius: 0.5rem; color: #e2e8f0; text-decoration: none; text-align: center; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#48bb78'; this.style.color='#000';" onmouseout="this.style.background='#2d3748'; this.style.color='#e2e8f0';">Reconciliation</a>
        </div>
      </div>
      <?php endif; ?>

    </div>

  </div>
</main>

<?php include "layout/footer.php"; ?>
