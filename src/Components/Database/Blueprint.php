<?php

namespace Initium\LaravelTranslatable\Components\Database;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;

class Blueprint extends BaseBlueprint
{
    /**
     * Translatable columns to be dropped.
     *
     * @var array<string>
     */
    protected array $droppedTranslatableColumns = [];

    /**
     * Cache for filtered translatable columns.
     *
     * @var array<string, array<TranslatableColumnDefinition>>|null
     */
    protected ?array $translatableColumnsCache = null;

    /**
     * Create a new schema blueprint.
     *
     * Overrides the parent constructor to allow null connection for unit testing
     * and to handle cases where schema grammar isn't initialized yet.
     */
    public function __construct(?Connection $connection, string $table, ?Closure $callback = null)
    {
        $this->table = $table;

        if ($connection !== null) {
            $this->connection = $connection;

            // Ensure schema grammar is initialized on the connection
            $grammar = $connection->getSchemaGrammar();
            if ($grammar === null) {
                $connection->useDefaultSchemaGrammar();
                $grammar = $connection->getSchemaGrammar();
            }

            // Only set grammar if we have one (might still be null in edge cases)
            if ($grammar !== null) {
                $this->grammar = $grammar;
            }
        }

        if (! is_null($callback)) {
            $callback($this);
        }
    }

    /**
     * Get the columns that are marked as translatable (new columns).
     *
     * @return array<TranslatableColumnDefinition>
     */
    public function getTranslatableColumns(): array
    {
        return array_filter($this->columns, function (Fluent $column) {
            return $column instanceof TranslatableColumnDefinition
                && $column->isTranslatable()
                && ! $column->isChange();
        });
    }

    /**
     * Get the columns that are marked as translatable and need to be changed.
     *
     * @return array<TranslatableColumnDefinition>
     */
    public function getChangedTranslatableColumns(): array
    {
        return array_filter($this->columns, function (Fluent $column) {
            return $column instanceof TranslatableColumnDefinition
                && $column->isTranslatable()
                && $column->isChange();
        });
    }

    /**
     * Get all translatable columns (new + changed).
     *
     * @return array<TranslatableColumnDefinition>
     */
    public function getAllTranslatableColumns(): array
    {
        return array_filter($this->columns, function (Fluent $column) {
            return $column instanceof TranslatableColumnDefinition && $column->isTranslatable();
        });
    }

    /**
     * Check if this blueprint has any translatable columns.
     */
    public function hasTranslatableColumns(): bool
    {
        return count($this->getAllTranslatableColumns()) > 0
            || count($this->droppedTranslatableColumns) > 0;
    }

    /**
     * Check if this blueprint has changed translatable columns.
     */
    public function hasChangedTranslatableColumns(): bool
    {
        return count($this->getChangedTranslatableColumns()) > 0;
    }

    /**
     * Drop a translatable column from the translations table.
     *
     * @param  string|array<string>  $columns
     */
    public function dropTranslatable(string|array $columns): static
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $column) {
            $this->droppedTranslatableColumns[] = $column;
        }

        return $this;
    }

    /**
     * Get the columns that should be dropped from translations table.
     *
     * @return array<string>
     */
    public function getDroppedTranslatableColumns(): array
    {
        return $this->droppedTranslatableColumns;
    }

    /**
     * Check if there are columns to drop from translations table.
     */
    public function hasDroppedTranslatableColumns(): bool
    {
        return count($this->droppedTranslatableColumns) > 0;
    }

    /**
     * Remove translatable columns from the main table blueprint.
     * Call this before building the main table to exclude translatable columns.
     */
    public function stripTranslatableColumns(): void
    {
        $this->columns = array_filter($this->columns, function (Fluent $column) {
            // Keep column if it's NOT a translatable TranslatableColumnDefinition
            if ($column instanceof TranslatableColumnDefinition) {
                return ! $column->isTranslatable();
            }

            return true;
        });

        // Re-index the array
        $this->columns = array_values($this->columns);
    }

    /**
     * Get the translation table name.
     * Uses singular form: products -> product_translations
     */
    public function getTranslationTableName(): string
    {
        $table = $this->getTable();
        $singular = str($table)->singular()->toString();
        $suffix = config('translatable.table_suffix', '_translations');

        return $singular.$suffix;
    }

    /**
     * Get the foreign key name for translations.
     */
    public function getTranslationForeignKey(): string
    {
        $table = $this->getTable();
        $singular = str($table)->singular()->toString();

        return $singular.'_id';
    }

    /**
     * Create a new string column with translatable support.
     */
    public function string($column, $length = null): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('string', $column, compact('length'));
    }

    /**
     * Create a new text column with translatable support.
     */
    public function text($column): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('text', $column);
    }

    /**
     * Create a new medium text column with translatable support.
     */
    public function mediumText($column): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('mediumText', $column);
    }

    /**
     * Create a new long text column with translatable support.
     */
    public function longText($column): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('longText', $column);
    }

    /**
     * Create a new JSON column with translatable support.
     */
    public function json($column): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('json', $column);
    }

    /**
     * Create a new integer column with translatable support.
     */
    public function integer($column, $autoIncrement = false, $unsigned = false): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new tiny integer column with translatable support.
     */
    public function tinyInteger($column, $autoIncrement = false, $unsigned = false): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new small integer column with translatable support.
     */
    public function smallInteger($column, $autoIncrement = false, $unsigned = false): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new medium integer column with translatable support.
     */
    public function mediumInteger($column, $autoIncrement = false, $unsigned = false): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new big integer column with translatable support.
     */
    public function bigInteger($column, $autoIncrement = false, $unsigned = false): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new unsigned integer column with translatable support.
     */
    public function unsignedInteger($column, $autoIncrement = false): TranslatableColumnDefinition
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned tiny integer column with translatable support.
     */
    public function unsignedTinyInteger($column, $autoIncrement = false): TranslatableColumnDefinition
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer column with translatable support.
     */
    public function unsignedSmallInteger($column, $autoIncrement = false): TranslatableColumnDefinition
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer column with translatable support.
     */
    public function unsignedMediumInteger($column, $autoIncrement = false): TranslatableColumnDefinition
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer column with translatable support.
     */
    public function unsignedBigInteger($column, $autoIncrement = false): TranslatableColumnDefinition
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new decimal column with translatable support.
     */
    public function decimal($column, $total = 8, $places = 2): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('decimal', $column, compact('total', 'places'));
    }

    /**
     * Create a new unsigned decimal column with translatable support.
     */
    public function unsignedDecimal($column, $total = 8, $places = 2): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('decimal', $column, [
            'total' => $total,
            'places' => $places,
            'unsigned' => true,
        ]);
    }

    /**
     * Create a new float column with translatable support.
     */
    public function float($column, $precision = 53): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('float', $column, compact('precision'));
    }

    /**
     * Create a new double column with translatable support.
     */
    public function double($column): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('double', $column);
    }

    /**
     * Create a new boolean column with translatable support.
     */
    public function boolean($column): TranslatableColumnDefinition
    {
        return $this->addTranslatableColumn('boolean', $column);
    }

    /**
     * Add a translatable column definition to the blueprint.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function addTranslatableColumn(string $type, string $name, array $parameters = []): TranslatableColumnDefinition
    {
        $column = new TranslatableColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        );

        $this->columns[] = $column;

        // Initialize fluent methods if grammar is available (Laravel 12+)
        if (isset($this->grammar) && $this->grammar !== null && method_exists($column, 'addFluentMethods')) {
            $column->addFluentMethods($this->grammar);
        }

        return $column;
    }

    /**
     * Get column definition by name (for internal use).
     *
     * @return array{type: string, length: int|null, nullable: bool, default: mixed, precision: int|null, scale: int|null, total: int|null, places: int|null, unsigned: bool, autoIncrement: bool}|null
     */
    public function getColumnAttributes(string $name): ?array
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return [
                    'type' => $column->type,
                    'length' => $column->length ?? null,
                    'nullable' => $column->nullable ?? false,
                    'default' => $column->default ?? null,
                    'precision' => $column->precision ?? null,
                    'scale' => $column->scale ?? null,
                    'total' => $column->total ?? null,
                    'places' => $column->places ?? null,
                    'unsigned' => $column->unsigned ?? false,
                    'autoIncrement' => $column->autoIncrement ?? false,
                ];
            }
        }

        return null;
    }
}
