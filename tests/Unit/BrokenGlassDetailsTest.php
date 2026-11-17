<?php

namespace Tests\Unit;

use App\Models\BrokenGlass;
use App\Models\Machine;
use App\Models\Piece;
use App\Models\User;
use App\Models\WarehouseCorrection;
use App\Services\BrokenGlassConfirmation;
use App\Support\Auditing\BrokenGlassLogger;
use App\Support\BrokenGlass\BreakDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * Broken-glass details (2026-11): the team dialog's usable m² / machine / stage,
 * and the production lead's confirmation that returns usable m² to stock.
 *
 * Runs on the real Postgres database inside a rolled-back transaction. If the
 * migration has not been run yet it is applied inside that transaction too
 * (Postgres DDL is transactional), so nothing is left behind either way.
 */
class BrokenGlassDetailsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('machines')) {
            (require base_path('database/migrations/2026_11_12_120000_add_break_details_to_broken_glasses.php'))->up();
        }
    }

    public function test_every_field_but_the_comment_is_required(): void
    {
        $errors = $this->errors([], 0.5);

        $this->assertSame(['machine_id', 'detected_stage_id', 'usable_area'], array_keys($errors));
        $this->assertSame('ჩაწერე გამოსადეგი მ². თუ არაფერია გამოსადეგი, ჩაწერე 0.', $errors['usable_area'][0]);
    }

    public function test_usable_area_cannot_exceed_the_sheet(): void
    {
        $errors = $this->errors($this->input(['usable_area' => '0.61']), 0.6);

        $this->assertSame(['usable_area'], array_keys($errors));
        $this->assertSame('გამოსადეგი მ² ფურცლის ფართობზე (0.6 მ²) მეტი ვერ იქნება.', $errors['usable_area'][0]);
    }

    public function test_usable_area_accepts_zero_the_full_sheet_and_a_decimal_comma(): void
    {
        $this->assertSame(0.0, BreakDetails::validate($this->input(['usable_area' => '0']), 0.6)['usable_area']);
        $this->assertSame(0.6, BreakDetails::validate($this->input(['usable_area' => '0.6']), 0.6)['usable_area']);
        $this->assertSame(0.35, BreakDetails::validate($this->input(['usable_area' => '0,35']), 0.6)['usable_area']);
    }

    public function test_the_other_machine_requires_a_comment(): void
    {
        $other = Machine::query()->where('requires_comment', true)->firstOrFail();

        $errors = $this->errors($this->input(['machine_id' => $other->id, 'description' => '  ']), 0.6);
        $this->assertSame(['description'], array_keys($errors));

        $details = BreakDetails::validate($this->input(['machine_id' => $other->id, 'description' => ' ბზარი ']), 0.6);
        $this->assertSame('ბზარი', $details['description']);
    }

    public function test_a_switched_off_machine_is_refused_unless_already_on_the_record(): void
    {
        $machine = $this->machine();
        $machine->update(['is_active' => false]);

        $this->assertSame(['machine_id'], array_keys($this->errors($this->input(['machine_id' => $machine->id]), 0.6)));
        $this->assertSame($machine->id, BreakDetails::validate($this->input(['machine_id' => $machine->id]), 0.6, $machine->id)['machine_id']);
    }

    public function test_only_the_client_listed_stages_are_offered(): void
    {
        $this->assertSame(
            ['cutting', 'processing', 'tempering', 'cutting-drilling', 'curing', 'finished'],
            BrokenGlass::detectionStages()->pluck('name')->all()
        );

        $assembly = DB::table('stages')->whereNotIn('name', BrokenGlass::DETECTION_STAGE_NAMES)->value('id');
        $this->assertSame(['detected_stage_id'], array_keys($this->errors($this->input(['detected_stage_id' => $assembly]), 0.6)));
    }

    public function test_the_logger_stores_the_details_and_one_sheet_of_damage(): void
    {
        $piece = $this->piece();
        $details = BreakDetails::validate($this->input(['usable_area' => '0.1']), BreakDetails::sheetArea($piece->width, $piece->height));

        $break = app(BrokenGlassLogger::class)->record($piece, $details);

        $this->assertSame($details['machine_id'], $break->machine_id);
        $this->assertSame($details['detected_stage_id'], $break->detected_stage_id);
        $this->assertEquals(0.1, (float) $break->usable_area);
        $this->assertEquals(round($piece->width / 100 * $piece->height / 100, 4), (float) $break->damaged_area);
        $this->assertSame(BrokenGlass::STATUS_PENDING, $break->fresh()->currentStatus());
    }

    public function test_confirming_returns_usable_area_to_each_product_row_of_the_order(): void
    {
        $piece = $this->piece();
        $break = $this->break($piece, 0.25);
        $products = DB::table('order_product')->where('order_id', $piece->order_id)->pluck('product_id');
        $lead = $this->admin();

        app(BrokenGlassConfirmation::class)->confirm($break, $lead);

        $break->refresh();
        $this->assertTrue($break->isConfirmed());
        $this->assertSame($lead->id, (int) $break->confirmed_by);

        $returned = WarehouseCorrection::query()->where('broken_glass_id', $break->id)->pluck('area', 'product_id')
            ->map(fn ($area) => (float) $area)->all();
        $expected = $products->countBy()->map(fn ($rows) => round(0.25 * $rows, 3))->all();
        ksort($returned);
        ksort($expected);
        $this->assertEquals($expected, $returned);
        $this->assertTrue(WarehouseCorrection::query()->where('broken_glass_id', $break->id)
            ->get()->every(fn ($c) => $c->effective_date->isToday() && str_contains($c->reason, '#' . $break->id)));
    }

    public function test_a_product_listed_twice_gets_the_usable_area_twice(): void
    {
        $piece = $this->piece();
        $productId = DB::table('order_product')->where('order_id', $piece->order_id)->value('product_id');
        DB::table('order_product')->insert(['order_id' => $piece->order_id, 'product_id' => $productId]);

        $returned = app(BrokenGlassConfirmation::class)->returnedAreaByProduct($this->break($piece, 0.2));

        $rows = DB::table('order_product')->where('order_id', $piece->order_id)->where('product_id', $productId)->count();
        $this->assertEquals(round(0.2 * $rows, 3), $returned[$productId]);
    }

    public function test_nothing_is_returned_when_nothing_is_usable(): void
    {
        $break = $this->break($this->piece(), 0);

        app(BrokenGlassConfirmation::class)->confirm($break, $this->admin());

        $this->assertTrue($break->fresh()->isConfirmed());
        $this->assertSame(0, WarehouseCorrection::query()->where('broken_glass_id', $break->id)->count());
    }

    public function test_a_record_is_confirmed_only_once(): void
    {
        $break = $this->break($this->piece(), 0.1);
        app(BrokenGlassConfirmation::class)->confirm($break, $this->admin());

        $this->expectException(RuntimeException::class);
        app(BrokenGlassConfirmation::class)->confirm($break, $this->admin());
    }

    public function test_a_legacy_record_cannot_be_confirmed(): void
    {
        $legacy = app(BrokenGlassLogger::class)->record($this->piece(), ['description' => 'old']);
        $this->assertSame(BrokenGlass::STATUS_LEGACY, $legacy->currentStatus());

        $this->expectException(RuntimeException::class);
        app(BrokenGlassConfirmation::class)->confirm($legacy, $this->admin());
    }

    public function test_unconfirming_removes_the_returned_stock_and_reopens_the_record(): void
    {
        $break = $this->break($this->piece(), 0.3);
        app(BrokenGlassConfirmation::class)->confirm($break, $this->admin());
        $this->assertGreaterThan(0, WarehouseCorrection::query()->where('broken_glass_id', $break->id)->count());

        app(BrokenGlassConfirmation::class)->unconfirm($break);

        $break->refresh();
        $this->assertFalse($break->isConfirmed());
        $this->assertNull($break->confirmed_by_name);
        $this->assertTrue($break->isPending());
        $this->assertSame(0, WarehouseCorrection::query()->where('broken_glass_id', $break->id)->count());
    }

    public function test_status_scope_matches_status(): void
    {
        $pending = $this->break($this->piece(), 0.1);
        $legacy = app(BrokenGlassLogger::class)->record($this->piece(), []);

        $this->assertTrue(BrokenGlass::query()->withStatus('pending')->whereKey($pending->id)->exists());
        $this->assertFalse(BrokenGlass::query()->withStatus('legacy')->whereKey($pending->id)->exists());
        $this->assertTrue(BrokenGlass::query()->withStatus('legacy')->whereKey($legacy->id)->exists());
    }

    public function test_the_team_board_rejects_a_break_without_details_in_georgian(): void
    {
        $piece = $this->piece();
        $before = BrokenGlass::query()->count();

        $response = $this->actingAs($this->admin(), backpack_guard_name())
            ->postJson(route('team.pieces.broken-group'), ['piece_ids' => [$piece->id]]);

        $response->assertStatus(422)->assertJson(['success' => false, 'message' => 'აირჩიე დანადგარი.']);
        $this->assertSame($before, BrokenGlass::query()->count());
    }

    public function test_the_team_board_records_a_break_with_details(): void
    {
        $piece = $this->piece();

        $response = $this->actingAs($this->admin(), backpack_guard_name())
            ->postJson(route('team.pieces.broken-group'), $this->input([
                'piece_ids' => [$piece->id],
                'usable_area' => '0,05',
                'description' => 'კუთხე',
            ]));

        $response->assertOk()->assertJson(['success' => true]);
        $break = BrokenGlass::query()->where('piece_id', $piece->id)->latest('id')->first();
        $this->assertEquals(0.05, (float) $break->usable_area);
        $this->assertSame('კუთხე', $break->description);
        $this->assertNotNull($break->machine_id);
    }

    public function test_the_list_search_sends_totals_for_the_filtered_rows(): void
    {
        $piece = $this->piece();
        $break = $this->break($piece, 0.1);

        $response = $this->actingAs($this->admin(), backpack_guard_name())
            ->post(url(config('backpack.base.route_prefix') . '/broken-glass/search') . '?piece_id=' . $piece->id, [
                'draw' => 1, 'start' => 0, 'length' => 10,
            ], ['Accept' => 'application/json']);

        $response->assertOk();
        $totals = $response->json('totals');
        $expected = BrokenGlass::query()->where('piece_id', $piece->id);
        $this->assertSame($expected->count(), $totals['entries']);
        $this->assertEquals(round((float) $expected->sum('damaged_area'), 3), $totals['damaged']);
        $this->assertGreaterThanOrEqual((float) $break->usable_area, $totals['usable']);
    }

    // ----- helpers -----

    private function input(array $overrides = []): array
    {
        return array_merge([
            'machine_id' => $this->machine()->id,
            'detected_stage_id' => BrokenGlass::detectionStages()->first()->id,
            'usable_area' => '0.2',
            'description' => '',
        ], $overrides);
    }

    private function errors(array $input, ?float $area): array
    {
        try {
            BreakDetails::validate($input, $area);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Validation passed.');
    }

    private function machine(): Machine
    {
        return Machine::query()->where('requires_comment', false)->ordered()->firstOrFail();
    }

    private function break(Piece $piece, float $usable): BrokenGlass
    {
        return app(BrokenGlassLogger::class)->record($piece, BreakDetails::validate(
            $this->input(['usable_area' => (string) $usable]),
            BreakDetails::sheetArea($piece->width, $piece->height)
        ));
    }

    /**
     * A real piece of an open, product-carrying order, big enough for the test areas.
     */
    private function piece(): Piece
    {
        $piece = Piece::query()
            ->where('width', '>=', 60)
            ->where('height', '>=', 60)
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['draft', 'finished'])->whereNull('archived_at')->whereHas('products'))
            ->latest('id')
            ->first();

        $this->assertNotNull($piece, 'No open order with a 60×60+ piece to test on.');

        return $piece;
    }

    private function admin(): User
    {
        return User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->orderBy('id')->firstOrFail();
    }
}
