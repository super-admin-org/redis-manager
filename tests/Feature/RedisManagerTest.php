<?php

namespace SuperAdmin\Admin\RedisManager\Tests\Feature;

use InvalidArgumentException;
use RuntimeException;
use SuperAdmin\Admin\RedisManager\DataType\Hashes;
use SuperAdmin\Admin\RedisManager\DataType\SortedSets;
use SuperAdmin\Admin\RedisManager\RedisManager;
use SuperAdmin\Admin\RedisManager\Tests\TestCase;

class RedisManagerTest extends TestCase
{
    public function test_connections_are_the_named_ones_only(): void
    {
        $this->usePhpRedis();

        $this->assertSame(['default', 'cache'], RedisManager::instance()->getConnections()->keys()->all());
    }

    public function test_connection_details_hide_the_password(): void
    {
        $this->usePhpRedis();

        $details = RedisManager::instance()->connectionDetails();

        $this->assertSame('********', $details['password']);
        $this->assertSame('127.0.0.1', $details['host']);
    }

    public function test_an_unknown_connection_falls_back_to_the_first(): void
    {
        $this->usePhpRedis();

        $this->assertSame('default', RedisManager::instance('options')->connectionName());
        $this->assertSame('cache', RedisManager::instance('cache')->connectionName());
    }

    public function test_scan_works_with_phpredis(): void
    {
        $client = $this->usePhpRedis();
        $client->put('user:1', 'hash', [], 120);
        $client->put('user:2', 'string', 'x');
        $client->put('session:9', 'list');

        $page = RedisManager::instance()->scan('user:*', 50);

        $this->assertSame('0', $page['cursor']);
        $this->assertEqualsCanonicalizing([
            ['key' => 'user:1', 'type' => 'hash', 'ttl' => 120],
            ['key' => 'user:2', 'type' => 'string', 'ttl' => -1],
        ], $page['keys']);
    }

    public function test_scan_works_with_predis(): void
    {
        $client = $this->usePredis();
        $client->put('user:1', 'zset');
        $client->put('order:1', 'set');

        $page = RedisManager::instance()->scan('*', 50);

        $this->assertEqualsCanonicalizing(['user:1', 'order:1'], array_column($page['keys'], 'key'));
        $this->assertEqualsCanonicalizing(['zset', 'set'], array_column($page['keys'], 'type'));
    }

    public function test_scan_returns_one_page_and_the_next_cursor(): void
    {
        $client = $this->usePhpRedis();
        foreach (range(1, 5) as $i) {
            $client->put("item:{$i}", 'string');
        }

        $first = RedisManager::instance()->scan('*', 2);
        $this->assertCount(2, $first['keys']);
        $this->assertNotSame('0', $first['cursor']);

        $rest = RedisManager::instance()->scan('*', 10, $first['cursor']);
        $this->assertCount(3, $rest['keys']);
        $this->assertSame('0', $rest['cursor']);
    }

    public function test_info_is_grouped_by_section_for_both_clients(): void
    {
        $this->usePhpRedis();
        $info = RedisManager::instance()->getInformation();
        $this->assertSame('7.2.4', $info['Server']['redis_version']);
        $this->assertSame('keys=3,expires=1,avg_ttl=0', $info['Keyspace']['db0']);

        $this->usePredis();
        $this->assertSame('7.2.4', RedisManager::instance()->getInformation()['Server']['redis_version']);
    }

    public function test_only_known_data_types_are_accepted(): void
    {
        $this->usePhpRedis();

        $this->expectException(InvalidArgumentException::class);

        RedisManager::instance()->dataType('getInformation');
    }

    public function test_a_request_cannot_call_manager_methods_through_type(): void
    {
        $client = $this->usePhpRedis();

        $this->post('admin/redis/store', ['type' => 'getInformation', 'key' => 'x'])->assertStatus(500);

        $this->assertEmpty($client->calls);
    }

    public function test_console_supports_quoted_arguments_on_both_clients(): void
    {
        $phpredis = $this->usePhpRedis();
        RedisManager::instance()->execute('SET greeting "hello world"');
        $this->assertSame(['rawCommand', 'SET', 'greeting', 'hello world'], $phpredis->calls[0]);

        $predis = $this->usePredis();
        RedisManager::instance()->execute('GET greeting');
        $this->assertSame(['executeRaw', 'GET', 'greeting'], $predis->calls[0]);
    }

    public function test_console_refuses_dangerous_commands(): void
    {
        $client = $this->usePhpRedis();

        foreach (['FLUSHALL', 'config set dir /tmp', 'SHUTDOWN'] as $command) {
            try {
                RedisManager::instance()->execute($command);
                $this->fail("{$command} was not refused");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('disabled', $e->getMessage());
            }
        }

        $this->assertEmpty($client->calls);
    }

    public function test_read_only_mode_allows_reads_only(): void
    {
        config(['admin.extensions.redis-manager.read_only' => true]);
        $client = $this->usePhpRedis();

        RedisManager::instance()->execute('GET greeting');

        $this->expectException(RuntimeException::class);
        RedisManager::instance()->execute('SET greeting hi');
    }

    public function test_hash_editor_stores_the_submitted_fields(): void
    {
        $client = $this->usePhpRedis();

        (new Hashes(RedisManager::instance()->getConnection()))->store([
            'key'   => 'user:1',
            'ttl'   => -1,
            'value' => ['keys' => ['name', 'email', ''], 'values' => ['Ada', 'ada@example.com', 'ignored']],
        ]);

        $this->assertContains(['hset', 'user:1', 'name', 'Ada'], $client->calls);
        $this->assertContains(['hset', 'user:1', 'email', 'ada@example.com'], $client->calls);
        $this->assertCount(2, $client->calls);
    }

    public function test_set_members_are_passed_as_separate_arguments(): void
    {
        $client = $this->usePhpRedis();

        RedisManager::instance()->dataType('set')->store(['key' => 'tags', 'value' => ['a', 'b', '']]);

        $this->assertSame(['sadd', 'tags', 'a', 'b'], $client->calls[0]);
    }

    public function test_sorted_set_keeps_members_with_equal_scores(): void
    {
        $client = $this->usePhpRedis();
        $client->put('ranking', 'zset', ['ann' => 1.0, 'bob' => 1.0, 'cid' => 2.0]);

        $zset = new SortedSets(RedisManager::instance()->getConnection());
        $data = $zset->prepareData(['key' => 'ranking', 'value' => $zset->fetch('ranking')]);

        $this->assertSame(['ann', 'bob', 'cid'], $data['value']);
        $this->assertSame(['zRange', 'ranking', 0, -1, true], $client->calls[0]);
    }

    public function test_index_lists_keys_and_links_the_next_page(): void
    {
        $client = $this->usePhpRedis();
        foreach (range(1, 3) as $i) {
            $client->put("item:{$i}", 'string');
        }

        $this->get('admin/redis?count=2')
            ->assertOk()
            ->assertSee('item:1')
            ->assertSee('Next page')
            ->assertSee('********')
            ->assertDontSee('top-secret');
    }

    public function test_read_only_mode_hides_the_write_actions(): void
    {
        config(['admin.extensions.redis-manager.read_only' => true]);
        $client = $this->usePhpRedis();
        $client->put('item:1', 'string');

        $this->get('admin/redis')
            ->assertOk()
            ->assertSee('read-only')
            ->assertDontSee('btn btn-danger btn-sm key-delete-multi', false)
            ->assertDontSee('Create</button>', false);

        $this->post('admin/redis/store', ['type' => 'string', 'key' => 'x', 'value' => 'y'])->assertRedirect();
        $this->assertEmpty(array_filter($client->calls, function ($call) {
            return $call[0] === 'set';
        }));
    }

    public function test_an_unreachable_server_shows_a_message_instead_of_an_error_page(): void
    {
        $redis = \Mockery::mock();
        $redis->shouldReceive('connection')->andThrow(new \Error('Class "Redis" not found'));
        \Illuminate\Support\Facades\Redis::swap($redis);

        $this->get('admin/redis')
            ->assertOk()
            ->assertSee('Cannot connect to Redis connection')
            ->assertSee('REDIS_CLIENT=predis');
    }

    public function test_editing_a_missing_key_redirects_back(): void
    {
        $this->usePhpRedis();

        $this->get('admin/redis/edit?key=gone')->assertRedirect();
    }

    public function test_editing_a_string_key_shows_its_value(): void
    {
        $client = $this->usePhpRedis();
        $client->put('greeting', 'string', 'hello there');

        $this->get('admin/redis/edit?key=greeting')
            ->assertOk()
            ->assertSee('hello there');
    }
}
