<?php

namespace Siberfx\LaravelBunq;

use Illuminate\Support\ServiceProvider;
use Siberfx\LaravelBunq\Console\InstallCommand;
use Siberfx\LaravelBunq\Contracts\PaymentGateway;
use Siberfx\LaravelBunq\Gateway\BunqGateway;

class BunqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bunq.php', 'bunq');

        $this->app->singleton(BunqManager::class, fn () => new BunqManager(config('bunq.context_path')));

        // bindIf lets an app swap in its own gateway, or a fake in tests.
        $this->app->bindIf(PaymentGateway::class, BunqGateway::class, true);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/bunq.php' => config_path('bunq.php'),
        ], 'bunq-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'bunq-migrations');

        if (config('bunq.webhook.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }
}
