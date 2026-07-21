<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ExpenseCategory extends Model
{
    use CrudTrait;

    protected $fillable = [
        'name',
        'parent_id',
        'lft',
        'rgt',
        'depth',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'lft' => 'integer',
        'rgt' => 'integer',
        'depth' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category) {
            if ($category->lft && $category->rgt) {
                return;
            }

            // Temporary slots; rebuilt after save so parent_id nesting is correct.
            $max = (int) static::max('rgt');
            $category->lft = $max + 1;
            $category->rgt = $max + 2;
            $category->depth = $category->depth ?: 1;
        });

        static::saved(function () {
            static::rebuildTree();
        });

        static::deleted(function () {
            static::rebuildTree();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('lft');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(CashierExpense::class, 'category_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(
            Supplier::class,
            'supplier_expense_category'
        );
    }

    public function isLeaf(): bool
    {
        return ! $this->children()->exists();
    }

    /**
     * Categories ordered by nested-set left bound (tree order).
     *
     * @return Collection<int, static>
     */
    public static function orderedTree(): Collection
    {
        return static::query()->orderBy('lft')->orderBy('id')->get();
    }

    /**
     * Indented options for picking a parent when creating/editing a category.
     *
     * @return array<int, string>
     */
    public static function optionsForSelect(?int $excludeId = null): array
    {
        $options = [];

        foreach (static::orderedTree() as $category) {
            if ($excludeId && (int) $category->id === $excludeId) {
                continue;
            }

            // Skip descendants of the excluded node so a category cannot become
            // its own ancestor via the parent picker.
            if ($excludeId) {
                $excluded = static::find($excludeId);
                if ($excluded && $category->lft > $excluded->lft && $category->rgt < $excluded->rgt) {
                    continue;
                }
            }

            $options[$category->id] = $category->indentedName();
        }

        return $options;
    }

    /**
     * Leaf categories only — used on the expense create/edit form.
     *
     * @return array<int, string>
     */
    public static function leafOptions(): array
    {
        $options = [];

        foreach (static::orderedTree() as $category) {
            if ($category->rgt === $category->lft + 1) {
                $options[$category->id] = $category->indentedName();
            }
        }

        return $options;
    }

    /**
     * Leaf options grouped under parent titles for <optgroup> selects.
     * Root-level leaves are returned under the empty-string group (no header).
     *
     * @return array<string, array<int, string>>
     */
    public static function groupedLeafOptions(): array
    {
        $tree = static::orderedTree()->keyBy('id');
        $groups = [];

        foreach ($tree as $category) {
            $isLeaf = $category->rgt === $category->lft + 1;

            if (! $isLeaf) {
                // Keep parent order so optgroups appear in tree order.
                $groups[$category->name] = $groups[$category->name] ?? [];
                continue;
            }

            if ($category->parent_id && isset($tree[$category->parent_id])) {
                $parentName = $tree[$category->parent_id]->name;
                $groups[$parentName][$category->id] = $category->name;
            } else {
                $groups[''][$category->id] = $category->name;
            }
        }

        // Drop parents that ended up with no selectable leaves.
        return array_filter($groups, fn (array $items) => $items !== []);
    }

    /**
     * All categories for the expense list filter (descendant-aware filtering).
     *
     * @return array<int, string>
     */
    public static function filterOptions(): array
    {
        $options = [];

        foreach (static::orderedTree() as $category) {
            $options[$category->id] = $category->indentedName();
        }

        return $options;
    }

    public function indentedName(): string
    {
        $depth = max(0, ((int) $this->depth) - 1);

        return str_repeat('— ', $depth) . $this->name;
    }

    /**
     * Label used in Backpack multi-selects so nesting is visible.
     */
    public function getSelectLabelAttribute(): string
    {
        return $this->indentedName();
    }

    /**
     * Recalculate lft/rgt/depth from parent_id relationships.
     * Safe to call after any create/update/delete that changes nesting.
     */
    public static function rebuildTree(): void
    {
        $items = static::query()->orderBy('lft')->orderBy('id')->get();
        $byParent = $items->groupBy(fn (self $item) => $item->parent_id ?? 'root');

        $lft = 1;

        $walk = function ($parentKey, int $depth) use (&$walk, &$lft, $byParent): void {
            foreach ($byParent->get($parentKey, collect()) as $node) {
                $nodeLft = $lft++;
                $walk($node->id, $depth + 1);
                $nodeRgt = $lft++;

                DB::table('expense_categories')->where('id', $node->id)->update([
                    'lft' => $nodeLft,
                    'rgt' => $nodeRgt,
                    'depth' => $depth,
                ]);
            }
        };

        // Avoid infinite recursion from the saved hook while rebuilding.
        static::withoutEvents(function () use ($walk) {
            $walk('root', 1);
        });
    }
}
