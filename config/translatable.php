<?php

// config for Initium/LaravelTranslatable
return [

    /*
    |--------------------------------------------------------------------------
    | Missing Translation Strategy
    |--------------------------------------------------------------------------
    |
    | This option controls how the package handles records that don't have
    | a translation in the current locale.
    |
    | Supported: "strict", "nullable", "fallback"
    |
    | - "strict": Only return records that have a translation in the current
    |             locale (uses INNER JOIN). Records without translations are
    |             excluded from results.
    |
    | - "nullable": Return all records, but translatable columns will be NULL
    |               if no translation exists for the current locale (uses LEFT JOIN).
    |
    | - "fallback": Return all records, using the fallback locale's translation
    |               when the current locale's translation doesn't exist
    |               (uses LEFT JOIN with COALESCE).
    |
    */
    'missing_translation_strategy' => 'strict',

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | The path where translatable column cache will be stored.
    |
    */
    'cache_path' => 'bootstrap/cache/translatable.php',

    /*
    |--------------------------------------------------------------------------
    | Auto-cache After Migrations
    |--------------------------------------------------------------------------
    |
    | When enabled, the translatable cache will be automatically regenerated
    | after running migration commands.
    |
    */
    'auto_cache_after_migrate' => true,

    /*
    |--------------------------------------------------------------------------
    | Translation Table Suffix
    |--------------------------------------------------------------------------
    |
    | The suffix used for translation tables.
    |
    */
    'table_suffix' => '_translations',

    /*
    |--------------------------------------------------------------------------
    | System Columns
    |--------------------------------------------------------------------------
    |
    | Columns that should be excluded when detecting translatable columns.
    | These are the structural columns in the translations table.
    |
    */
    'system_columns' => ['id', 'locale'],

];
