CREATE TABLE IF NOT EXISTS `node_health_state` (
  `node_id` int NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'unknown',
  `consecutive_failures` int NOT NULL DEFAULT 0,
  `consecutive_successes` int NOT NULL DEFAULT 0,
  `last_latency_ms` int DEFAULT NULL,
  `last_checked_at` bigint NOT NULL DEFAULT 0,
  `last_success_at` bigint DEFAULT NULL,
  `last_failure_at` bigint DEFAULT NULL,
  `unhealthy_since` bigint DEFAULT NULL,
  `last_error` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`node_id`),
  KEY `idx_node_health_status_time` (`status`,`last_checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
