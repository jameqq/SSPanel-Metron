# V9.8.20 Upgrade Guide

## Before upgrading

1. Back up the application directory, database, environment configuration, and scheduled-task configuration.
2. Verify the backup can be restored.
3. Deploy to a staging environment and test login, registration, payment callbacks, mail delivery, scheduled jobs, and every enabled subscription client.

## Database migration

Existing installations must run the idempotent migration below before enabling node `custom_config`:

```sh
mysql --database="$DB_NAME" < sql/upgrade_20260819_add_node_custom_config.sql
```

The migration checks `information_schema` before adding the column. A database backup is still required before running it.

## Application deployment

```sh
git fetch origin
git checkout V9.8.20
composer install --no-dev --classmap-authoritative
```

Clear Smarty's compiled templates after replacing the application files. Preserve the existing files under `config/` and do not copy example configuration over production configuration.

The Docker image now runs PHP-FPM 8.4 as the unprivileged `www-data` user on port 9000. Put Nginx or another FastCGI reverse proxy in front of it. Scheduled `xcat` jobs must run in a separate scheduler/container with the same code and production configuration; they are intentionally no longer bundled into the web process.

## Verification

- Confirm admin node create/edit works and rejects malformed JSON.
- Confirm Hysteria2, TUIC, AnyTLS, VLESS Reality/xHTTP, and Trojan gRPC subscriptions.
- Confirm XrayR receives `custom_config` as a JSON object.
- Run `composer test` and inspect application, PHP, Nginx, and cron logs.

## Rollback

Restore the previous application release and its matching `vendor/` directory. The nullable `ss_node.custom_config` column can remain during rollback; removing it is not required and may destroy saved node configuration.
