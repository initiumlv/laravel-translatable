<?php

namespace Initium\LaravelTranslatable\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeTranslatableMigrationCommand extends Command
{
    protected $signature = 'make:translatable-migration {name : The name of the migration}
                            {--table= : The table name}
                            {--create= : The table to be created}';

    protected $description = 'Create a new translatable migration file';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = Str::snake(trim($this->argument('name')));
        $table = $this->option('table') ?? $this->option('create');

        // Determine if this is a create or update migration
        $create = $this->option('create') !== null;

        if (! $table) {
            // Try to extract table name from migration name
            if (preg_match('/^create_(\w+)_table$/', $name, $matches)) {
                $table = $matches[1];
                $create = true;
            } elseif (preg_match('/^add_\w+_to_(\w+)_table$/', $name, $matches)) {
                $table = $matches[1];
            } else {
                $this->error('Could not determine table name. Use --table or --create option.');

                return self::FAILURE;
            }
        }

        $stub = $create ? $this->getCreateStub() : $this->getUpdateStub();

        $stub = str_replace(
            ['{{ table }}', '{{table}}'],
            $table,
            $stub
        );

        $path = $this->getMigrationPath($name);

        if ($this->files->exists($path)) {
            $this->error('Migration already exists!');

            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $stub);

        $this->info("Created Migration: {$path}");

        return self::SUCCESS;
    }

    protected function getCreateStub(): string
    {
        $stubPath = $this->getStubPath('translatable.create.stub');

        if ($this->files->exists($stubPath)) {
            return $this->files->get($stubPath);
        }

        return $this->getDefaultCreateStub();
    }

    protected function getUpdateStub(): string
    {
        $stubPath = $this->getStubPath('translatable.update.stub');

        if ($this->files->exists($stubPath)) {
            return $this->files->get($stubPath);
        }

        return $this->getDefaultUpdateStub();
    }

    protected function getStubPath(string $stub): string
    {
        $customPath = base_path("stubs/{$stub}");

        if ($this->files->exists($customPath)) {
            return $customPath;
        }

        return dirname(__DIR__, 2)."/stubs/{$stub}";
    }

    protected function getMigrationPath(string $name): string
    {
        $timestamp = date('Y_m_d_His');

        return database_path("migrations/{$timestamp}_{$name}.php");
    }

    protected function getDefaultCreateStub(): string
    {
        return <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Initium\LaravelTranslatable\Components\Database\Blueprint;
use Initium\LaravelTranslatable\Facades\TranslatableSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        TranslatableSchema::create('{{ table }}', function (Blueprint $table) {
            $table->id();
            // Non-translatable columns
            $table->boolean('is_active')->default(true);

            // Translatable columns (will be moved to {{ table }}_translations table)
            $table->string('name')->translatable();
            $table->text('description')->nullable()->translatable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        TranslatableSchema::dropIfExists('{{ table }}');
    }
};
STUB;
    }

    protected function getDefaultUpdateStub(): string
    {
        return <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Initium\LaravelTranslatable\Components\Database\Blueprint;
use Initium\LaravelTranslatable\Facades\TranslatableSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        TranslatableSchema::table('{{ table }}', function (Blueprint $table) {
            // Add new translatable column
            // $table->string('subtitle')->nullable()->translatable();

            // Add new non-translatable column
            // $table->integer('sort_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        TranslatableSchema::table('{{ table }}', function (Blueprint $table) {
            // Drop translatable column
            // $table->dropTranslatable('subtitle');

            // Drop non-translatable column
            // $table->dropColumn('sort_order');
        });
    }
};
STUB;
    }
}
