<?php

namespace Initium\LaravelTranslatable\Components\Database;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

class TranslatableSchemaBuilder extends Builder
{
    /**
     * Create a new schema builder instance.
     *
     * Ensures the schema grammar is properly initialized.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        // Ensure schema grammar is initialized
        $grammar = $connection->getSchemaGrammar();
        if ($grammar === null) {
            $connection->useDefaultSchemaGrammar();
            $grammar = $connection->getSchemaGrammar();
        }

        $this->grammar = $grammar;
    }

    /**
     * Create a new table on the schema.
     */
    public function create($table, Closure $callback): void
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->create();

        $callback($blueprint);

        // Store translatable columns before stripping them
        $hasTranslatable = $blueprint->hasTranslatableColumns();
        $translatableColumns = $blueprint->getAllTranslatableColumns();

        // Remove translatable columns from main table
        $blueprint->stripTranslatableColumns();

        // Build main table (without translatable columns)
        $this->build($blueprint);

        // Create translations table if needed
        if ($hasTranslatable && count($translatableColumns) > 0) {
            $this->createTranslationsTableWithColumns($blueprint, $translatableColumns);
        }
    }

    /**
     * Modify a table on the schema.
     */
    public function table($table, Closure $callback): void
    {
        $blueprint = $this->createBlueprint($table);

        $callback($blueprint);

        // Store translatable info before stripping
        $hasTranslatable = $blueprint->hasTranslatableColumns();
        $newColumns = $blueprint->getTranslatableColumns();
        $changedColumns = $blueprint->getChangedTranslatableColumns();
        $droppedColumns = $blueprint->getDroppedTranslatableColumns();

        // Remove translatable columns from main table modifications
        $blueprint->stripTranslatableColumns();

        // Build main table modifications (without translatable columns)
        if (count($blueprint->getColumns()) > 0 || count($blueprint->getCommands()) > 0) {
            $this->build($blueprint);
        }

        // Sync translations table if needed
        if ($hasTranslatable) {
            $this->syncTranslationsTableWithColumns($blueprint, $newColumns, $changedColumns, $droppedColumns);
        }
    }

    /**
     * Drop a table from the schema.
     */
    public function drop($table): void
    {
        // Drop translations table first (if exists)
        $translationTable = $this->getTranslationTableName($table);
        if ($this->hasTable($translationTable)) {
            parent::drop($translationTable);
        }

        parent::drop($table);
    }

    /**
     * Drop a table from the schema if it exists.
     */
    public function dropIfExists($table): void
    {
        // Drop translations table first (if exists)
        $translationTable = $this->getTranslationTableName($table);
        parent::dropIfExists($translationTable);

        parent::dropIfExists($table);
    }

    /**
     * Get the translation table name for a given table.
     */
    protected function getTranslationTableName(string $table): string
    {
        $singular = str($table)->singular()->toString();
        $suffix = config('translatable.table_suffix', '_translations');

        return $singular.$suffix;
    }

    /**
     * Create a new command set with a Closure.
     */
    protected function createBlueprint($table, ?Closure $callback = null): Blueprint
    {
        return new Blueprint($this->connection, $table, $callback);
    }

    /**
     * Create the translations table with the given columns.
     *
     * @param  array<TranslatableColumnDefinition>  $translatableColumns
     */
    protected function createTranslationsTableWithColumns(Blueprint $mainBlueprint, array $translatableColumns): void
    {
        $translationTable = $mainBlueprint->getTranslationTableName();
        $foreignKey = $mainBlueprint->getTranslationForeignKey();
        $mainTable = $mainBlueprint->getTable();

        // Use base Blueprint for translations table to avoid recursion
        $this->connection->getSchemaBuilder()->create($translationTable, function (BaseBlueprint $table) use ($mainTable, $foreignKey, $translatableColumns) {
            $table->id();
            $table->foreignId($foreignKey)
                ->constrained($mainTable)
                ->cascadeOnDelete();
            $table->string('locale', 10)->index();

            foreach ($translatableColumns as $column) {
                self::addColumnToTranslationTable($table, $column);
            }

            $table->unique([$foreignKey, 'locale']);
        });
    }

    /**
     * Sync the translations table with the given columns.
     *
     * @param  array<TranslatableColumnDefinition>  $newColumns
     * @param  array<TranslatableColumnDefinition>  $changedColumns
     * @param  array<string>  $droppedColumns
     */
    protected function syncTranslationsTableWithColumns(
        Blueprint $mainBlueprint,
        array $newColumns,
        array $changedColumns,
        array $droppedColumns
    ): void {
        $translationTable = $mainBlueprint->getTranslationTableName();

        // Create translations table if it doesn't exist and we have new columns
        if (! $this->hasTable($translationTable)) {
            if (count($newColumns) > 0) {
                $this->createTranslationsTableWithColumns($mainBlueprint, $newColumns);
            }

            return;
        }

        $schemaBuilder = $this->connection->getSchemaBuilder();

        // Get all existing columns once to avoid multiple database queries
        $existingColumns = Schema::getColumnListing($translationTable);

        // Drop columns from translations table
        if (! empty($droppedColumns)) {
            $schemaBuilder->table($translationTable, function (BaseBlueprint $table) use ($droppedColumns, $existingColumns) {
                $columnsToDrop = array_intersect($droppedColumns, $existingColumns);
                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }

        // Change existing columns in translations table
        if (! empty($changedColumns)) {
            $schemaBuilder->table($translationTable, function (BaseBlueprint $table) use ($changedColumns, $existingColumns) {
                foreach ($changedColumns as $column) {
                    if (in_array($column->name, $existingColumns)) {
                        self::modifyColumnInTranslationTable($table, $column);
                    }
                }
            });
        }

        // Add new translatable columns to existing translations table
        if (! empty($newColumns)) {
            $schemaBuilder->table($translationTable, function (BaseBlueprint $table) use ($newColumns, $existingColumns) {
                foreach ($newColumns as $column) {
                    // Only add if column doesn't exist
                    if (! in_array($column->name, $existingColumns)) {
                        self::addColumnToTranslationTable($table, $column);
                    }
                }
            });
        }
    }

    /**
     * Add a column to the translation table based on the original column definition.
     */
    protected static function addColumnToTranslationTable(BaseBlueprint $table, TranslatableColumnDefinition $column): void
    {
        $newColumn = self::createColumnByType($table, $column);
        self::applyColumnModifiers($newColumn, $column);
    }

    /**
     * Modify an existing column in the translation table.
     */
    protected static function modifyColumnInTranslationTable(BaseBlueprint $table, TranslatableColumnDefinition $column): void
    {
        $newColumn = self::createColumnByType($table, $column);
        self::applyColumnModifiers($newColumn, $column);
        $newColumn->change();
    }

    /**
     * Create a column by type.
     */
    protected static function createColumnByType(BaseBlueprint $table, TranslatableColumnDefinition $column): \Illuminate\Database\Schema\ColumnDefinition
    {
        $type = $column->type;
        $name = $column->name;
        $length = $column->length ?? null;

        return match ($type) {
            'string' => $table->string($name, $length ?? 255),
            'text' => $table->text($name),
            'mediumText' => $table->mediumText($name),
            'longText' => $table->longText($name),
            'json' => $table->json($name),
            default => $table->string($name, $length ?? 255),
        };
    }

    /**
     * Apply modifiers (nullable, default) to a column.
     */
    protected static function applyColumnModifiers(\Illuminate\Database\Schema\ColumnDefinition $newColumn, TranslatableColumnDefinition $column): void
    {
        $nullable = $column->nullable ?? false;
        $default = $column->default ?? null;

        if ($nullable) {
            $newColumn->nullable();
        }

        if ($default !== null) {
            $newColumn->default($default);
        }
    }
}
