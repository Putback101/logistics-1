ALTER TABLE maintenance_logs
  ADD COLUMN IF NOT EXISTS request_ref varchar(50) DEFAULT NULL AFTER recorded_by,
  ADD COLUMN IF NOT EXISTS priority enum('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal' AFTER request_ref,
  ADD COLUMN IF NOT EXISTS source_module varchar(80) DEFAULT NULL AFTER priority,
  ADD COLUMN IF NOT EXISTS source_system varchar(80) DEFAULT NULL AFTER source_module,
  ADD COLUMN IF NOT EXISTS source_reference varchar(120) DEFAULT NULL AFTER source_system,
  ADD COLUMN IF NOT EXISTS source_payload longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(source_payload)) AFTER source_reference;

SET @idx_maint_ref_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'maintenance_logs'
    AND index_name = 'idx_maint_request_ref'
);
SET @sql_idx_maint_ref := IF(@idx_maint_ref_exists = 0, 'CREATE INDEX idx_maint_request_ref ON maintenance_logs (request_ref)', 'SELECT 1');
PREPARE stmt_idx_maint_ref FROM @sql_idx_maint_ref;
EXECUTE stmt_idx_maint_ref;
DEALLOCATE PREPARE stmt_idx_maint_ref;

SET @idx_maint_source_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'maintenance_logs'
    AND index_name = 'idx_maint_source'
);
SET @sql_idx_maint_source := IF(@idx_maint_source_exists = 0, 'CREATE INDEX idx_maint_source ON maintenance_logs (source_module, source_reference)', 'SELECT 1');
PREPARE stmt_idx_maint_source FROM @sql_idx_maint_source;
EXECUTE stmt_idx_maint_source;
DEALLOCATE PREPARE stmt_idx_maint_source;
