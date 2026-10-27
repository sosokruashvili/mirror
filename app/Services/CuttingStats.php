<?php

namespace App\Services;

use App\Models\PieceStageLog;
use App\Models\Stage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Square metres of glass and mirror cut per product over a date range.
 *
 * A piece counts as cut when the team ticks მოჭრა on it, so the dates come from
 * the cutting stage's change log (piece_stage_logs) rather than piece_stage:
 * the pivot only keeps the latest tick, and a break wipes it.
 *
 * - The piece's own sheets (width × height × quantity) count once, on the day
 *   of the tick that stuck. A tick undone by hand was a mistake, so the next
 *   tick replaces it. A tick undone by a break did happen, so it stays.
 * - Each broken_glasses row is one more sheet of that piece (the same rule the
 *   warehouse expense uses), counted when cutting is ticked again after it.
 *
 * Each glass/mirror line of the order gets the piece's area, so a package of
 * two 4მმ panes charges 4მმ twice. Butyl and film are not cut and are left out.
 * Offcut is the product's own % on top of the clean area. Draft orders are
 * excluded.
 */
class CuttingStats
{
    /**
     * A break clears the group's stages in the same request that records it,
     * so a clear this close to a break on the same order is the break's doing.
     */
    public const BREAK_CLEAR_WINDOW_SECONDS = 10;

    /** Product types whose area is cut from sheets. */
    public const PRODUCT_TYPES = ['glass', 'mirror'];

    /**
     * Cut area per product between two days (inclusive), only products with
     * something cut, ordered by title.
     *
     * @return \Illuminate\Support\Collection<int, object{id: int, title: string, offcut_percent: float, clean: float, offcut: float, total: float}>
     */
    public function forRange(Carbon $from, Carbon $to): Collection
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $cuttingId = Stage::ordered()->firstWhere('name', 'cutting')?->id;
        if ($cuttingId === null) {
            return collect();
        }

        // Pieces with something that may land in the range: a tick in it, or a
        // break in it that a tick never followed (see cutEvents()).
        $pieceIds = DB::table('piece_stage_logs')
            ->where('stage_id', $cuttingId)
            ->where('action', PieceStageLog::ACTION_COMPLETED)
            ->whereBetween('created_at', [$start, $end])
            ->pluck('piece_id')
            ->merge(DB::table('broken_glasses')->whereBetween('created_at', [$start, $end])->pluck('piece_id'))
            ->filter()
            ->unique()
            ->values();

        if ($pieceIds->isEmpty()) {
            return collect();
        }

        $pieces = DB::table('pieces as p')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('o.status', '!=', 'draft')
            ->whereIn('p.id', $pieceIds)
            ->get(['p.id', 'p.order_id', 'p.width', 'p.height', 'p.quantity']);

        if ($pieces->isEmpty()) {
            return collect();
        }

        $orderIds = $pieces->pluck('order_id')->unique()->values();

        $logs = DB::table('piece_stage_logs')
            ->where('stage_id', $cuttingId)
            ->whereIn('piece_id', $pieces->pluck('id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['piece_id', 'action', 'created_at'])
            ->groupBy('piece_id');

        $breaks = DB::table('broken_glasses')
            ->whereIn('order_id', $orderIds)
            ->orderBy('created_at')
            ->get(['piece_id', 'order_id', 'created_at']);
        $breaksByOrder = $breaks->groupBy('order_id');
        $breaksByPiece = $breaks->groupBy('piece_id');

        $lines = DB::table('order_product as op')
            ->join('products as pr', 'pr.id', '=', 'op.product_id')
            ->whereIn('op.order_id', $orderIds)
            ->whereIn('pr.product_type', self::PRODUCT_TYPES)
            ->get(['op.order_id', 'pr.id', 'pr.title', 'pr.offcut'])
            ->groupBy('order_id');

        $products = [];
        foreach ($pieces as $piece) {
            $orderLines = $lines->get($piece->order_id);
            if ($orderLines === null) {
                continue;
            }

            $events = self::cutEvents(
                ($logs->get($piece->id) ?? collect())
                    ->map(fn ($log) => ['action' => $log->action, 'at' => Carbon::parse($log->created_at)])
                    ->all(),
                ($breaksByPiece->get($piece->id) ?? collect())
                    ->map(fn ($break) => Carbon::parse($break->created_at))
                    ->all(),
                ($breaksByOrder->get($piece->order_id) ?? collect())
                    ->map(fn ($break) => Carbon::parse($break->created_at))
                    ->all(),
                (int) $piece->quantity
            );

            $sheets = 0;
            foreach ($events as $event) {
                if ($event['at']->between($start, $end)) {
                    $sheets += $event['sheets'];
                }
            }

            if ($sheets === 0) {
                continue;
            }

            $area = ((float) $piece->width / 100) * ((float) $piece->height / 100) * $sheets;

            foreach ($orderLines as $line) {
                $products[$line->id] ??= (object) [
                    'id' => (int) $line->id,
                    'title' => $line->title,
                    'offcut_percent' => (float) $line->offcut,
                    'clean' => 0.0,
                ];
                $products[$line->id]->clean += $area;
            }
        }

        return collect($products)
            ->each(function ($row) {
                $row->offcut = $row->clean * $row->offcut_percent / 100;
                $row->total = $row->clean + $row->offcut;
            })
            ->sortBy('title')
            ->values();
    }

    /**
     * When one piece's sheets were cut, from its cutting log and breaks.
     *
     * @param  array<int, array{action: string, at: \Carbon\Carbon}>  $logs  the piece's cutting log, oldest first
     * @param  array<int, \Carbon\Carbon>  $pieceBreaks  breaks recorded against this piece, oldest first
     * @param  array<int, \Carbon\Carbon>  $orderBreaks  breaks anywhere on the piece's order (a group break
     *                                                    is recorded on its first piece but clears them all)
     * @return array<int, array{at: \Carbon\Carbon, sheets: int}>
     */
    public static function cutEvents(array $logs, array $pieceBreaks, array $orderBreaks, int $quantity): array
    {
        $isBreakClear = function (Carbon $at) use ($orderBreaks): bool {
            foreach ($orderBreaks as $break) {
                if (abs($at->getTimestamp() - $break->getTimestamp()) <= self::BREAK_CLEAR_WINDOW_SECONDS) {
                    return true;
                }
            }

            return false;
        };

        $events = [];

        // The piece's own sheets: the latest tick, until a break freezes it.
        $cutAt = null;
        foreach ($logs as $log) {
            if ($log['action'] === PieceStageLog::ACTION_COMPLETED) {
                $cutAt = $log['at'];
            } elseif ($isBreakClear($log['at'])) {
                break;
            } else {
                $cutAt = null;
            }
        }

        if ($cutAt !== null && $quantity > 0) {
            $events[] = ['at' => $cutAt, 'sheets' => $quantity];
        }

        // One more sheet per break, cut at the first tick after it. A break that
        // did not reset a piece already cut was re-cut on the spot.
        foreach ($pieceBreaks as $break) {
            $resetHere = false;
            $cutBefore = false;
            $recutAt = null;

            foreach ($logs as $log) {
                $isCompleted = $log['action'] === PieceStageLog::ACTION_COMPLETED;

                if ($log['at']->lt($break)) {
                    $cutBefore = $isCompleted;
                } elseif (! $isCompleted && $log['at']->diffInSeconds($break, true) <= self::BREAK_CLEAR_WINDOW_SECONDS) {
                    $resetHere = true;
                } elseif ($isCompleted) {
                    $recutAt = $log['at'];
                    break;
                }
            }

            if ($recutAt === null && ! $resetHere && $cutBefore) {
                $recutAt = $break;
            }

            if ($recutAt !== null) {
                $events[] = ['at' => $recutAt, 'sheets' => 1];
            }
        }

        return $events;
    }
}
