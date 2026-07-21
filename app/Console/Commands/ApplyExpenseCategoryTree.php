<?php

namespace App\Console\Commands;

use App\Models\CashierExpense;
use App\Models\ExpenseCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Georgian expense-category tree (if missing) and remaps expenses
 * still attached to the legacy English roots (Food, Salary, …) onto the
 * corresponding Georgian leaves.
 *
 * Safe / idempotent — re-running is a no-op once mapping is done.
 *
 *   php artisan expenses:apply-category-tree
 *   php artisan expenses:apply-category-tree --dry-run
 *   php artisan expenses:apply-category-tree --delete-old
 */
class ApplyExpenseCategoryTree extends Command
{
    protected $signature = 'expenses:apply-category-tree
                            {--dry-run : Show what would change without writing}
                            {--delete-old : Delete unused legacy English root categories after remap}';

    protected $description = 'Seed Georgian expense categories and remap expenses from legacy English ones';

    /**
     * Nested tree: parent name => list of child names (1 level).
     * A root with an empty children list is itself a selectable leaf.
     */
    private function tree(): array
    {
        return [
            'ადმინისტრაციული' => [
                'ხელფასები',
                'იჯარა',
                'კომუნალურები',
                'საკანცელარიო',
                'საოფისე ტექნიკა',
                'საოფისე კომფორტი',
                'კავშირგაბმულობა',
                'კვება',
            ],
            'საწარმოო' => [
                'ნედლეული',
                'სახარჯი მასალები',
                'აქსესუარები',
                'საწარმოო ტექნიკისა და ინსტრუმენტების მოვლა',
            ],
            'კომერციული  / მარკეტინგული' => [
                'რეკლამა',
                'ტრანსპორტირება',
                'შეფუთვა',
                'კლიენტთა საჩუქრები',
                'მონტაჟები',
            ],
            'ფინანსური' => [
                'სესხი',
            ],
            'არასაოპერაციო' => [
                'ჯარიმები',
                'ქველმოქმედება',
            ],
            'ზოგადი' => [], // root-level leaf
        ];
    }

    /**
     * Legacy English root name => Georgian leaf name.
     */
    private function legacyMap(): array
    {
        return [
            'Food' => 'კვება',
            'Accessories' => 'აქსესუარები',
            'Consumable Materials' => 'სახარჯი მასალები',
            'Installation' => 'მონტაჟები',
            'Salary' => 'ხელფასები',
            'General' => 'ზოგადი',
        ];
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        DB::transaction(function () use ($dryRun) {
            $this->ensureTree($dryRun);
            $this->remapExpenses($dryRun);

            if ($this->option('delete-old')) {
                $this->deleteLegacyRoots($dryRun);
            }
        });

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function ensureTree(bool $dryRun): void
    {
        $this->info('Ensuring Georgian category tree…');

        foreach ($this->tree() as $parentName => $children) {
            $parent = ExpenseCategory::query()
                ->where('name', $parentName)
                ->whereNull('parent_id')
                ->first();

            if (! $parent) {
                $this->line("  + root: {$parentName}");
                if (! $dryRun) {
                    $parent = ExpenseCategory::create([
                        'name' => $parentName,
                        'parent_id' => null,
                    ]);
                }
            } else {
                $this->line("  = root exists: {$parentName}");
            }

            foreach ($children as $childName) {
                $exists = $parent
                    ? ExpenseCategory::query()
                        ->where('name', $childName)
                        ->where('parent_id', $parent->id)
                        ->exists()
                    : ExpenseCategory::query()
                        ->where('name', $childName)
                        ->whereNotNull('parent_id')
                        ->exists();

                // Also accept an existing child by name under any parent (idempotent).
                if (! $exists) {
                    $exists = ExpenseCategory::query()->where('name', $childName)->exists();
                }

                if ($exists) {
                    $this->line("    = child exists: {$childName}");
                    continue;
                }

                $this->line("    + child: {$childName}");
                if (! $dryRun && $parent) {
                    ExpenseCategory::create([
                        'name' => $childName,
                        'parent_id' => $parent->id,
                    ]);
                }
            }
        }

        if (! $dryRun) {
            ExpenseCategory::rebuildTree();
        }
    }

    private function remapExpenses(bool $dryRun): void
    {
        $this->info('Remapping expenses from legacy English categories…');

        foreach ($this->legacyMap() as $fromName => $toName) {
            $from = ExpenseCategory::query()->where('name', $fromName)->first();
            $to = ExpenseCategory::query()->where('name', $toName)->first();

            if (! $from) {
                $this->line("  skip {$fromName}: source category not found");
                continue;
            }

            if (! $to) {
                $this->error("  skip {$fromName}: target \"{$toName}\" not found — create the tree first");
                continue;
            }

            $count = CashierExpense::query()->where('category_id', $from->id)->count();
            if ($count === 0) {
                $this->line("  {$fromName} → {$toName}: 0 expenses");
                continue;
            }

            $this->line("  {$fromName} → {$toName}: {$count} expenses");
            if (! $dryRun) {
                CashierExpense::query()
                    ->where('category_id', $from->id)
                    ->update(['category_id' => $to->id]);
            }
        }
    }

    private function deleteLegacyRoots(bool $dryRun): void
    {
        $this->info('Deleting unused legacy English roots…');

        foreach (array_keys($this->legacyMap()) as $name) {
            $cat = ExpenseCategory::query()->where('name', $name)->whereNull('parent_id')->first();
            if (! $cat) {
                continue;
            }

            $expenseCount = CashierExpense::query()->where('category_id', $cat->id)->count();
            $childCount = $cat->children()->count();

            if ($expenseCount > 0 || $childCount > 0) {
                $this->warn("  keep {$name}: still has {$expenseCount} expenses / {$childCount} children");
                continue;
            }

            $this->line("  - delete {$name}");
            if (! $dryRun) {
                $cat->delete();
            }
        }

        if (! $dryRun) {
            ExpenseCategory::rebuildTree();
        }
    }
}
