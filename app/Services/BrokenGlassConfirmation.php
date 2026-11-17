<?php

namespace App\Services;

use App\Models\BrokenGlass;
use App\Models\User;
use App\Models\WarehouseCorrection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Confirms (and un-confirms) a broken-glass record on behalf of the production lead.
 *
 * Confirming returns the record's usable m² to stock as warehouse corrections
 * dated today, linked back through warehouse_corrections.broken_glass_id. The
 * return mirrors how the break was charged: WarehouseSnapshotService charges an
 * order's expense to EVERY product row on the order (a glass package listing the
 * same pane twice is charged twice), so the usable area goes back to each of
 * those products the same number of times. Nothing goes back before confirmation,
 * so a wrong number typed in the workshop never reaches the stock figures.
 *
 * Un-confirming deletes those corrections and makes the record editable again.
 * Both directions rebuild the stored stock snapshots, as editing a correction by
 * hand does (WarehouseCorrectionCrudController).
 */
class BrokenGlassConfirmation
{
    public function __construct(private WarehouseSnapshotService $snapshots)
    {
    }

    /**
     * @throws RuntimeException when the record cannot be confirmed
     */
    public function confirm(BrokenGlass $break, ?User $user): BrokenGlass
    {
        $confirmed = DB::transaction(function () use ($break, $user) {
            // Lock the row so two leads clicking at once cannot both return stock.
            $locked = BrokenGlass::query()->lockForUpdate()->findOrFail($break->getKey());

            if ($locked->isLegacy()) {
                throw new RuntimeException(__('broken_glass.errors.legacy'));
            }

            if ($locked->isConfirmed()) {
                throw new RuntimeException(__('broken_glass.errors.already_confirmed'));
            }

            $locked->forceFill([
                'confirmed_at' => now(),
                'confirmed_by' => $user?->getKey(),
                'confirmed_by_name' => $user?->name,
            ])->save();

            foreach ($this->returnedAreaByProduct($locked) as $productId => $area) {
                WarehouseCorrection::create([
                    'product_id' => $productId,
                    'area' => $area,
                    'effective_date' => today(),
                    'reason' => __('broken_glass.correction_reason', [
                        'id' => $locked->getKey(),
                        'order' => $locked->order_id,
                        'piece' => $locked->piece_id,
                    ], 'ka'),
                    'user_id' => $user?->getKey(),
                    'broken_glass_id' => $locked->getKey(),
                ]);
            }

            return $locked;
        });

        if ($confirmed->warehouseCorrections()->exists()) {
            $this->snapshots->rebuildStoredSnapshots(today()->startOfDay());
        }

        return $confirmed;
    }

    /**
     * @throws RuntimeException when the record is not confirmed
     */
    public function unconfirm(BrokenGlass $break): BrokenGlass
    {
        $rebuildFrom = null;

        $unconfirmed = DB::transaction(function () use ($break, &$rebuildFrom) {
            $locked = BrokenGlass::query()->lockForUpdate()->findOrFail($break->getKey());

            if (! $locked->isConfirmed()) {
                throw new RuntimeException(__('broken_glass.errors.not_confirmed'));
            }

            $corrections = WarehouseCorrection::query()->where('broken_glass_id', $locked->getKey())->get();
            $rebuildFrom = $corrections->min('effective_date');

            // One by one, so the audit log records each deletion.
            $corrections->each->delete();

            $locked->forceFill([
                'confirmed_at' => null,
                'confirmed_by' => null,
                'confirmed_by_name' => null,
            ])->save();

            return $locked;
        });

        if ($rebuildFrom) {
            $this->snapshots->rebuildStoredSnapshots(Carbon::parse($rebuildFrom)->startOfDay());
        }

        return $unconfirmed;
    }

    /**
     * m² to add back per product: usable_area once per product row on the order.
     *
     * @return array<int, float> product_id => area
     */
    public function returnedAreaByProduct(BrokenGlass $break): array
    {
        $usable = (float) $break->usable_area;

        if ($usable <= 0 || ! $break->order_id) {
            return [];
        }

        return DB::table('order_product')
            ->where('order_id', $break->order_id)
            ->pluck('product_id')
            ->countBy()
            ->map(fn (int $rows) => round($usable * $rows, 3))
            ->filter(fn (float $area) => $area > 0)
            ->all();
    }
}
