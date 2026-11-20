Redis manager for Super Admin
=============================

Browse the keys of your Redis connections page by page, edit strings, lists, sets, sorted sets and hashes, set TTLs, delete keys and run Redis commands from the admin panel.

**Documentation:** https://super-admin.org/laravel/extension-redis-manager/

Requirements: PHP 8.0+, Laravel 8 – 13, Super Admin 1.x. Works with both Redis clients Laravel supports: **phpredis** (the default) and **predis**.

## Installation

```bash
composer require super-admin-org/redis-manager
php artisan admin:import redis-manager
```

Open `/admin/redis`.

## Configuration

```php
// config/admin.php
'extensions' => [
    'redis-manager' => [
        'read_only'  => false,   // true: browse only, no create/edit/delete, console limited to read commands
        'scan_count' => 50,      // keys per page
        // Commands the console refuses. The default list blocks FLUSHALL, FLUSHDB, CONFIG,
        // SHUTDOWN, DEBUG, MODULE, REPLICAOF, SAVE, KEYS, SCRIPT, ACL, CLUSTER, MONITOR, ...
        // 'disabled_commands' => [...],
    ],
],
```

Connection passwords are never shown in the panel.

## Troubleshooting

| Message | Fix |
|---|---|
| Cannot connect ... `Class "Redis" not found` | Install the PHP redis extension, or set `REDIS_CLIENT=predis` and `composer require predis/predis`. |
| Cannot connect ... `Connection refused` | Start Redis or fix `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD`. |

## Tests

```bash
composer install
composer test
```

License
-------
Licensed under [The MIT License (MIT)](LICENSE).
