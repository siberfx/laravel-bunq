<?php

namespace Siberfx\LaravelBunq\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\LaravelBunq\BunqServiceProvider;
use Siberfx\LaravelBunq\Contracts\PaymentGateway;
use Siberfx\LaravelBunq\Tests\Fakes\FakeGateway;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BunqServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('bunq.webhook.token', 'test-token');
        $app['config']->set('bunq.monetary_account_id', 123);
        $app['config']->set('bunq.currency', 'EUR');

        // No network in tests: the real gateway is replaced by an in-memory fake.
        $app->instance(PaymentGateway::class, new FakeGateway());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    protected function gateway(): FakeGateway
    {
        return $this->app->make(PaymentGateway::class);
    }
}
