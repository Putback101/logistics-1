SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `supply_chain` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `supply_chain`;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `approvals`;
DROP TABLE IF EXISTS `asset_monitor_logs`;
DROP TABLE IF EXISTS `asset_movements`;
DROP TABLE IF EXISTS `maintenance_logs`;
DROP TABLE IF EXISTS `project_tasks`;
DROP TABLE IF EXISTS `project_resources`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `purchase_order_items`;
DROP TABLE IF EXISTS `receiving`;
DROP TABLE IF EXISTS `supplier_returns`;
DROP TABLE IF EXISTS `stock_reconciliation`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `procurement`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `assets`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `budgets`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE `users` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','procurement_staff','project_staff','mro_staff','asset','warehouse_staff') NOT NULL DEFAULT 'project_staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'System Admin', 'admin@example.com', '$2y$10$/dpjGOoOOCL2B8pFcpkmkOra54SYgf1Z5B..9Sho2G2xGHvtHfCbG', 'admin', '2026-02-08 12:12:04'),
(2, 'Operations Manager', 'manager@example.com', '$2y$10$/dpjGOoOOCL2B8pFcpkmkOra54SYgf1Z5B..9Sho2G2xGHvtHfCbG', 'manager', '2026-02-08 12:12:04'),
(3, 'Procurement Staff', 'procurement.staff@example.com', '$2y$10$/dpjGOoOOCL2B8pFcpkmkOra54SYgf1Z5B..9Sho2G2xGHvtHfCbG', 'procurement_staff', '2026-02-08 12:12:04'),
(4, 'Project Staff', 'project.staff@example.com', '$2y$10$/dpjGOoOOCL2B8pFcpkmkOra54SYgf1Z5B..9Sho2G2xGHvtHfCbG', 'project_staff', '2026-02-08 12:12:04'),
(5, 'Assets Staff', 'assets.staff@example.com', '$2y$10$/dpjGOoOOCL2B8pFcpkmkOra54SYgf1Z5B..9Sho2G2xGHvtHfCbG', 'asset', '2026-02-08 12:12:04'),
(6, 'MRO Staff', 'mro.staff@example.com', '$2y$10$/dpjGOoOOCL2B8pFcpkmkOra54SYgf1Z5B..9Sho2G2xGHvtHfCbG', 'mro_staff', '2026-02-08 12:12:04'),
(7, 'Warehouse Staff', 'warehouse.staff@example.com', '$2y$10$/dpjGOoOOCL2B8pFcpkmkOra54SYgf1Z5B..9Sho2G2xGHvtHfCbG', 'warehouse_staff', '2026-02-08 12:12:04');

CREATE TABLE `budgets` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `allocated` decimal(12,2) NOT NULL DEFAULT 0.00,
  `spent` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `items` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fleet` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_name` varchar(100) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `status` enum('Available','In Use','Maintenance') NOT NULL DEFAULT 'Available',
  `acquisition_date` date DEFAULT NULL,
  `asset_value` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `plate_number` (`plate_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assets` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(60) NOT NULL,
  `asset_name` varchar(120) NOT NULL,
  `asset_category` varchar(80) NOT NULL,
  `brand` varchar(80) DEFAULT NULL,
  `model` varchar(80) DEFAULT NULL,
  `serial_no` varchar(120) DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `purchase_cost` decimal(12,2) DEFAULT 0.00,
  `status` enum('Active','In Use','Idle','Under Maintenance','Retired') NOT NULL DEFAULT 'Active',
  `location` varchar(120) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_tag` (`asset_tag`),
  KEY `fk_assets_assigned_to` (`assigned_to`),
  CONSTRAINT `fk_assets_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projects` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `type` enum('General','Fleet Expansion') NOT NULL DEFAULT 'General',
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Planned','Ongoing','Completed','On Hold') NOT NULL DEFAULT 'Planned',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_projects_created_by` (`created_by`),
  CONSTRAINT `fk_projects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approvals` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `acted_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `acted_at` timestamp NULL DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_approvals_requested_by` (`requested_by`),
  KEY `fk_approvals_approved_by` (`approved_by`),
  KEY `fk_approvals_acted_by` (`acted_by`),
  CONSTRAINT `fk_approvals_acted_by` FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_approvals_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_approvals_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `log_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user_id` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `asset_monitor_logs` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `condition_status` enum('Good','Needs Inspection','Needs Maintenance') NOT NULL DEFAULT 'Good',
  `usage_hours` decimal(10,2) DEFAULT 0.00,
  `last_inspected` date DEFAULT NULL,
  `next_inspection` date DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_asset_monitor_asset` (`asset_id`),
  KEY `fk_asset_monitor_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_asset_monitor_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asset_monitor_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `asset_movements` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `from_location` varchar(120) DEFAULT NULL,
  `to_location` varchar(120) DEFAULT NULL,
  `from_user` int(11) DEFAULT NULL,
  `to_user` int(11) DEFAULT NULL,
  `moved_by` int(11) DEFAULT NULL,
  `moved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_asset_movements_asset` (`asset_id`),
  KEY `fk_asset_movements_from_user` (`from_user`),
  KEY `fk_asset_movements_to_user` (`to_user`),
  KEY `fk_asset_movements_moved_by` (`moved_by`),
  CONSTRAINT `fk_asset_movements_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asset_movements_from_user` FOREIGN KEY (`from_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asset_movements_moved_by` FOREIGN KEY (`moved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asset_movements_to_user` FOREIGN KEY (`to_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `location` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `item_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_inventory_item` (`item_id`),
  CONSTRAINT `fk_inventory_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `maintenance_logs` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fleet_id` int(11) DEFAULT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `type` enum('Maintenance','Repair') NOT NULL,
  `description` text DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT 0.00,
  `performed_at` date DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_maintenance_recorded_by` (`recorded_by`),
  KEY `fk_maintenance_fleet` (`fleet_id`),
  KEY `fk_maintenance_asset` (`asset_id`),
  CONSTRAINT `fk_maintenance_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_maintenance_fleet` FOREIGN KEY (`fleet_id`) REFERENCES `fleet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_maintenance_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `procurement` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Approved','Delivered') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `request_ref` varchar(40) DEFAULT NULL,
  `budget_year` int(11) DEFAULT NULL,
  `estimated_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `po_number` varchar(50) DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `budget_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_proc_requested_by` (`requested_by`),
  KEY `fk_proc_supplier` (`supplier_id`),
  KEY `fk_proc_budget` (`budget_id`),
  KEY `fk_proc_item` (`item_id`),
  CONSTRAINT `fk_proc_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_proc_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `fk_proc_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_proc_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_orders` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `procurement_id` int(11) DEFAULT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Draft','Pending Approval','Approved','Sent','Received','Rejected','Returned') NOT NULL DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `fk_po_created_by` (`created_by`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_supplier_id` (`supplier_id`),
  KEY `fk_po_procurement` (`procurement_id`),
  CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurement` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_order_items` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `item_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_poi_po` (`po_id`),
  KEY `fk_po_item` (`item_id`),
  CONSTRAINT `fk_po_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `fk_poi_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `receiving` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity_received` int(11) NOT NULL,
  `received_by` int(11) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `qc_status` enum('PASS','FAIL') NOT NULL DEFAULT 'PASS',
  `qc_notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_receiving_received_by` (`received_by`),
  KEY `idx_receiving_po_id` (`po_id`),
  CONSTRAINT `fk_receiving_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_receiving_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_reconciliation` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `system_stock` int(11) NOT NULL DEFAULT 0,
  `physical_stock` int(11) NOT NULL DEFAULT 0,
  `variance` int(11) NOT NULL DEFAULT 0,
  `reconciled_by` int(11) DEFAULT NULL,
  `reconciled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_recon_item` (`item_id`),
  KEY `fk_recon_user` (`reconciled_by`),
  CONSTRAINT `fk_recon_item` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_recon_user` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_resources` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `resource_type` enum('User','Fleet','Asset') NOT NULL,
  `resource_id` int(11) NOT NULL,
  `role_label` varchar(80) DEFAULT NULL,
  `allocated_from` date DEFAULT NULL,
  `allocated_to` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_resources_project` (`project_id`),
  CONSTRAINT `fk_resources_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_tasks` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  `status` enum('Todo','In Progress','Done') NOT NULL DEFAULT 'Todo',
  `assigned_user_id` int(11) DEFAULT NULL,
  `assigned_fleet_id` int(11) DEFAULT NULL,
  `assigned_asset_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tasks_user` (`assigned_user_id`),
  KEY `fk_tasks_fleet` (`assigned_fleet_id`),
  KEY `idx_tasks_project_id` (`project_id`),
  KEY `fk_tasks_asset` (`assigned_asset_id`),
  CONSTRAINT `fk_tasks_asset` FOREIGN KEY (`assigned_asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_fleet` FOREIGN KEY (`assigned_fleet_id`) REFERENCES `fleet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `supplier_returns` (


  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_returns_po` (`po_id`),
  KEY `fk_returns_user` (`created_by`),
  CONSTRAINT `fk_returns_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_returns_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;





