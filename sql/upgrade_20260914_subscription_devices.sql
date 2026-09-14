CREATE TABLE IF NOT EXISTS `subscription_device` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `name` varchar(64) NOT NULL,
  `profile` varchar(32) NOT NULL DEFAULT 'auto',
  `token_hash` char(64) NOT NULL,
  `token_prefix` varchar(10) NOT NULL,
  `created_at` bigint NOT NULL,
  `expires_at` bigint DEFAULT NULL,
  `last_access_at` bigint DEFAULT NULL,
  `last_access_ip` varchar(45) DEFAULT NULL,
  `revoked_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_subscription_device_token_hash` (`token_hash`),
  KEY `idx_subscription_device_user_status` (`user_id`,`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `link` MODIFY `token` varchar(64) NOT NULL;

SET @index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'link' AND INDEX_NAME = 'uk_link_token');
SET @statement = IF(@index_exists = 0, 'ALTER TABLE `link` ADD UNIQUE KEY `uk_link_token` (`token`)', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'link' AND INDEX_NAME = 'idx_link_user_type_geo');
SET @statement = IF(@index_exists = 0, 'ALTER TABLE `link` ADD KEY `idx_link_user_type_geo` (`userid`,`type`,`geo`)', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_subscribe_log' AND INDEX_NAME = 'idx_sublog_user_time');
SET @statement = IF(@index_exists = 0, 'ALTER TABLE `user_subscribe_log` ADD KEY `idx_sublog_user_time` (`user_id`,`request_time`)', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node_info' AND INDEX_NAME = 'idx_node_info_node_time');
SET @statement = IF(@index_exists = 0, 'ALTER TABLE `ss_node_info` ADD KEY `idx_node_info_node_time` (`node_id`,`log_time`)', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_node_online_log' AND INDEX_NAME = 'idx_node_online_node_time');
SET @statement = IF(@index_exists = 0, 'ALTER TABLE `ss_node_online_log` ADD KEY `idx_node_online_node_time` (`node_id`,`log_time`)', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'speedtest' AND INDEX_NAME = 'idx_speedtest_node_time');
SET @statement = IF(@index_exists = 0, 'ALTER TABLE `speedtest` ADD KEY `idx_speedtest_node_time` (`nodeid`,`datetime`)', 'SELECT 1');
PREPARE migration_statement FROM @statement; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;
