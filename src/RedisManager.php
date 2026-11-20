<?php

namespace SuperAdmin\Admin\RedisManager;

use Illuminate\Http\Request;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use RuntimeException;
use SuperAdmin\Admin\Extension;
use SuperAdmin\Admin\RedisManager\DataType\DataType;
use SuperAdmin\Admin\RedisManager\DataType\Hashes;
use SuperAdmin\Admin\RedisManager\DataType\Lists;
use SuperAdmin\Admin\RedisManager\DataType\Sets;
use SuperAdmin\Admin\RedisManager\DataType\SortedSets;
use SuperAdmin\Admin\RedisManager\DataType\Strings;

/**
 * Works with both Redis clients Laravel supports: phpredis (the default) and predis.
 */
class RedisManager extends Extension
{
    use BootExtension;

    public $name = 'redis-manager';

    public static $typeColor = [
        'string' => 'primary',
        'list'   => 'info',
        'zset'   => 'danger',
        'hash'   => 'warning',
        'set'    => 'success',
    ];

    /**
     * phpredis returns key types as integers (Redis::REDIS_STRING, ...).
     *
     * @var array<int, string>
     */
    public static $phpRedisTypes = [
        0 => 'none',
        1 => 'string',
        2 => 'set',
        3 => 'list',
        4 => 'zset',
        5 => 'hash',
        6 => 'stream',
    ];

    /**
     * Data types the editor supports.
     *
     * @var array<string, class-string<DataType>>
     */
    protected $dataTypes = [
        'string' => Strings::class,
        'hash'   => Hashes::class,
        'set'    => Sets::class,
        'zset'   => SortedSets::class,
        'list'   => Lists::class,
    ];

    /**
     * Commands the console refuses by default (`admin.extensions.redis-manager.disabled_commands`).
     *
     * @var array<int, string>
     */
    public static $disabledCommands = [
        'FLUSHALL', 'FLUSHDB', 'CONFIG', 'SHUTDOWN', 'DEBUG', 'MODULE', 'SLAVEOF', 'REPLICAOF',
        'MIGRATE', 'SAVE', 'BGSAVE', 'BGREWRITEAOF', 'SCRIPT', 'FUNCTION', 'ACL', 'CLUSTER',
        'FAILOVER', 'MONITOR', 'SYNC', 'PSYNC', 'SUBSCRIBE', 'PSUBSCRIBE', 'SSUBSCRIBE', 'CLIENT', 'KEYS',
    ];

    /**
     * Commands allowed in read-only mode.
     *
     * @var array<int, string>
     */
    public static $readCommands = [
        'GET', 'MGET', 'STRLEN', 'GETRANGE', 'EXISTS', 'TYPE', 'TTL', 'PTTL', 'LRANGE', 'LLEN', 'LINDEX',
        'HGET', 'HGETALL', 'HKEYS', 'HVALS', 'HLEN', 'HEXISTS', 'HMGET', 'HSTRLEN', 'SMEMBERS', 'SCARD',
        'SISMEMBER', 'SMISMEMBER', 'SRANDMEMBER', 'ZRANGE', 'ZRANGEBYSCORE', 'ZREVRANGE', 'ZREVRANGEBYSCORE',
        'ZCARD', 'ZSCORE', 'ZRANK', 'ZREVRANK', 'ZCOUNT', 'SCAN', 'HSCAN', 'SSCAN', 'ZSCAN', 'INFO',
        'DBSIZE', 'PING', 'ECHO', 'TIME', 'MEMORY', 'OBJECT', 'XRANGE', 'XREVRANGE', 'XLEN', 'XINFO',
        'LASTSAVE', 'ROLE', 'COMMAND', 'GETBIT', 'BITCOUNT', 'PFCOUNT',
    ];

    /**
     * Connection config keys never shown in the panel.
     *
     * @var array<int, string>
     */
    public static $secretOptions = ['password', 'username', 'token'];

    /**
     * @var string
     */
    protected $connection;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * A manager for the connection named in the request (`conn`), or the first one.
     */
    public static function instance($connection = null): self
    {
        return new static($connection);
    }

    public function __construct($connection = null)
    {
        $this->prefix = (string) config('database.redis.options.prefix', '');

        $names = $this->getConnections()->keys()->all();
        $requested = (string) ($connection ?? request()->input('conn', ''));

        $this->connection = in_array($requested, $names, true) ? $requested : (string) ($names[0] ?? 'default');
    }

    public function connectionName(): string
    {
        return $this->connection;
    }

    public function vars(): array
    {
        return [
            'conn'        => $this->connection,
            'prefix'      => $this->prefix,
            'info'        => $this->getInformation(),
            'connections' => $this->getConnections(),
            'details'     => $this->connectionDetails(),
            'readOnly'    => static::readOnly(),
        ];
    }

    /**
     * Whether the panel may change data (`admin.extensions.redis-manager.read_only`).
     */
    public static function readOnly(): bool
    {
        return (bool) static::config('read_only', false);
    }

    /**
     * The editor of a data type. Unknown types are rejected.
     */
    public function dataType(?string $type): DataType
    {
        $type = strtolower((string) $type);

        if (! isset($this->dataTypes[$type])) {
            throw new InvalidArgumentException("Unsupported Redis type [{$type}].");
        }

        $class = $this->dataTypes[$type];

        return new $class($this->getConnection(), $this);
    }

    public function list(): Lists
    {
        return $this->dataType('list');
    }

    public function string(): Strings
    {
        return $this->dataType('string');
    }

    public function hash(): Hashes
    {
        return $this->dataType('hash');
    }

    public function set(): Sets
    {
        return $this->dataType('set');
    }

    public function zset(): SortedSets
    {
        return $this->dataType('zset');
    }

    /**
     * Named connections from `database.redis` (not `client`, `options` or `clusters`).
     */
    public function getConnections()
    {
        return collect(config('database.redis', []))
            ->except(['client', 'options', 'clusters'])
            ->filter(function ($config) {
                return is_array($config);
            });
    }

    /**
     * Settings of the current connection with secrets masked.
     *
     * @return array<string, mixed>
     */
    public function connectionDetails(): array
    {
        $details = (array) $this->getConnections()->get($this->connection, []);

        foreach ($details as $name => $value) {
            if (in_array(strtolower((string) $name), static::$secretOptions, true) && ! empty($value)) {
                $details[$name] = '********';
            }
        }

        return $details;
    }

    public function getConnection($connection = null): Connection
    {
        if ($connection) {
            $this->connection = $connection;
        }

        return Redis::connection($this->connection);
    }

    public function isPhpRedis(?Connection $connection = null): bool
    {
        return ($connection ?? $this->getConnection()) instanceof PhpRedisConnection;
    }

    /**
     * `INFO`, grouped by section for both clients.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getInformation(): array
    {
        $connection = $this->getConnection();

        if ($this->isPhpRedis($connection)) {
            return static::parseInfo((string) $connection->client()->rawCommand('INFO'));
        }

        $info = $connection->info();

        if (! is_array($info)) {
            return static::parseInfo((string) $info);
        }

        // predis groups by section; older servers return a flat list.
        return is_array(reset($info)) ? $info : ['Server' => $info];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function parseInfo(string $raw): array
    {
        $sections = [];
        $section = 'Server';

        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if ($line[0] === '#') {
                $section = trim(substr($line, 1));

                continue;
            }

            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $sections[$section][$key] = $value;
            }
        }

        return $sections;
    }

    public function keyNoPrefix(string $key): string
    {
        if ($this->prefix !== '' && strpos($key, $this->prefix) === 0) {
            return substr($key, strlen($this->prefix));
        }

        return $key;
    }

    /**
     * The type of a key: string, list, set, zset, hash, stream or none.
     */
    public function typeOf(string $key): string
    {
        $type = $this->getConnection()->type($key);

        if (is_int($type)) {
            return static::$phpRedisTypes[$type] ?? 'none';
        }

        return strtolower((string) $type);
    }

    /**
     * One page of keys matching a pattern, with their type and TTL.
     *
     * @return array{keys: array<int, array{key: string, type: string, ttl: int}>, cursor: string}
     */
    public function scan(string $pattern = '*', int $count = 50, string $cursor = '0'): array
    {
        $connection = $this->getConnection();
        $count = max(1, min($count, 1000));
        $match = $this->prefix.($pattern === '' ? '*' : $pattern);
        $keys = [];
        $rounds = 0;

        do {
            [$cursor, $batch] = $this->scanStep($connection, $cursor, $match, $count);

            foreach ($batch as $key) {
                $keys[$this->keyNoPrefix((string) $key)] = true;
            }
        } while ($cursor !== '0' && count($keys) < $count && ++$rounds < 100);

        $rows = [];
        foreach (array_keys($keys) as $key) {
            $key = (string) $key;
            $rows[] = [
                'key'  => $key,
                'type' => $this->typeOf($key),
                'ttl'  => (int) $connection->ttl($key),
            ];
        }

        return ['keys' => $rows, 'cursor' => $cursor];
    }

    /**
     * One SCAN call. Returns the next cursor ('0' when done) and the keys found.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected function scanStep(Connection $connection, string $cursor, string $match, int $count): array
    {
        if ($this->isPhpRedis($connection)) {
            $iterator = $cursor === '0' ? null : (int) $cursor;
            $batch = $connection->client()->scan($iterator, $match, $count);

            return [(string) ($iterator ?: '0'), is_array($batch) ? $batch : []];
        }

        $result = $connection->client()->scan($cursor, ['MATCH' => $match, 'COUNT' => $count]);

        return [(string) ($result[0] ?? '0'), (array) ($result[1] ?? [])];
    }

    /**
     * @return array{key: string, value: mixed, ttl: int, type: string}|array{}
     */
    public function fetch($key): array
    {
        $key = (string) $key;

        if ($key === '' || ! $this->getConnection()->exists($key)) {
            return [];
        }

        $type = $this->typeOf($key);

        if (! isset($this->dataTypes[$type])) {
            return [];
        }

        $class = $this->dataType($type);
        $value = $class->fetch($key);
        $ttl = $class->ttl($key);

        return compact('key', 'value', 'ttl', 'type');
    }

    public function update(Request $request)
    {
        $this->guardWrite();

        $key = $request->input('key');
        $class = $this->dataType($request->input('type'));

        $res = $class->update($request->all());

        $class->setTtl($key, $request->input('ttl'));

        return $res;
    }

    public function del($key)
    {
        $this->guardWrite();

        $keys = array_values(array_filter((array) $key, 'strlen'));

        return $keys ? $this->getConnection()->del($keys) : 0;
    }

    /**
     * Run a console command (quoted arguments are supported: SET greeting "hello world").
     *
     * @return mixed
     */
    public function execute($command)
    {
        $arguments = array_values(array_filter(str_getcsv(trim((string) $command), ' ', '"', '\\'), function ($part) {
            return $part !== '' && $part !== null;
        }));

        if (empty($arguments)) {
            throw new InvalidArgumentException('Type a command, e.g. GET key.');
        }

        $name = strtoupper($arguments[0]);
        $disabled = array_map('strtoupper', (array) static::config('disabled_commands', static::$disabledCommands));

        if (in_array($name, $disabled, true)) {
            throw new RuntimeException("The {$name} command is disabled in the Redis manager.");
        }

        if (static::readOnly() && ! in_array($name, static::$readCommands, true)) {
            throw new RuntimeException("The Redis manager is read-only; {$name} is not allowed.");
        }

        $connection = $this->getConnection();

        if ($this->isPhpRedis($connection)) {
            return $connection->client()->rawCommand(...$arguments);
        }

        return $connection->executeRaw($arguments);
    }

    public function guardWrite(): void
    {
        if (static::readOnly()) {
            throw new RuntimeException('The Redis manager is read-only.');
        }
    }

    public static function typeColor($type)
    {
        return Arr::get(static::$typeColor, $type, 'secondary');
    }
}
