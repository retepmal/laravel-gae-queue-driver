<?php

namespace Retepmal\LaravelGaeQueue;

use Illuminate\Support\ServiceProvider;

class GaeQueueServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // add routes as App Engine task handlers
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // register queue connector
        $this->app['queue']->addConnector('gae', function()
        {
            return new GaeQueueConnector;
        });
    }

    public function register()
    {
        //
    }
}
