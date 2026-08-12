<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Compares every English language file against its Georgian counterpart and
 * reports keys that are missing, orphaned, or still holding the English text.
 *
 * Run it after adding strings to lang/en so nothing silently falls back to
 * English in the Georgian panel:  php artisan lang:status
 */
class TranslationStatus extends Command
{
    protected $signature = 'lang:status
                            {--locale=ka : The locale to compare against English}
                            {--untranslated : Also list values identical to the English source}';

    protected $description = 'Report missing/untranslated keys for a locale';

    /**
     * Source-of-truth English directory => the directory holding the override.
     */
    protected function directoryPairs(string $locale): array
    {
        return [
            'app' => [base_path('lang/en'), base_path("lang/$locale")],
            'backpack' => [base_path('vendor/backpack/crud/src/resources/lang/en'), base_path("lang/vendor/backpack/$locale")],
            'theme' => [base_path('vendor/backpack/theme-tabler/resources/lang/en'), base_path("lang/vendor/backpack.theme-tabler/$locale")],
            'pro' => [base_path('vendor/backpack/pro/resources/lang/en'), base_path("lang/vendor/backpack/pro/$locale")],
        ];
    }

    public function handle(): int
    {
        $locale = $this->option('locale');
        $problems = 0;

        foreach ($this->directoryPairs($locale) as $group => [$sourceDir, $targetDir]) {
            $this->newLine();
            $this->line("<comment>$group</comment>  <fg=gray>$sourceDir -> $targetDir</>");

            foreach (glob("$sourceDir/*.php") as $sourceFile) {
                $file = basename($sourceFile);
                $targetFile = "$targetDir/$file";

                if (! file_exists($targetFile)) {
                    // Backpack ships lang files for add-ons this project does not
                    // install; those legitimately have no translation.
                    $this->line("  <fg=gray>skip</> $file <fg=gray>(not translated)</>");

                    continue;
                }

                $source = $this->flatten(require $sourceFile);
                $target = $this->flatten(require $targetFile);

                $missing = array_diff_key($source, $target);
                $orphaned = array_diff_key($target, $source);
                $problems += count($missing) + count($orphaned);

                $tag = ($missing || $orphaned) ? '<fg=red>fail</>' : '<info>ok</info>';
                $this->line("  $tag $file <fg=gray>(".count($source).' keys)</>');

                foreach (array_keys($missing) as $key) {
                    $this->line("      <fg=red>missing</> $key");
                }

                foreach (array_keys($orphaned) as $key) {
                    $this->line("      <fg=yellow>orphaned</> $key");
                }

                if ($this->option('untranslated')) {
                    foreach ($target as $key => $value) {
                        if (isset($source[$key]) && $source[$key] === $value) {
                            $this->line("      <fg=yellow>same as en</> $key = $value");
                        }
                    }
                }
            }
        }

        $this->newLine();

        if ($problems > 0) {
            $this->error("$problems key(s) need attention.");

            return self::FAILURE;
        }

        $this->info('All translation files are in sync.');

        return self::SUCCESS;
    }

    /**
     * Turn a nested lang array into dot-notation key => value pairs.
     */
    protected function flatten(array $lines, string $prefix = ''): array
    {
        $flat = [];

        foreach ($lines as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : "$prefix.$key";

            if (is_array($value)) {
                $flat += $this->flatten($value, $dotted);
            } else {
                $flat[$dotted] = $value;
            }
        }

        return $flat;
    }
}
