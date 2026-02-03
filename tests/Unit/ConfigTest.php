<?php

test('config has cache_path', function () {
    expect(config('translatable.cache_path'))->toBe('bootstrap/cache/translatable.php');
});

test('config has auto_cache_after_migrate', function () {
    expect(config('translatable.auto_cache_after_migrate'))->toBeFalse(); // Set to false in TestCase
});

test('config has table_suffix', function () {
    expect(config('translatable.table_suffix'))->toBe('_translations');
});

test('config has system_columns', function () {
    $systemColumns = config('translatable.system_columns');

    expect($systemColumns)->toBeArray();
    expect($systemColumns)->toContain('id');
    expect($systemColumns)->toContain('locale');
});
