<?php

namespace Initium\LaravelTranslatable\Components\Database\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class TranslationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $locale = app()->getLocale();
        $table = $model->getTable();
        $translationTable = $model->getTranslationTableName();
        $foreignKey = $model->getTranslationForeignKey();
        $translatableColumns = $model->getTranslatableAttributes();
        $strategy = config('translatable.missing_translation_strategy', 'strict');

        match ($strategy) {
            'nullable' => $this->applyNullableStrategy($builder, $table, $translationTable, $foreignKey, $translatableColumns, $locale),
            'fallback' => $this->applyFallbackStrategy($builder, $table, $translationTable, $foreignKey, $translatableColumns, $locale),
            default => $this->applyStrictStrategy($builder, $table, $translationTable, $foreignKey, $translatableColumns, $locale),
        };

        // Fix ambiguous column references in WHERE clauses
        $query = $builder->getQuery();
        $this->qualifyWhereClauses($query, $table, $translatableColumns);
    }

    /**
     * Apply strict strategy - INNER JOIN, only records with translations.
     */
    protected function applyStrictStrategy(
        Builder $builder,
        string $table,
        string $translationTable,
        string $foreignKey,
        array $translatableColumns,
        string $locale
    ): void {
        $builder->join("{$translationTable} as t", function ($join) use ($table, $foreignKey, $locale) {
            $join->on("{$table}.id", '=', "t.{$foreignKey}")
                ->where('t.locale', '=', $locale);
        });

        // Select: main table + translatable columns from JOIN
        $selects = ["{$table}.*"];
        foreach ($translatableColumns as $col) {
            $selects[] = "t.{$col}";
        }
        $builder->select($selects);
    }

    /**
     * Apply nullable strategy - LEFT JOIN, null values if no translation exists.
     */
    protected function applyNullableStrategy(
        Builder $builder,
        string $table,
        string $translationTable,
        string $foreignKey,
        array $translatableColumns,
        string $locale
    ): void {
        $builder->leftJoin("{$translationTable} as t", function ($join) use ($table, $foreignKey, $locale) {
            $join->on("{$table}.id", '=', "t.{$foreignKey}")
                ->where('t.locale', '=', $locale);
        });

        // Select: main table + translatable columns from JOIN (will be NULL if no translation)
        $selects = ["{$table}.*"];
        foreach ($translatableColumns as $col) {
            $selects[] = "t.{$col}";
        }
        $builder->select($selects);
    }

    /**
     * Apply fallback strategy - LEFT JOIN with COALESCE to fallback locale.
     */
    protected function applyFallbackStrategy(
        Builder $builder,
        string $table,
        string $translationTable,
        string $foreignKey,
        array $translatableColumns,
        string $locale
    ): void {
        $fallbackLocale = config('app.fallback_locale', 'en');

        // Skip fallback join if current locale is the fallback locale
        if ($locale === $fallbackLocale) {
            $this->applyNullableStrategy($builder, $table, $translationTable, $foreignKey, $translatableColumns, $locale);

            return;
        }

        // LEFT JOIN for current locale
        $builder->leftJoin("{$translationTable} as t", function ($join) use ($table, $foreignKey, $locale) {
            $join->on("{$table}.id", '=', "t.{$foreignKey}")
                ->where('t.locale', '=', $locale);
        });

        // LEFT JOIN for fallback locale
        $builder->leftJoin("{$translationTable} as t_fallback", function ($join) use ($table, $foreignKey, $fallbackLocale) {
            $join->on("{$table}.id", '=', "t_fallback.{$foreignKey}")
                ->where('t_fallback.locale', '=', $fallbackLocale);
        });

        // Select: main table + COALESCE translatable columns (current locale, then fallback)
        $selects = ["{$table}.*"];
        foreach ($translatableColumns as $col) {
            $selects[] = DB::raw("COALESCE(t.{$col}, t_fallback.{$col}) as {$col}");
        }
        $builder->select($selects);
    }

    /**
     * Qualify ambiguous column references in WHERE clauses.
     */
    protected function qualifyWhereClauses($query, string $table, array $translatableColumns): void
    {
        if (isset($query->wheres)) {
            foreach ($query->wheres as &$where) {
                if ($where['type'] === 'Basic' && isset($where['column'])) {
                    // Qualify 'id' column to refer to the main table
                    if ($where['column'] === 'id') {
                        $where['column'] = "{$table}.id";
                    }
                    // For translatable columns, search in the translation table
                    elseif (in_array($where['column'], $translatableColumns)) {
                        // Change the column reference to search in the joined table
                        $where['column'] = "t.{$where['column']}";
                    }
                }
            }
        }

        // Also check nested where clauses
        if (isset($query->wheres) && is_array($query->wheres)) {
            foreach ($query->wheres as &$where) {
                if (isset($where['query']) && is_object($where['query'])) {
                    $this->qualifyWhereClauses($where['query'], $table, $translatableColumns);
                }
            }
        }
    }
}
