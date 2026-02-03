<?php

namespace Initium\LaravelTranslatable;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Initium\LaravelTranslatable\Commands\MakeTranslatableMigrationCommand;
use Initium\LaravelTranslatable\Commands\TranslatableCacheCommand;
use Initium\LaravelTranslatable\Commands\TranslatableClearCommand;
use Initium\LaravelTranslatable\Components\Database\TranslatableSchemaBuilder;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelTranslatableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-translatable')
            ->hasConfigFile('translatable')
            ->hasViews()
            ->hasMigration('create_laravel_translatable_table')
            ->hasCommands([
                MakeTranslatableMigrationCommand::class,
                TranslatableCacheCommand::class,
                TranslatableClearCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerTranslatableSchema();
    }

    public function packageBooted(): void
    {
        $this->configureBlueprintMacros();
        $this->configureTranslatableCache();
    }

    /**
     * Register the TranslatableSchema singleton.
     */
    protected function registerTranslatableSchema(): void
    {
        $this->app->singleton('translatable.schema', function ($app) {
            return new TranslatableSchemaBuilder($app['db']->connection());
        });
    }

    /**
     * Auto-regenerate translatable cache after migrations.
     */
    protected function configureTranslatableCache(): void
    {
        if (! config('translatable.auto_cache_after_migrate', true)) {
            return;
        }

        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            if ($event->exitCode === 0 && str_starts_with($event->command ?? '', 'migrate')) {
                Artisan::call('translatable:cache');
            }
        });
    }

    /**
     * Register Blueprint macros for common column patterns.
     */
    protected function configureBlueprintMacros(): void
    {
        Blueprint::macro('translationColumns', function (string $foreignKey) {
            /** @var Blueprint $this */
            $this->id();
            $this->foreignId($foreignKey)->constrained()->cascadeOnDelete();
            $this->string('locale', 10)->index();
            $this->timestamps();
        });

        Blueprint::macro('translationUnique', function (string $foreignKey) {
            /** @var Blueprint $this */
            $this->unique([$foreignKey, 'locale']);
        });
    }
}
