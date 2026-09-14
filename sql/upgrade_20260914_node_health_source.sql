SET @column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node' AND COLUMN_NAME = 'health_source_node_id');
SET @statement = IF(@column_exists = 0, 'ALTER TABLE `ss_node` ADD COLUMN `health_source_node_id` int NOT NULL DEFAULT 0 AFTER `node_heartbeat`', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node' AND INDEX_NAME = 'idx_node_health_source');
SET @statement = IF(@index_exists = 0, 'ALTER TABLE `ss_node` ADD KEY `idx_node_health_source` (`health_source_node_id`)', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;
