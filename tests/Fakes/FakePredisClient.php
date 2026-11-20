<?php

namespace SuperAdmin\Admin\RedisManager\Tests\Fakes;

use Predis\Response\Status;

/**
 * Behaves like Predis\Client for the calls the manager makes: TYPE returns a Status object,
 * SCAN takes a cursor and an options array and returns [cursor, keys], executeRaw() takes an array.
 */
class FakePredisClient
{
    /** @var array<string, array{type: string, value: mixed, ttl: int}> keys stored with prefix */
    public $data = [];

    /** @var array<int, array<int, mixed>> */
    public $calls = [];

    public $prefix = 'app_';

    public function put(string $key, string $type, $value = null, int $ttl = -1): void
    {
        $this->data[$this->prefix.$key] = ['type' => $type, 'value' => $value, 'ttl' => $ttl];
    }

    public function scan($cursor, array $options = [])
    {
        $keys = array_values(array_filter(array_keys($this->data), function ($key) use ($options) {
            return fnmatch((string) ($options['MATCH'] ?? '*'), $key);
        }));

        $count = (int) ($options['COUNT'] ?? 10);
        $offset = (int) $cursor;
        $next = $offset + $count >= count($keys) ? 0 : $offset + $count;

        return [(string) $next, array_slice($keys, $offset, $count)];
    }

    public function type($key)
    {
        return new Status($this->data[$this->prefix.$key]['type'] ?? 'none');
    }

    public function ttl($key)
    {
        return $this->data[$this->prefix.$key]['ttl'] ?? -2;
    }

    public function exists($key)
    {
        return isset($this->data[$this->prefix.$key]) ? 1 : 0;
    }

    public function info()
    {
        return ['Server' => ['redis_version' => '7.2.4'], 'Keyspace' => ['db0' => ['keys' => 3]]];
    }

    public function executeRaw(array $arguments)
    {
        $this->calls[] = array_merge(['executeRaw'], $arguments);

        return 'OK';
    }

    public function __call($method, $arguments)
    {
        $this->calls[] = array_merge([strtolower($method)], $arguments);

        return 1;
    }
}
