-- =========================================================
-- SUPPLY CHAIN DATABASE (Clean Structured)
-- (Structure/formatting only; logic + fields unchanged)
-- =========================================================

DROP DATABASE IF EXISTS supply_chain;
CREATE DATABASE supply_chain CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE supply_chain;

-- =========================================================
-- 1) CORE TABLES
-- =========================================================

-- USERS
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','staff','procurement','warehouse','project','mro','asset') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- SUPPLIERS
CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  contact_person VARCHAR(100),
  email VARCHAR(100),
  phone VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 2) FLEET + MAINTENANCE
-- =========================================================

CREATE TABLE fleet (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_name VARCHAR(100) NOT NULL,
  plate_number VARCHAR(50) NOT NULL UNIQUE,
  status ENUM('Available','In Use','Maintenance') NOT NULL DEFAULT 'Available',
  acquisition_date DATE NULL,
  asset_value DECIMAL(12,2) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE maintenance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fleet_id INT NOT NULL,
  type ENUM('Maintenance','Repair') NOT NULL,
  description TEXT,
  cost DECIMAL(12,2) DEFAULT 0,
  performed_at DATE,
  recorded_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_maintenance_fleet
    FOREIGN KEY (fleet_id) REFERENCES fleet(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_maintenance_recorded_by
    FOREIGN KEY (recorded_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 3) PROCUREMENT + PURCHASE ORDERS
-- =========================================================

-- Simple procurement requests (optional separate from PO flow)
CREATE TABLE procurement (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_name VARCHAR(100) NOT NULL,
  quantity INT NOT NULL,
  supplier VARCHAR(100),
  status ENUM('Pending','Approved','Delivered') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(50) NOT NULL UNIQUE,
  supplier VARCHAR(100) NULL,              -- optional legacy/free-text supplier
  supplier_id INT NULL,                    -- preferred relational supplier
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('Draft','Approved','Sent','Received') NOT NULL DEFAULT 'Draft',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_po_supplier
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_po_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  quantity INT NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,

  CONSTRAINT fk_poi_po
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE receiving (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  item_name VARCHAR(100) NOT NULL,
  quantity_received INT NOT NULL,
  received_by INT NULL,
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_receiving_po
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_receiving_received_by
    FOREIGN KEY (received_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 4) INVENTORY + RECONCILIATION
-- =========================================================

CREATE TABLE inventory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_name VARCHAR(100) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  location VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE stock_reconciliation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NULL,
  item_name VARCHAR(100) NOT NULL,
  system_stock INT NOT NULL DEFAULT 0,
  physical_stock INT NOT NULL DEFAULT 0,
  variance INT NOT NULL DEFAULT 0,
  reconciled_by INT NULL,
  reconciled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_recon_item
    FOREIGN KEY (item_id) REFERENCES inventory(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_recon_user
    FOREIGN KEY (reconciled_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 5) BUDGETS + APPROVALS
-- =========================================================

CREATE TABLE budgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  year INT NOT NULL UNIQUE,
  allocated DECIMAL(12,2) NOT NULL DEFAULT 0,
  spent DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module VARCHAR(50) NOT NULL,
  record_id INT NOT NULL,
  status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',

  requested_by INT NULL,
  approved_by INT NULL,     -- original field kept
  acted_by INT NULL,
  approved_at TIMESTAMP NULL,
  acted_at TIMESTAMP NULL,
  remarks VARCHAR(255) NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_approvals_requested_by
    FOREIGN KEY (requested_by) REFERENCES users(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_approvals_approved_by
    FOREIGN KEY (approved_by) REFERENCES users(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_approvals_acted_by
    FOREIGN KEY (acted_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 6) AUDIT TRAIL
-- =========================================================

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(255) NOT NULL,
  log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 7) PROJECTS + TASKS + RESOURCES
-- =========================================================

CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  type ENUM('General','Fleet Expansion') NOT NULL DEFAULT 'General',
  description TEXT,
  start_date DATE,
  end_date DATE,
  status ENUM('Planned','Ongoing','Completed','On Hold') NOT NULL DEFAULT 'Planned',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_projects_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE project_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  start_date DATE,
  due_date DATE,
  priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  status ENUM('Todo','In Progress','Done') NOT NULL DEFAULT 'Todo',
  assigned_user_id INT NULL,
  assigned_fleet_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_tasks_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_tasks_user
    FOREIGN KEY (assigned_user_id) REFERENCES users(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_tasks_fleet
    FOREIGN KEY (assigned_fleet_id) REFERENCES fleet(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE project_resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  resource_type ENUM('User','Fleet') NOT NULL,
  resource_id INT NOT NULL,
  role_label VARCHAR(80),
  allocated_from DATE,
  allocated_to DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_resources_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 8) OPTIONAL INDEXES (for speed)
-- =========================================================

CREATE INDEX idx_po_status        ON purchase_orders(status);
CREATE INDEX idx_po_supplier_id   ON purchase_orders(supplier_id);
CREATE INDEX idx_receiving_po_id  ON receiving(po_id);
CREATE INDEX idx_tasks_project_id ON project_tasks(project_id);
CREATE INDEX idx_audit_user_id    ON audit_logs(user_id);

-- =========================================================
-- 9) POST-CREATION PATCHES / UPGRADES (as-is, re-ordered only)
-- =========================================================

-- A) Fix role mismatch (your project uses procurement/warehouse in controllers)
ALTER TABLE users
  MODIFY role ENUM('admin','manager','staff','procurement','warehouse','project','mro','asset') NOT NULL DEFAULT 'staff';

-- B) Add lock field to purchase_orders
ALTER TABLE purchase_orders
  ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0;

-- C) Upgrade PO status to match workflow
ALTER TABLE purchase_orders
  MODIFY status ENUM('Draft','Pending Approval','Approved','Sent','Received','Rejected')
  NOT NULL DEFAULT 'Draft';

-- D) Upgrade audit_logs (keep your existing user_id/action columns; add tracking fields)
ALTER TABLE audit_logs
  ADD COLUMN entity_type VARCHAR(50) NULL,
  ADD COLUMN entity_id INT NULL,
  ADD COLUMN meta JSON NULL;

-- E) Extend PO status with Returned
ALTER TABLE purchase_orders
  MODIFY status ENUM('Draft','Pending Approval','Approved','Sent','Received','Rejected','Returned')
  NOT NULL DEFAULT 'Draft';

-- F) Receiving QC fields
ALTER TABLE receiving
  ADD COLUMN qc_status ENUM('PASS','FAIL') NOT NULL DEFAULT 'PASS',
  ADD COLUMN qc_notes VARCHAR(255) NULL;

-- G) Supplier returns (optional)
CREATE TABLE IF NOT EXISTS supplier_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  quantity INT NOT NULL,
  reason VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_returns_po
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_returns_user
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;


-- =========================================================
-- ASSET MANAGEMENT TABLES (Registry + Tracking + Monitoring)
-- =========================================================

CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_tag VARCHAR(60) NOT NULL UNIQUE,
  asset_name VARCHAR(120) NOT NULL,
  asset_category VARCHAR(80) NOT NULL,
  brand VARCHAR(80) NULL,
  model VARCHAR(80) NULL,
  serial_no VARCHAR(120) NULL,
  acquisition_date DATE NULL,
  purchase_cost DECIMAL(12,2) DEFAULT 0,
  status ENUM('Active','In Use','Idle','Under Maintenance','Retired') NOT NULL DEFAULT 'Active',
  location VARCHAR(120) NULL,
  assigned_to INT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_assets_assigned_to
    FOREIGN KEY (assigned_to) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asset_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  from_location VARCHAR(120) NULL,
  to_location VARCHAR(120) NULL,
  from_user INT NULL,
  to_user INT NULL,
  moved_by INT NULL,
  moved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  remarks VARCHAR(255) NULL,

  CONSTRAINT fk_asset_movements_asset
    FOREIGN KEY (asset_id) REFERENCES assets(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_asset_movements_from_user
    FOREIGN KEY (from_user) REFERENCES users(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_asset_movements_to_user
    FOREIGN KEY (to_user) REFERENCES users(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_asset_movements_moved_by
    FOREIGN KEY (moved_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asset_monitor_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  condition_status ENUM('Good','Needs Inspection','Needs Maintenance') NOT NULL DEFAULT 'Good',
  usage_hours DECIMAL(10,2) DEFAULT 0,
  last_inspected DATE NULL,
  next_inspection DATE NULL,
  recorded_by INT NULL,
  remarks VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_asset_monitor_asset
    FOREIGN KEY (asset_id) REFERENCES assets(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_asset_monitor_recorded_by
    FOREIGN KEY (recorded_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;


-- 1) Drop old FK that requires fleet_id NOT NULL
ALTER TABLE maintenance_logs DROP FOREIGN KEY fk_maintenance_fleet;

-- 2) Allow fleet_id to be NULL and add asset_id
ALTER TABLE maintenance_logs
  MODIFY fleet_id INT NULL,
  ADD COLUMN asset_id INT NULL AFTER fleet_id;

-- 3) Add FK back for fleet_id (nullable)
ALTER TABLE maintenance_logs
  ADD CONSTRAINT fk_maintenance_fleet
    FOREIGN KEY (fleet_id) REFERENCES fleet(id)
    ON DELETE SET NULL;

-- 4) Add FK for asset_id
ALTER TABLE maintenance_logs
  ADD CONSTRAINT fk_maintenance_asset
    FOREIGN KEY (asset_id) REFERENCES assets(id)
    ON DELETE SET NULL;

-- ✅ Add Asset assignment to tasks
ALTER TABLE project_tasks
  ADD COLUMN assigned_asset_id INT NULL AFTER assigned_fleet_id;

ALTER TABLE project_tasks
  ADD CONSTRAINT fk_tasks_asset
  FOREIGN KEY (assigned_asset_id) REFERENCES assets(id)
  ON DELETE SET NULL;

-- ✅ Allow Asset in resource allocation type
ALTER TABLE project_resources
  MODIFY resource_type ENUM('User','Fleet','Asset') NOT NULL;
