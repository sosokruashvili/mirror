<?php

namespace Tests\Unit;

use App\Services\CuttingStats;
use Carbon\Carbon;
use Tests\TestCase;

class CuttingStatsTest extends TestCase
{
    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-10-' . $time);
    }

    private function tick(string $time): array
    {
        return ['action' => 'completed', 'at' => $this->at($time)];
    }

    private function untick(string $time): array
    {
        return ['action' => 'cleared', 'at' => $this->at($time)];
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    private function events(array $logs, array $pieceBreaks, array $orderBreaks, int $quantity): array
    {
        return array_map(
            fn (array $event) => [$event['at']->format('d H:i:s'), $event['sheets']],
            CuttingStats::cutEvents(
                $logs,
                array_map(fn (string $t) => $this->at($t), $pieceBreaks),
                array_map(fn (string $t) => $this->at($t), $orderBreaks),
                $quantity
            )
        );
    }

    public function test_a_tick_counts_every_sheet_on_its_day(): void
    {
        $this->assertSame([['20 10:00:00', 3]], $this->events([$this->tick('20 10:00:00')], [], [], 3));
    }

    public function test_a_tick_undone_by_hand_moves_to_the_next_tick(): void
    {
        $logs = [$this->tick('20 10:00:00'), $this->untick('20 10:05:00'), $this->tick('22 09:00:00')];

        $this->assertSame([['22 09:00:00', 2]], $this->events($logs, [], [], 2));
    }

    public function test_a_tick_undone_by_hand_and_never_redone_counts_nothing(): void
    {
        $logs = [$this->tick('20 10:00:00'), $this->untick('20 10:05:00')];

        $this->assertSame([], $this->events($logs, [], [], 2));
    }

    public function test_a_break_keeps_the_first_cut_and_adds_one_sheet_when_recut(): void
    {
        $logs = [$this->tick('20 10:00:00'), $this->untick('21 12:00:03'), $this->tick('22 08:00:00')];

        $this->assertSame(
            [['20 10:00:00', 4], ['22 08:00:00', 1]],
            $this->events($logs, ['21 12:00:00'], ['21 12:00:00'], 4)
        );
    }

    public function test_other_pieces_of_a_broken_group_are_not_counted_again(): void
    {
        // The group's break is recorded on another piece of the same order.
        $logs = [$this->tick('20 10:00:00'), $this->untick('21 12:00:02'), $this->tick('22 08:00:00')];

        $this->assertSame([['20 10:00:00', 1]], $this->events($logs, [], ['21 12:00:00'], 1));
    }

    public function test_a_break_waiting_to_be_recut_adds_nothing_yet(): void
    {
        $logs = [$this->tick('20 10:00:00'), $this->untick('21 12:00:01')];

        $this->assertSame([['20 10:00:00', 1]], $this->events($logs, ['21 12:00:00'], ['21 12:00:00'], 1));
    }

    public function test_two_breaks_before_one_recut_add_two_sheets(): void
    {
        $logs = [$this->tick('20 10:00:00'), $this->untick('21 12:00:00'), $this->tick('22 08:00:00')];

        $this->assertSame(
            [['20 10:00:00', 1], ['22 08:00:00', 1], ['22 08:00:00', 1]],
            $this->events($logs, ['21 12:00:00', '21 15:00:00'], ['21 12:00:00', '21 15:00:00'], 1)
        );
    }

    public function test_a_break_that_did_not_reset_a_cut_piece_counts_on_the_spot(): void
    {
        $this->assertSame(
            [['20 10:00:00', 2], ['21 12:00:00', 1]],
            $this->events([$this->tick('20 10:00:00')], ['21 12:00:00'], ['21 12:00:00'], 2)
        );
    }

    public function test_a_break_before_the_first_cut_adds_a_sheet_to_that_cut(): void
    {
        $this->assertSame(
            [['22 08:00:00', 2], ['22 08:00:00', 1]],
            $this->events([$this->tick('22 08:00:00')], ['20 12:00:00'], ['20 12:00:00'], 2)
        );
    }
}
