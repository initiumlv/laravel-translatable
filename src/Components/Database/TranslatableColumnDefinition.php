<?php

namespace Initium\LaravelTranslatable\Components\Database;

use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\Grammar;

class TranslatableColumnDefinition extends ColumnDefinition
{
    /**
     * Add the fluent commands specified via the grammar.
     *
     * This method is required by Laravel 12's Blueprint for proper column initialization.
     */
    public function addFluentMethods(Grammar $grammar): static
    {
        foreach ($grammar->getFluentCommands() as $commandName) {
            $this->attributes[$commandName] = null;
        }

        return $this;
    }

    /**
     * Mark this column as translatable.
     */
    public function translatable(): static
    {
        $this->attributes['translatable'] = true;

        return $this;
    }

    /**
     * Check if this column is marked as translatable.
     */
    public function isTranslatable(): bool
    {
        return $this->attributes['translatable'] ?? false;
    }

    /**
     * Check if this column is being changed/modified.
     */
    public function isChange(): bool
    {
        return $this->attributes['change'] ?? false;
    }

    /**
     * Indicate that the column should be changed.
     */
    public function change(): static
    {
        $this->attributes['change'] = true;

        return $this;
    }
}
