ALTER TABLE procurement
  ADD COLUMN IF NOT EXISTS source_module varchar(80) DEFAULT NULL AFTER requested_by,
  ADD COLUMN IF NOT EXISTS source_system varchar(80) DEFAULT NULL AFTER source_module,
  ADD COLUMN IF NOT EXISTS source_reference varchar(120) DEFAULT NULL AFTER source_system,
  ADD COLUMN IF NOT EXISTS source_payload longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(source_payload)) AFTER source_reference;

SET @idx_module_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'procurement'
    AND index_name = 'idx_proc_source_module'
);
SET @sql_idx_module := IF(@idx_module_exists = 0, 'CREATE INDEX idx_proc_source_module ON procurement (source_module)', 'SELECT 1');
PREPARE stmt_idx_module FROM @sql_idx_module;
EXECUTE stmt_idx_module;
DEALLOCATE PREPARE stmt_idx_module;

SET @idx_ref_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'procurement'
    AND index_name = 'idx_proc_source_reference'
);
SET @sql_idx_ref := IF(@idx_ref_exists = 0, 'CREATE INDEX idx_proc_source_reference ON procurement (source_reference)', 'SELECT 1');
PREPARE stmt_idx_ref FROM @sql_idx_ref;
EXECUTE stmt_idx_ref;
DEALLOCATE PREPARE stmt_idx_ref;
