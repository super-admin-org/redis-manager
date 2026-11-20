<?php

namespace SuperAdmin\Admin\RedisManager\Tests\Fakes;

/**
 * Behaves like the \Redis client of the phpredis extension for the calls the manager makes:
 * integer key types, SCAN with an iterator by reference, rawCommand(), the key prefix applied
 * to key arguments but not to SCAN patterns.
 */
class FakePhpRedisClient
{
    public const TYPES = ['none' => 0, 'string' => 1, 'set' => 2, 'list' => 3, 'zset' => 4, 'hash' => 5];

    /** @var array<string, array{type: string, value: mixed, ttl: int}> keys stored with prefix */
    public $data = [];

    /** @var array<int, array<int, mixed>> */
    public $calls = [];

    public $prefix = 'app_';

    public function put(string $key, string $type, $value = null, int $ttl = -1): void
    {
        $this->data[$this->prefix.$key] = ['type' => $type, 'value' => $value, 'ttl' => $ttl];
    }

    public function scan(&$iterator, $pattern = null, $count = 0)
    {
        $keys = array_values(array_filter(array_keys($this->data), function ($key) use ($pattern) {
            return fnmatch((string) $pattern, $key);
        }));

        $offset = (int) ($iterator ?? 0);
        $batch = array_slice($keys, $offset, $count);
        $iterator = $offset + $count >= count($keys) ? 0 : $offset + $count;

        return $batch;
    }

    public function type($key)
    {
        return static::TYPES[$this->data[$this->prefix.$key]['type'] ?? 'none'] ?? 0;
    }

    public function ttl($key)
    {
        return $this->data[$this->prefix.$key]['ttl'] ?? -2;
    }

    public function exists($key)
    {
        return isset($this->data[$this->prefix.$key]) ? 1 : 0;
    }

    public function get($key)
    {
        return $this->data[$this->prefix.$key]['value'] ?? false;
    }

    public function zRange($key, $start, $end, $withScores = false)
    {
        $this->calls[] = ['zRange', $key, $start, $end, $withScores];

        return $this->data[$this->prefix.$key]['value'] ?? [];
    }

    public function rawCommand(...$arguments)
    {
        $this->calls[] = array_merge(['rawCommand'], $arguments);

        if (strtoupper((string) $arguments[0]) === 'INFO') {
            return "# Server\r\nredis_version:7.2.4\r\nuptime_in_days:3\r\n\r\n# Keyspace\r\ndb0:keys=3,expires=1,avg_ttl=0\r\n";
        }

        return 'OK';
    }

    public function __call($method, $arguments)
    {
        $this->calls[] = array_merge([strtolower($method)], $arguments);

        return 1;
    }
}
