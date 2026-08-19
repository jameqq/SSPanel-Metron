-- Idempotent migration for XrayR custom_config support (MySQL 5.7+).
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ss_node'
      AND COLUMN_NAME = 'custom_config'
);
SET @statement = IF(
    @column_exists = 0,
    'ALTER TABLE `ss_node` ADD COLUMN `custom_config` TEXT NULL AFTER `node_ip`',
    'SELECT ''ss_node.custom_config already exists'' AS message'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
