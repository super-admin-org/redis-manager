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
        // Template-aware: <views>/<template>/ and <template>/extensions/super-admin-redis-manager/ override <views>/.
        if (class_exists(\SuperAdmin\Admin\Template\TemplateManager::class)) {
            \SuperAdmin\Admin\Facades\Admin::template()->extensionViews('super-admin-redis-manager', __DIR__.'/../resources/views');
        } else {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'super-admin-redis-manager');
        }

        RedisManager::boot();
    }
}
