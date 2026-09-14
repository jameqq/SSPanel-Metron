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

## Subscription devices and automatic node removal

Apply these idempotent migrations before deploying the matching PHP code:

```bash
mysql -u root -p panel_database < sql/upgrade_20260914_add_node_health.sql
mysql -u root -p panel_database < sql/upgrade_20260914_subscription_devices.sql
mysql -u root -p panel_database < sql/upgrade_20260914_node_health_state.sql
mysql -u root -p panel_database < sql/upgrade_20260914_node_health_source.sql
```

Run the health collector every minute. A node is removed from generated subscriptions after three consecutive probe failures and restored after two consecutive successes. Stale health results older than 15 minutes do not remove nodes.

Copied transit or alternate-entry nodes automatically inherit the original node's heartbeat, online-user count, uptime, system load, and core versions through `health_source_node_id`. Their own endpoint latency, traffic settings, and automatic-removal result remain independent. Set the field to `0` in the node editor to make a copied node independent again.

```cron
* * * * * cd /var/www/sspanel && /usr/bin/php xcat NodeHealth >/dev/null 2>&1
```

Legacy query-string subscriptions remain valid. New canonical profiles use `/link/{token}/{profile}`, where profile can be `mihomo`, `fancyss`, `sing-box`, `v2rayn`, `stash`, `surge`, `quantumultx`, or `shadowrocket`.

## Framework compatibility

The template engine is upgraded to Smarty 5.8.4. Clear `storage/framework/smarty/compile/` during deployment so templates are rebuilt with the new compiler.

Slim remains on the maintained 3.13 line for this release. Moving to Slim 4 requires a separate application migration: replace the Pimple bootstrap and container-bound routes, convert double-pass middleware to PSR-15, and replace Slim 3 request/response helpers such as `getParam()`, `write()`, and `withJson()`. Do not change only the Composer constraint; that leaves the application unable to boot. Complete that migration on a separate branch and rerun every web, API, callback, and subscription test before merging it.

## Rollback

Restore the previous application release and its matching `vendor/` directory. The nullable `ss_node.custom_config` column can remain during rollback; removing it is not required and may destroy saved node configuration.
