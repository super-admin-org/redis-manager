<?php

namespace SuperAdmin\Admin\RedisManager;

use SuperAdmin\Admin\Facades\Admin;

trait BootExtension
{
    public static function boot()
    {
        static::registerRoutes();

        Admin::extend('redis-manager', __CLASS__);
    }

    /**
     * Register routes for super-admin.
     *
     * @return void
     */
    protected static function registerRoutes()
    {
        parent::routes(function ($router) {
            /* @var \Illuminate\Routing\Router $router */
            $router->get('redis', 'SuperAdmin\Admin\RedisManager\RedisController@index')->name('redis-index');
            $router->delete('redis/key', 'SuperAdmin\Admin\RedisManager\RedisController@destroy')->name('redis-key-delete');
            $router->get('redis/fetch', 'SuperAdmin\Admin\RedisManager\RedisController@fetch')->name('redis-fetch-key');
            $router->get('redis/create', 'SuperAdmin\Admin\RedisManager\RedisController@create')->name('redis-create-key');
            $router->post('redis/store', 'SuperAdmin\Admin\RedisManager\RedisController@store')->name('redis-store-key');
            $router->get('redis/edit', 'SuperAdmin\Admin\RedisManager\RedisController@edit')->name('redis-edit-key');
            $router->post('redis/key', 'SuperAdmin\Admin\RedisManager\RedisController@update')->name('redis-update-key');
            $router->delete('redis/item', 'SuperAdmin\Admin\RedisManager\RedisController@remove')->name('redis-remove-item');

            $router->get('redis/console', 'SuperAdmin\Admin\RedisManager\RedisController@console')->name('redis-console');
            $router->post('redis/console', 'SuperAdmin\Admin\RedisManager\RedisController@execute')->name('redis-execute');
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function import()
    {
        parent::createMenu('Redis manager', 'redis', 'icon-database');

        parent::createPermission('Redis Manager', 'ext.redis-manager', 'redis*');
    }
}
