<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Initium\LaravelTranslatable\Components\Database\Blueprint;
use Initium\LaravelTranslatable\Facades\TranslatableSchema;

beforeEach(function () {
    $this->cachePath = base_path('bootstrap/cache/translatable.php');

    // Ensure cache directory exists
    File::ensureDirectoryExists(dirname($this->cachePath));

    // Clean up any leftover migration files from previous test runs
    $testMigrations = File::glob(database_path('migrations/*_create_test_items_table.php'));
    foreach ($testMigrations as $migration) {
        File::delete($migration);
    }
    $testMigrations = File::glob(database_path('migrations/*_add_subtitle_to_posts_table.php'));
    foreach ($testMigrations as $migration) {
        File::delete($migration);
    }
    $testMigrations = File::glob(database_path('migrations/*_create_categories_table.php'));
    foreach ($testMigrations as $migration) {
        File::delete($migration);
    }
});

afterEach(function () {
    // Clean up cache file
    if (File::exists($this->cachePath)) {
        File::delete($this->cachePath);
    }

    // Clean up test tables
    Schema::dropIfExists('post_translations');
    Schema::dropIfExists('posts');

    // Clean up migration files created during tests
    $testMigrations = File::glob(database_path('migrations/*_create_test_items_table.php'));
    foreach ($testMigrations as $migration) {
        File::delete($migration);
    }
    $testMigrations = File::glob(database_path('migrations/*_add_subtitle_to_posts_table.php'));
    foreach ($testMigrations as $migration) {
        File::delete($migration);
    }
    $testMigrations = File::glob(database_path('migrations/*_create_categories_table.php'));
    foreach ($testMigrations as $migration) {
        File::delete($migration);
    }
});

test('translatable:cache command creates cache file', function () {
    // Create a test table with translations
    TranslatableSchema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('slug');
        $table->string('title')->translatable();
        $table->text('content')->nullable()->translatable();
    });

    $this->artisan('translatable:cache')
        ->assertSuccessful();

    expect(File::exists($this->cachePath))->toBeTrue();

    $cache = require $this->cachePath;

    expect($cache)->toBeArray();
    expect($cache)->toHaveKey('post_translations');
    expect($cache['post_translations'])->toContain('title');
    expect($cache['post_translations'])->toContain('content');
    expect($cache['post_translations'])->not->toContain('id');
    expect($cache['post_translations'])->not->toContain('locale');
    expect($cache['post_translations'])->not->toContain('post_id');
});

test('translatable:clear command removes cache file', function () {
    // Create cache file
    File::put($this->cachePath, '<?php return [];');

    expect(File::exists($this->cachePath))->toBeTrue();

    $this->artisan('translatable:clear')
        ->assertSuccessful();

    expect(File::exists($this->cachePath))->toBeFalse();
});

test('translatable:clear command handles missing file gracefully', function () {
    // Ensure file doesn't exist
    if (File::exists($this->cachePath)) {
        File::delete($this->cachePath);
    }

    $this->artisan('translatable:clear')
        ->assertSuccessful();
});

test('translatable:cache warns when no translation tables exist', function () {
    $this->artisan('translatable:cache')
        ->expectsOutput('No translation tables found (*_translations).')
        ->assertSuccessful();
});

test('make:translatable-migration command creates migration file', function () {
    $this->artisan('make:translatable-migration', [
        'name' => 'create_test_items_table',
        '--create' => 'test_items',
    ])->assertSuccessful();

    // Find the created migration
    $migrations = File::glob(database_path('migrations/*_create_test_items_table.php'));

    expect($migrations)->toHaveCount(1);

    $content = File::get($migrations[0]);

    expect($content)->toContain('TranslatableSchema::create');
    expect($content)->toContain('test_items');

    // Clean up
    File::delete($migrations[0]);
});

test('make:translatable-migration creates update migration', function () {
    $this->artisan('make:translatable-migration', [
        'name' => 'add_subtitle_to_posts_table',
        '--table' => 'posts',
    ])->assertSuccessful();

    // Find the created migration
    $migrations = File::glob(database_path('migrations/*_add_subtitle_to_posts_table.php'));

    expect($migrations)->toHaveCount(1);

    $content = File::get($migrations[0]);

    expect($content)->toContain('TranslatableSchema::table');
    expect($content)->toContain('posts');

    // Clean up
    File::delete($migrations[0]);
});

test('make:translatable-migration auto-detects table name from create pattern', function () {
    $this->artisan('make:translatable-migration', [
        'name' => 'create_categories_table',
    ])->assertSuccessful();

    $migrations = File::glob(database_path('migrations/*_create_categories_table.php'));

    expect($migrations)->toHaveCount(1);

    $content = File::get($migrations[0]);

    expect($content)->toContain('categories');

    // Clean up
    File::delete($migrations[0]);
});
