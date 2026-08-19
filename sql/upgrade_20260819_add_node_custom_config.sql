-- Run once when upgrading an existing installation for XrayR custom_config support.
ALTER TABLE `ss_node` ADD COLUMN `custom_config` TEXT NULL AFTER `node_ip`;
