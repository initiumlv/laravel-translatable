<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Initium\LaravelTranslatable\Components\Database\Blueprint;
use Initium\LaravelTranslatable\Facades\TranslatableSchema;

afterEach(function () {
    // Clean up any tables created during tests
    Schema::dropIfExists('category_translations');
    Schema::dropIfExists('categories');
    Schema::dropIfExists('article_translations');
    Schema::dropIfExists('articles');
});

test('it creates main table without translatable columns', function () {
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->integer('sort_order')->default(0);
        $table->string('name')->translatable();
        $table->text('description')->nullable()->translatable();
    });

    $columns = Schema::getColumnListing('categories');

    expect($columns)->toContain('id');
    expect($columns)->toContain('sort_order');
    expect($columns)->not->toContain('name');
    expect($columns)->not->toContain('description');
});

test('it creates translations table with correct structure', function () {
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name')->translatable();
    });

    $columns = Schema::getColumnListing('category_translations');

    expect($columns)->toContain('id');
    expect($columns)->toContain('category_id');
    expect($columns)->toContain('locale');
    expect($columns)->toContain('name');
});

test('it adds columns to existing translations table', function () {
    // Create initial table
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name')->translatable();
    });

    // Add new translatable column
    TranslatableSchema::table('categories', function (Blueprint $table) {
        $table->text('description')->nullable()->translatable();
    });

    $columns = Schema::getColumnListing('category_translations');

    expect($columns)->toContain('name');
    expect($columns)->toContain('description');
});

test('it drops translatable columns', function () {
    // Create initial table
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name')->translatable();
        $table->text('description')->nullable()->translatable();
    });

    // Drop translatable column
    TranslatableSchema::table('categories', function (Blueprint $table) {
        $table->dropTranslatable('description');
    });

    $columns = Schema::getColumnListing('category_translations');

    expect($columns)->toContain('name');
    expect($columns)->not->toContain('description');
});

test('it drops both main and translations tables', function () {
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name')->translatable();
    });

    expect(Schema::hasTable('categories'))->toBeTrue();
    expect(Schema::hasTable('category_translations'))->toBeTrue();

    TranslatableSchema::drop('categories');

    expect(Schema::hasTable('categories'))->toBeFalse();
    expect(Schema::hasTable('category_translations'))->toBeFalse();
});

test('dropIfExists drops both tables if they exist', function () {
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name')->translatable();
    });

    TranslatableSchema::dropIfExists('categories');

    expect(Schema::hasTable('categories'))->toBeFalse();
    expect(Schema::hasTable('category_translations'))->toBeFalse();
});

test('dropIfExists does not error when tables do not exist', function () {
    // Should not throw an exception
    TranslatableSchema::dropIfExists('nonexistent_table');

    expect(true)->toBeTrue();
});

test('it respects table_suffix config', function () {
    config()->set('translatable.table_suffix', '_trans');

    TranslatableSchema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->string('title')->translatable();
    });

    expect(Schema::hasTable('articles'))->toBeTrue();
    expect(Schema::hasTable('article_trans'))->toBeTrue();

    // Clean up
    Schema::dropIfExists('article_trans');
    Schema::dropIfExists('articles');

    // Reset config
    config()->set('translatable.table_suffix', '_translations');
});

test('it creates foreign key constraint', function () {
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name')->translatable();
    });

    // Insert a category
    $categoryId = DB::table('categories')->insertGetId([]);

    // Insert translation
    DB::table('category_translations')->insert([
        'category_id' => $categoryId,
        'locale' => 'en',
        'name' => 'Test',
    ]);

    // Delete category - should cascade
    DB::table('categories')->where('id', $categoryId)->delete();

    $translation = DB::table('category_translations')
        ->where('category_id', $categoryId)
        ->first();

    expect($translation)->toBeNull();
});

test('non-translatable columns stay in main table', function () {
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('slug');
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->string('name')->translatable();
    });

    $mainColumns = Schema::getColumnListing('categories');
    $transColumns = Schema::getColumnListing('category_translations');

    // Main table has non-translatable columns
    expect($mainColumns)->toContain('slug');
    expect($mainColumns)->toContain('is_active');
    expect($mainColumns)->toContain('sort_order');

    // Translations table has only translatable + system columns
    expect($transColumns)->toContain('name');
    expect($transColumns)->not->toContain('slug');
    expect($transColumns)->not->toContain('is_active');
    expect($transColumns)->not->toContain('sort_order');
});

test('connection method returns new builder instance', function () {
    $builder = TranslatableSchema::connection('testing');

    expect($builder)->toBeInstanceOf(\Initium\LaravelTranslatable\Components\Database\TranslatableSchemaBuilder::class);
});

test('it changes existing translatable columns', function () {
    // Create initial table with a string column
    TranslatableSchema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name')->translatable();
    });

    // Insert some data to verify it's preserved after change
    $categoryId = DB::table('categories')->insertGetId([]);
    DB::table('category_translations')->insert([
        'category_id' => $categoryId,
        'locale' => 'en',
        'name' => 'Original Name',
    ]);

    // Change the column type from string to text and make it nullable
    TranslatableSchema::table('categories', function (Blueprint $table) {
        $table->text('name')->nullable()->translatable()->change();
    });

    // Verify the column still exists and data is preserved
    $columns = Schema::getColumnListing('category_translations');
    expect($columns)->toContain('name');

    // Verify data is still there
    $translation = DB::table('category_translations')
        ->where('category_id', $categoryId)
        ->first();

    expect($translation)->not->toBeNull();
    expect($translation->name)->toBe('Original Name');
});
