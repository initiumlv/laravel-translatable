<?php

use Initium\LaravelTranslatable\Components\Database\Blueprint;
use Initium\LaravelTranslatable\Components\Database\TranslatableColumnDefinition;

test('string column returns TranslatableColumnDefinition', function () {
    $blueprint = new Blueprint(null, 'test_table');

    $column = $blueprint->string('name');

    expect($column)->toBeInstanceOf(TranslatableColumnDefinition::class);
});

test('translatable method marks column as translatable', function () {
    $blueprint = new Blueprint(null, 'test_table');

    $column = $blueprint->string('name')->translatable();

    expect($column->isTranslatable())->toBeTrue();
});

test('column without translatable is not translatable', function () {
    $blueprint = new Blueprint(null, 'test_table');

    $column = $blueprint->string('slug');

    expect($column->isTranslatable())->toBeFalse();
});

test('getTranslatableColumns returns only translatable columns', function () {
    $blueprint = new Blueprint(null, 'test_table');

    $blueprint->string('slug');
    $blueprint->string('name')->translatable();
    $blueprint->text('description')->translatable();

    $translatable = $blueprint->getTranslatableColumns();

    expect($translatable)->toHaveCount(2);
});

test('hasTranslatableColumns returns true when translatable columns exist', function () {
    $blueprint = new Blueprint(null, 'test_table');

    $blueprint->string('name')->translatable();

    expect($blueprint->hasTranslatableColumns())->toBeTrue();
});

test('hasTranslatableColumns returns false when no translatable columns', function () {
    $blueprint = new Blueprint(null, 'test_table');

    $blueprint->string('slug');

    expect($blueprint->hasTranslatableColumns())->toBeFalse();
});

test('stripTranslatableColumns removes translatable columns', function () {
    $blueprint = new Blueprint(null, 'test_table');

    $blueprint->string('slug');
    $blueprint->string('name')->translatable();

    $blueprint->stripTranslatableColumns();

    expect($blueprint->getColumns())->toHaveCount(1);
    expect($blueprint->getTranslatableColumns())->toHaveCount(0);
});

test('getTranslationTableName returns correct name', function () {
    $blueprint = new Blueprint(null, 'products');

    expect($blueprint->getTranslationTableName())->toBe('product_translations');
});

test('getTranslationTableName respects config suffix', function () {
    config()->set('translatable.table_suffix', '_trans');

    $blueprint = new Blueprint(null, 'products');

    expect($blueprint->getTranslationTableName())->toBe('product_trans');

    config()->set('translatable.table_suffix', '_translations');
});

test('getTranslationForeignKey returns correct key', function () {
    $blueprint = new Blueprint(null, 'products');

    expect($blueprint->getTranslationForeignKey())->toBe('product_id');
});

test('dropTranslatable adds columns to dropped list', function () {
    $blueprint = new Blueprint(null, 'products');

    $blueprint->dropTranslatable('name');
    $blueprint->dropTranslatable(['description', 'content']);

    expect($blueprint->getDroppedTranslatableColumns())->toBe(['name', 'description', 'content']);
});

test('text column supports translatable', function () {
    $blueprint = new Blueprint(null, 'test');

    $column = $blueprint->text('content')->translatable();

    expect($column->isTranslatable())->toBeTrue();
    expect($column->type)->toBe('text');
});

test('mediumText column supports translatable', function () {
    $blueprint = new Blueprint(null, 'test');

    $column = $blueprint->mediumText('content')->translatable();

    expect($column->isTranslatable())->toBeTrue();
    expect($column->type)->toBe('mediumText');
});

test('longText column supports translatable', function () {
    $blueprint = new Blueprint(null, 'test');

    $column = $blueprint->longText('content')->translatable();

    expect($column->isTranslatable())->toBeTrue();
    expect($column->type)->toBe('longText');
});

test('json column supports translatable', function () {
    $blueprint = new Blueprint(null, 'test');

    $column = $blueprint->json('metadata')->translatable();

    expect($column->isTranslatable())->toBeTrue();
    expect($column->type)->toBe('json');
});

test('nullable modifier works on translatable columns', function () {
    $blueprint = new Blueprint(null, 'test');

    $column = $blueprint->string('name')->nullable()->translatable();

    expect($column->nullable)->toBeTrue();
    expect($column->isTranslatable())->toBeTrue();
});

test('change modifier works on translatable columns', function () {
    $blueprint = new Blueprint(null, 'test');

    $column = $blueprint->string('name')->translatable()->change();

    expect($column->isChange())->toBeTrue();
    expect($column->isTranslatable())->toBeTrue();
});

test('getChangedTranslatableColumns returns only changed columns', function () {
    $blueprint = new Blueprint(null, 'test');

    $blueprint->string('name')->translatable();
    $blueprint->string('title')->translatable()->change();

    $changed = $blueprint->getChangedTranslatableColumns();

    expect($changed)->toHaveCount(1);
});
