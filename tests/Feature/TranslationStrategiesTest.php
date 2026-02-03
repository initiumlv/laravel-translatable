<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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

    // Set up fallback locale
    Config::set('app.fallback_locale', 'en');
});

afterEach(function () {
    TranslatableSchema::dropIfExists('products');

    // Reset cache
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('translatableCache');
    $property->setAccessible(true);
    $property->setValue(null, null);

    // Reset strategy to default
    Config::set('translatable.missing_translation_strategy', 'strict');
});

/*
|--------------------------------------------------------------------------
| Strict Strategy Tests (Default)
|--------------------------------------------------------------------------
*/

test('strict strategy excludes records without translations', function () {
    Config::set('translatable.missing_translation_strategy', 'strict');
    App::setLocale('en');

    // Create product with English translation
    $productWithTranslation = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productWithTranslation,
        'locale' => 'en',
        'name' => 'English Product',
        'description' => 'English description',
    ]);

    // Create product without translation
    $productWithoutTranslation = DB::table('products')->insertGetId([
        'price' => 200,
        'is_active' => true,
    ]);

    // Query products
    $products = Product::all();

    expect($products)->toHaveCount(1);
    expect($products->first()->id)->toBe($productWithTranslation);
    expect($products->first()->name)->toBe('English Product');
});

test('strict strategy excludes records when locale translation is missing', function () {
    Config::set('translatable.missing_translation_strategy', 'strict');

    // Create product with only English translation
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'English Only',
        'description' => 'English description',
    ]);

    // Query in Latvian - should find nothing
    App::setLocale('lv');
    $products = Product::all();

    expect($products)->toHaveCount(0);

    // Query in English - should find the product
    App::setLocale('en');
    $products = Product::all();

    expect($products)->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Nullable Strategy Tests
|--------------------------------------------------------------------------
*/

test('nullable strategy includes records without translations with null values', function () {
    Config::set('translatable.missing_translation_strategy', 'nullable');
    App::setLocale('en');

    // Create product with translation
    $productWithTranslation = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productWithTranslation,
        'locale' => 'en',
        'name' => 'English Product',
        'description' => 'English description',
    ]);

    // Create product without translation
    $productWithoutTranslation = DB::table('products')->insertGetId([
        'price' => 200,
        'is_active' => true,
    ]);

    // Query products
    $products = Product::orderBy('products.id')->get();

    expect($products)->toHaveCount(2);

    // First product has translation
    expect($products[0]->id)->toBe($productWithTranslation);
    expect($products[0]->name)->toBe('English Product');

    // Second product has null values for translatable columns
    expect($products[1]->id)->toBe($productWithoutTranslation);
    expect($products[1]->name)->toBeNull();
    expect($products[1]->description)->toBeNull();
    expect((float) $products[1]->price)->toBe(200.00);
});

test('nullable strategy returns null for missing locale translations', function () {
    Config::set('translatable.missing_translation_strategy', 'nullable');

    // Create product with only English translation
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'English Name',
        'description' => 'English description',
    ]);

    // Query in Latvian - should find product with null translations
    App::setLocale('lv');
    $product = Product::find($productId);

    expect($product)->not->toBeNull();
    expect($product->name)->toBeNull();
    expect($product->description)->toBeNull();
    expect((float) $product->price)->toBe(100.00);

    // Query in English - should find product with translations
    App::setLocale('en');
    $product = Product::find($productId);

    expect($product->name)->toBe('English Name');
});

/*
|--------------------------------------------------------------------------
| Fallback Strategy Tests
|--------------------------------------------------------------------------
*/

test('fallback strategy uses fallback locale when current locale translation is missing', function () {
    Config::set('translatable.missing_translation_strategy', 'fallback');
    Config::set('app.fallback_locale', 'en');

    // Create product with only English translation
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'English Fallback Name',
        'description' => 'English fallback description',
    ]);

    // Query in Latvian - should get English fallback
    App::setLocale('lv');
    $product = Product::find($productId);

    expect($product)->not->toBeNull();
    expect($product->name)->toBe('English Fallback Name');
    expect($product->description)->toBe('English fallback description');
});

test('fallback strategy prefers current locale over fallback when both exist', function () {
    Config::set('translatable.missing_translation_strategy', 'fallback');
    Config::set('app.fallback_locale', 'en');

    // Create product with both English and Latvian translations
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'English Name',
        'description' => 'English description',
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'lv',
        'name' => 'Latvian Name',
        'description' => 'Latvian description',
    ]);

    // Query in Latvian - should get Latvian translation, not fallback
    App::setLocale('lv');
    $product = Product::find($productId);

    expect($product->name)->toBe('Latvian Name');
    expect($product->description)->toBe('Latvian description');

    // Query in English - should get English translation
    App::setLocale('en');
    $product = Product::find($productId);

    expect($product->name)->toBe('English Name');
});

test('fallback strategy returns null when neither current nor fallback locale exists', function () {
    Config::set('translatable.missing_translation_strategy', 'fallback');
    Config::set('app.fallback_locale', 'en');

    // Create product with only German translation (not the fallback)
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'de',
        'name' => 'German Name',
        'description' => 'German description',
    ]);

    // Query in Latvian - fallback is English, but no English translation exists
    App::setLocale('lv');
    $product = Product::find($productId);

    expect($product)->not->toBeNull();
    expect($product->name)->toBeNull();
    expect($product->description)->toBeNull();
});

test('fallback strategy includes records without any translations', function () {
    Config::set('translatable.missing_translation_strategy', 'fallback');
    Config::set('app.fallback_locale', 'en');

    // Create product without any translations
    $productId = DB::table('products')->insertGetId([
        'price' => 150,
        'is_active' => true,
    ]);

    App::setLocale('lv');
    $product = Product::find($productId);

    expect($product)->not->toBeNull();
    expect($product->name)->toBeNull();
    expect((float) $product->price)->toBe(150.00);
});

test('fallback strategy uses nullable strategy when current locale equals fallback locale', function () {
    Config::set('translatable.missing_translation_strategy', 'fallback');
    Config::set('app.fallback_locale', 'en');

    // Create product without translation
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);

    // Query in English (same as fallback) - should behave like nullable
    App::setLocale('en');
    $product = Product::find($productId);

    expect($product)->not->toBeNull();
    expect($product->name)->toBeNull();
});

test('fallback strategy handles partial translations correctly', function () {
    Config::set('translatable.missing_translation_strategy', 'fallback');
    Config::set('app.fallback_locale', 'en');

    // Create product with English having both fields, Latvian having only name
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'English Name',
        'description' => 'English description',
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'lv',
        'name' => 'Latvian Name',
        'description' => null, // No Latvian description
    ]);

    // Query in Latvian - name from Latvian, description falls back to English
    App::setLocale('lv');
    $product = Product::find($productId);

    expect($product->name)->toBe('Latvian Name');
    // COALESCE will use English description since Latvian is null
    expect($product->description)->toBe('English description');
});

/*
|--------------------------------------------------------------------------
| Strategy Switching Tests
|--------------------------------------------------------------------------
*/

test('strategy can be changed at runtime', function () {
    // Create product with only English translation
    $productId = DB::table('products')->insertGetId([
        'price' => 100,
        'is_active' => true,
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $productId,
        'locale' => 'en',
        'name' => 'English Name',
        'description' => 'English description',
    ]);

    App::setLocale('lv');

    // Strict - should not find product
    Config::set('translatable.missing_translation_strategy', 'strict');
    $strictResult = Product::find($productId);
    expect($strictResult)->toBeNull();

    // Nullable - should find product with null translations
    Config::set('translatable.missing_translation_strategy', 'nullable');
    $nullableResult = Product::find($productId);
    expect($nullableResult)->not->toBeNull();
    expect($nullableResult->name)->toBeNull();

    // Fallback - should find product with English fallback
    Config::set('translatable.missing_translation_strategy', 'fallback');
    Config::set('app.fallback_locale', 'en');
    $fallbackResult = Product::find($productId);
    expect($fallbackResult)->not->toBeNull();
    expect($fallbackResult->name)->toBe('English Name');
});

/*
|--------------------------------------------------------------------------
| Query Builder Tests with Strategies
|--------------------------------------------------------------------------
*/

test('strategies work correctly with where clauses on translatable columns', function () {
    Config::set('translatable.missing_translation_strategy', 'nullable');
    App::setLocale('en');

    // Create products
    $product1 = DB::table('products')->insertGetId(['price' => 100, 'is_active' => true]);
    $product2 = DB::table('products')->insertGetId(['price' => 200, 'is_active' => true]);

    DB::table('product_translations')->insert([
        'product_id' => $product1,
        'locale' => 'en',
        'name' => 'Searchable Product',
        'description' => 'Description',
    ]);
    DB::table('product_translations')->insert([
        'product_id' => $product2,
        'locale' => 'en',
        'name' => 'Another Product',
        'description' => 'Description',
    ]);

    // Search by translatable column
    $results = Product::where('name', 'Searchable Product')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($product1);
});

test('strategies work correctly with orderBy on translatable columns', function () {
    Config::set('translatable.missing_translation_strategy', 'strict');
    App::setLocale('en');

    // Create products
    $product1 = DB::table('products')->insertGetId(['price' => 100, 'is_active' => true]);
    $product2 = DB::table('products')->insertGetId(['price' => 200, 'is_active' => true]);
    $product3 = DB::table('products')->insertGetId(['price' => 300, 'is_active' => true]);

    DB::table('product_translations')->insert([
        ['product_id' => $product1, 'locale' => 'en', 'name' => 'Zebra', 'description' => null],
        ['product_id' => $product2, 'locale' => 'en', 'name' => 'Apple', 'description' => null],
        ['product_id' => $product3, 'locale' => 'en', 'name' => 'Mango', 'description' => null],
    ]);

    // Order by translatable column
    $results = Product::orderBy('t.name', 'asc')->get();

    expect($results)->toHaveCount(3);
    expect($results[0]->name)->toBe('Apple');
    expect($results[1]->name)->toBe('Mango');
    expect($results[2]->name)->toBe('Zebra');
});
