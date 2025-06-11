<?php

namespace SuperAdmin\Admin\RedisManager;

use Illuminate\Support\ServiceProvider;

class RedisManagerServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'super-admin-redis-manager');

        RedisManager::boot();
    }
}
