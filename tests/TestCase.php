<?php

namespace SuperAdmin\Admin\RedisManager\Tests;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use SuperAdmin\Admin\AdminServiceProvider;
use SuperAdmin\Admin\Auth\Database\AdminTablesSeeder;
use SuperAdmin\Admin\Auth\Database\Administrator;
use SuperAdmin\Admin\Facades\Admin;
use SuperAdmin\Admin\RedisManager\RedisManagerServiceProvider;
use SuperAdmin\Admin\RedisManager\Tests\Fakes\FakePhpRedisClient;
use SuperAdmin\Admin\RedisManager\Tests\Fakes\FakePredisClient;

/**
 * Boots Super Admin with this extension in Orchestra Testbench and logs in the seeded administrator.
 * Redis is replaced with in-memory fakes of the phpredis and predis clients.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [AdminServiceProvider::class, RedisManagerServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return ['Admin' => Admin::class];
    }

    protected function defineEnvironment($app)
    {
        $config = require static::corePath().'/config/admin.php';
        $config['bootstrap'] = __DIR__.'/fixtures/bootstrap.php';
        $config['auth']['controller'] = \SuperAdmin\Admin\Controllers\AuthController::class;

        $app['config']->set('admin', $config);

        foreach (Arr::dot(Arr::get($config, 'auth', []), 'auth.') as $key => $value) {
            $app['config']->set($key, $value);
        }

        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.redis', [
            'client'   => 'phpredis',
            'options'  => ['cluster' => 'redis', 'prefix' => 'app_'],
            'default'  => ['host' => '127.0.0.1', 'port' => 6379, 'password' => 'top-secret', 'database' => 0],
            'cache'    => ['host' => '127.0.0.1', 'port' => 6379, 'database' => 1],
            'clusters' => ['default' => [['host' => '127.0.0.1', 'port' => 7000]]],
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(static::corePath().'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminTablesSeeder::class);

        Admin::routes();

        $this->actingAs(Administrator::query()->first(), 'admin');
    }

    protected function usePhpRedis(): FakePhpRedisClient
    {
        $client = new FakePhpRedisClient();
        $this->useConnection((new PhpRedisConnection($client))->setName('default'));

        return $client;
    }

    protected function usePredis(): FakePredisClient
    {
        $client = new FakePredisClient();
        $this->useConnection((new PredisConnection($client))->setName('default'));

        return $client;
    }

    protected function useConnection(Connection $connection): void
    {
        // A fresh mock each time, so a test can switch between the phpredis and predis fakes.
        $redis = \Mockery::mock();
        $redis->shouldReceive('connection')->andReturn($connection);

        Redis::swap($redis);
    }

    protected static function corePath(): string
    {
        return dirname((new ReflectionClass(AdminServiceProvider::class))->getFileName(), 2);
    }
}
