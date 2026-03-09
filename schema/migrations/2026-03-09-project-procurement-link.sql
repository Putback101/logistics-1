START TRANSACTION;

SET @has_project_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'procurement'
    AND COLUMN_NAME = 'project_id'
);

SET @sql_add_project_id := IF(
  @has_project_id = 0,
  'ALTER TABLE procurement ADD COLUMN project_id INT(11) NULL AFTER requested_by',
  'SELECT 1'
);
PREPARE stmt_add_project_id FROM @sql_add_project_id;
EXECUTE stmt_add_project_id;
DEALLOCATE PREPARE stmt_add_project_id;

SET @has_project_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'procurement'
    AND INDEX_NAME = 'fk_proc_project'
);

SET @sql_add_project_idx := IF(
  @has_project_idx = 0,
  'ALTER TABLE procurement ADD KEY fk_proc_project (project_id)',
  'SELECT 1'
);
PREPARE stmt_add_project_idx FROM @sql_add_project_idx;
EXECUTE stmt_add_project_idx;
DEALLOCATE PREPARE stmt_add_project_idx;

SET @has_project_fk := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'procurement'
    AND CONSTRAINT_NAME = 'fk_proc_project'
);

SET @sql_add_project_fk := IF(
  @has_project_fk = 0,
  'ALTER TABLE procurement ADD CONSTRAINT fk_proc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt_add_project_fk FROM @sql_add_project_fk;
EXECUTE stmt_add_project_fk;
DEALLOCATE PREPARE stmt_add_project_fk;

COMMIT;
