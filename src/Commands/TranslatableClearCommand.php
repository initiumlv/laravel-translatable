<?php

namespace Initium\LaravelTranslatable\Commands;

use Illuminate\Console\Command;

class TranslatableClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translatable:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the translatable column cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cachePath = $this->getCachePath();

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->info('Translatable cache cleared successfully!');
        } else {
            $this->warn('Translatable cache file does not exist.');
        }

        return self::SUCCESS;
    }

    /**
     * Get the cache file path from config.
     */
    protected function getCachePath(): string
    {
        $configPath = config('translatable.cache_path', 'bootstrap/cache/translatable.php');

        // Handle relative paths
        if (! str_starts_with($configPath, '/') && ! preg_match('/^[a-zA-Z]:/', $configPath)) {
            return base_path($configPath);
        }

        return $configPath;
    }
}
