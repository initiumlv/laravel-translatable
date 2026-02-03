<?php

namespace Initium\LaravelTranslatable\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Initium\LaravelTranslatable\LaravelTranslatableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Initium\\LaravelTranslatable\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Enable foreign key constraints for SQLite
        if (config('database.default') === 'testing') {
            \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelTranslatableServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('translatable.cache_path', 'bootstrap/cache/translatable.php');
        config()->set('translatable.table_suffix', '_translations');
        config()->set('translatable.auto_cache_after_migrate', false);
    }
}
