# Changelog

## v1.1.0 - 2026-11-20

### Changed

- Works with super-admin 2.x: the package now requires `super-admin-org/super-admin` `^1.0|^2.0`. Earlier versions required `^1.0`, so Composer could not install them next to super-admin v2.0.0.
- Package homepage and documentation: https://super-admin.org/laravel/extension-redis-manager/

## v1.0.4 - 2026-11-20

### Security

- `store`, `update` and `remove` called `$manager->{$request->type}()`, so a request could invoke any public method; only the five data types are accepted now.
- The console refuses dangerous commands by default (`FLUSHALL`, `FLUSHDB`, `CONFIG`, `SHUTDOWN`, `DEBUG`, `MODULE`, `REPLICAOF`, `SAVE`, `KEYS`, `SCRIPT`, `ACL`, `CLUSTER`, `MONITOR`, ...); configurable with `disabled_commands`.
- Connection passwords were shown in the connection details; they are masked.
- Console input and error messages were inserted as HTML; they are escaped.

### Fixed

- Only the predis client worked; phpredis (Laravel's default) failed. Both are supported now (key types, SCAN, INFO, raw commands, sorted sets).
- The key list read the whole keyspace and ran `TYPE`/`TTL` for every key in one request; it now pages with `SCAN` (`scan_count`, default 50) and **Next page**.
- `options` and `clusters` from `config/database.php` were listed as connections.
- Editing a key that no longer exists rendered a missing view; it redirects with a message now.
- Saving a hash failed with "Undefined array key"; the key-value field's `keys`/`values` are read correctly.
- Sorted-set members with equal scores collapsed into one in the editor.
- Set, list and hash commands passed arrays where phpredis expects separate arguments.
- The key prefix was removed anywhere in a key, not only at the start.
- Connections defined with a URL instead of host/port broke the sidebar.
- Quoted console arguments (`SET greeting "hello world"`) were split at spaces.

### Added

- `read_only` mode: browse only, console limited to read commands.
- A clear message instead of an error page when Redis cannot be reached (missing extension, wrong host).
- Tests on Orchestra Testbench (with fake phpredis and predis clients).

### Changed

- Requires PHP 8.0+, declares Laravel 8 – 13 and `predis/predis ^1.1|^2.0|^3.0`.
