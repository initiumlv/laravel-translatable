<?php

namespace Initium\LaravelTranslatable\Facades;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Initium\LaravelTranslatable\Components\Database\TranslatableSchemaBuilder;

/**
 * @method static void create(string $table, Closure $callback)
 * @method static void table(string $table, Closure $callback)
 * @method static void drop(string $table)
 * @method static void dropIfExists(string $table)
 * @method static bool hasTable(string $table)
 * @method static bool hasColumn(string $table, string $column)
 * @method static void dropColumns(string $table, array $columns)
 * @method static void rename(string $from, string $to)
 *
 * @see \Initium\LaravelTranslatable\Components\Database\TranslatableSchemaBuilder
 */
class TranslatableSchema extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'translatable.schema';
    }

    /**
     * Get a TranslatableSchemaBuilder instance for the default connection.
     */
    public static function connection(?string $name = null): TranslatableSchemaBuilder
    {
        $connection = DB::connection($name);

        return new TranslatableSchemaBuilder($connection);
    }
}
