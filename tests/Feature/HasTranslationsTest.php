<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Initium\LaravelTranslatable\Components\Database\Blueprint;
use Initium\LaravelTranslatable\Facades\TranslatableSchema;
use Initium\LaravelTranslatable\Tests\Fixtures\Product;

beforeEach(function () {
    // Create products table with translatable columns
    TranslatableSchema::create('products', function (Blueprint $table) {
        $table->id();
        $table->decimal('price', 10, 2)->default(0);
        $table->boolean('is_active')->default(true);
        $table->string('name')->translatable();
        $table->text('description')->nullable()->translatable();
    });

    // Manually set the cache since we're in tests
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('translatableCache');
    $property->setAccessible(true);
    $property->setValue(null, [
        'product_translations' => ['name', 'description'],
    ]);
});

afterEach(function () {
    TranslatableSchema::dropIfExists('products');

    // Reset cache
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('translatableCache');
    $property->setAccessible(true);
    $property->setValue(null, null);
});

test('it creates main table and translations table', function () {
    expect(Schema::hasTable('products'))->toBeTrue();
    expect(Schema::hasTable('product_translations'))->toBeTrue();
});

test('translations table has correct columns', function () {
    $columns = Schema::getColumnListing('product_translations');

    expect($columns)->toContain('id');
    expect($columns)->toContain('product_id');
    expect($columns)->toContain('locale');
    expect($columns)->toContain('name');
    expect($columns)->toContain('description');
});

test('main table does not have translatable columns', function () {
    $columns = Schema::getColumnListing('products');

    expect($columns)->toContain('id');
    expect($columns)->toContain('price');
    expect($columns)->toContain('is_active');
    expect($columns)->not->toContain('name');
    expect($columns)->not->toContain('description');
});

test('it can create a model with translations', function () {
    App::setLocale('en');

    // Insert main record
    $productId = DB::table('products')->insertGetId([
        'price' => 99.99,
        'is_active' => true,
    ]);

    // Insert translation
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'Test Product',
        'description' => 'A test product description',
    ]);

    $product = Product::find($productId);

    expect($product)->not->toBeNull();
    expect($product->name)->toBe('Test Product');
    expect($product->description)->toBe('A test product description');
    expect((float) $product->price)->toBe(99.99);
});

test('it retrieves translations based on current locale', function () {
    // Insert main record
    $productId = DB::table('products')->insertGetId([
        'price' => 50.00,
        'is_active' => true,
    ]);

    // Insert English translation
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'English Name',
        'description' => 'English description',
    ]);

    // Insert Latvian translation
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'lv',
        'name' => 'Latvian Name',
        'description' => 'Latvian description',
    ]);

    // Test English locale
    App::setLocale('en');
    $product = Product::find($productId);
    expect($product->name)->toBe('English Name');

    // Test Latvian locale
    App::setLocale('lv');
    $product = Product::find($productId);
    expect($product->name)->toBe('Latvian Name');
});

test('it can save translations via model', function () {
    App::setLocale('en');

    // Create product directly
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);

    $product = Product::withoutTranslations()->find($productId);
    $product->name = 'New Product';
    $product->description = 'New description';
    $product->save();

    // Verify translation was saved
    $translation = DB::table('product_translations')
        ->where('product_id', $productId)
        ->where('locale', 'en')
        ->first();

    expect($translation)->not->toBeNull();
    expect($translation->name)->toBe('New Product');
    expect($translation->description)->toBe('New description');
});

test('it can save translations for specific locale', function () {
    // Create product directly
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);

    $product = Product::withoutTranslations()->find($productId);
    $product->name = 'Latvian Product';
    $product->save(locale: 'lv');

    // Verify Latvian translation was saved
    $translation = DB::table('product_translations')
        ->where('product_id', $productId)
        ->where('locale', 'lv')
        ->first();

    expect($translation)->not->toBeNull();
    expect($translation->name)->toBe('Latvian Product');
});

test('it can save multiple translations at once', function () {
    // Create product directly
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);

    $product = Product::withoutTranslations()->find($productId);
    $product->saveTranslations([
        'en' => ['name' => 'English Product', 'description' => 'English desc'],
        'lv' => ['name' => 'Latvian Product', 'description' => 'Latvian desc'],
        'de' => ['name' => 'German Product', 'description' => 'German desc'],
    ]);

    // Verify all translations
    $translations = DB::table('product_translations')
        ->where('product_id', $productId)
        ->get()
        ->keyBy('locale');

    expect($translations)->toHaveCount(3);
    expect($translations['en']->name)->toBe('English Product');
    expect($translations['lv']->name)->toBe('Latvian Product');
    expect($translations['de']->name)->toBe('German Product');
});

test('it can update existing translations', function () {
    App::setLocale('en');

    // Create product with translation
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);

    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'Original Name',
        'description' => 'Original description',
    ]);

    // Update via model
    $product = Product::find($productId);
    $product->name = 'Updated Name';
    $product->save();

    // Verify update
    $translation = DB::table('product_translations')
        ->where('product_id', $productId)
        ->where('locale', 'en')
        ->first();

    expect($translation->name)->toBe('Updated Name');
});

test('deleting model cascades to translations', function () {
    // Create product with translations
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);

    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'Test',
        'description' => null,
    ]);

    // Delete product
    DB::table('products')->where('id', $productId)->delete();

    // Verify translation is also deleted (cascade)
    $translation = DB::table('product_translations')
        ->where('product_id', $productId)
        ->first();

    expect($translation)->toBeNull();
});

test('withoutTranslations scope returns all records', function () {
    // Create product without translation
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);

    // Without scope, product won't be found (no translation)
    App::setLocale('en');
    $product = Product::find($productId);
    expect($product)->toBeNull();

    // With scope, product is found
    $product = Product::withoutTranslations()->find($productId);
    expect($product)->not->toBeNull();
    expect((float) $product->price)->toBe(100.00);
});

test('it gets correct translation table name', function () {
    $product = new Product;

    expect($product->getTranslationTableName())->toBe('product_translations');
});

test('it gets correct foreign key name', function () {
    $product = new Product;

    expect($product->getTranslationForeignKey())->toBe('product_id');
});
