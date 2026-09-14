-- Idempotent migration for the node health center (MySQL 5.7+).
SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node_info' AND COLUMN_NAME = 'memory_usage');
SET @statement = IF(@column_exists = 0, 'ALTER TABLE `ss_node_info` ADD COLUMN `memory_usage` varchar(32) NULL AFTER `load`', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node_info' AND COLUMN_NAME = 'disk_usage');
SET @statement = IF(@column_exists = 0, 'ALTER TABLE `ss_node_info` ADD COLUMN `disk_usage` varchar(32) NULL AFTER `memory_usage`', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node_info' AND COLUMN_NAME = 'xray_version');
SET @statement = IF(@column_exists = 0, 'ALTER TABLE `ss_node_info` ADD COLUMN `xray_version` varchar(128) NULL AFTER `disk_usage`', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node_info' AND COLUMN_NAME = 'hysteria_version');
SET @statement = IF(@column_exists = 0, 'ALTER TABLE `ss_node_info` ADD COLUMN `hysteria_version` varchar(128) NULL AFTER `xray_version`', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node_info' AND COLUMN_NAME = 'singbox_version');
SET @statement = IF(@column_exists = 0, 'ALTER TABLE `ss_node_info` ADD COLUMN `singbox_version` varchar(128) NULL AFTER `hysteria_version`', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;
