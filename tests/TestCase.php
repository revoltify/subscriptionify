<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Revoltify\Subscriptionify\SubscriptionifyServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            SubscriptionifyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set('database.default', 'testing');
        $app->make(Repository::class)->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    private function setUpDatabase(): void
    {
        // Run package migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Create a test users table for the subscribable model
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->decimal('balance', 16, 8)->default(0);
                $table->timestamps();
            });
        }
    }
}
