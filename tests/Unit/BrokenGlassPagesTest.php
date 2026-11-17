<?php

namespace Tests\Unit;

use App\Models\BrokenGlass;
use App\Models\Machine;
use App\Models\Order;
use App\Models\Piece;
use App\Models\User;
use App\Support\Auditing\BrokenGlassLogger;
use App\Support\BrokenGlass\BreakDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The pages touched by the broken-glass details render (one request per test:
 * Backpack's CrudPanel singleton carries over between requests in one process).
 */
class BrokenGlassPagesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('machines')) {
            (require base_path('database/migrations/2026_11_12_120000_add_break_details_to_broken_glasses.php'))->up();
        }
    }

    public function test_broken_glass_list_page(): void
    {
        $html = $this->page($this->admin(), 'broken-glass')->assertOk()->getContent();

        $this->assertStringContainsString('id="brokenGlassTotals"', $html);
        $this->assertStringContainsString('let dataTablesExportAllRows = true;', $html);
        $this->assertStringContainsString('let dataTablesExportPdfGeorgian = true;', $html);
        $this->assertStringContainsString(__('broken_glass.usable_area'), $html);
    }

    public function test_broken_glass_search_rows_carry_the_confirm_button(): void
    {
        $break = $this->pendingBreak();

        $json = $this->actingAs($this->admin(), backpack_guard_name())
            ->post($this->url('broken-glass/search') . '?piece_id=' . $break->piece_id, ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->json();

        $row = collect($json['data'])->first(fn ($row) => str_contains(implode(' ', $row), '/' . $break->id . '/confirm'));
        $this->assertNotNull($row, 'No row with a confirm button for the new break.');
        $this->assertStringContainsString('/' . $break->id . '/edit', implode(' ', $row));
    }

    public function test_broken_glass_edit_page(): void
    {
        $break = $this->pendingBreak();

        $html = $this->page($this->admin(), 'broken-glass/' . $break->id . '/edit')->assertOk()->getContent();

        $this->assertStringContainsString('name="usable_area"', $html);
        $this->assertStringContainsString('name="machine_id"', $html);
        $this->assertStringContainsString('name="detected_stage_id"', $html);
    }

    public function test_confirmed_record_cannot_be_edited(): void
    {
        $break = $this->pendingBreak();
        $break->forceFill(['confirmed_at' => now()])->save();

        $this->page($this->admin(), 'broken-glass/' . $break->id . '/edit')->assertForbidden();
    }

    public function test_machine_list_page(): void
    {
        $this->page($this->admin(), 'machine')->assertOk()->assertSee(url(config('backpack.base.route_prefix') . '/machine/create'), false);
    }

    public function test_machine_create_page(): void
    {
        $this->page($this->admin(), 'machine/create')->assertOk()->assertSee('name="requires_comment"', false);
    }

    public function test_team_board_dialog_has_the_detail_fields(): void
    {
        $html = $this->page($this->admin(), 'team/orders')->assertOk()->getContent();

        $this->assertStringContainsString('id="pieceBrokenUsableArea"', $html);
        $this->assertStringContainsString('data-unit-area="', $html);
        $this->assertStringContainsString('assets/js/broken-glass-form.js?v=', $html);
        foreach (Machine::query()->active()->pluck('name') as $name) {
            $this->assertStringContainsString(e($name), $html);
        }
    }

    public function test_team_order_preview_dialog_has_the_detail_fields(): void
    {
        // No team account holds order.show today (the preview is unreachable for
        // them); grant it inside the test transaction to render the team view.
        $team = User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'team'))->orderBy('id')->firstOrFail();
        $team->givePermissionTo('order.show');
        $order = Order::query()->whereNotIn('status', ['draft', 'finished'])->whereNull('archived_at')->whereHas('pieces')->latest('id')->firstOrFail();

        $html = $this->page($team, 'order/' . $order->id . '/show')->assertOk()->getContent();

        $this->assertStringContainsString('id="brokenGlassUsableArea"', $html);
        $this->assertStringContainsString('data-unit-area="', $html);
    }

    public function test_warehouse_correction_list_still_renders(): void
    {
        $this->page($this->admin(), 'warehouse-correction')->assertOk();
    }

    public function test_confirm_route_returns_stock(): void
    {
        $break = $this->pendingBreak();

        $this->actingAs($this->admin(), backpack_guard_name())
            ->postJson($this->url('broken-glass/' . $break->id . '/confirm'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue($break->fresh()->isConfirmed());
        $this->assertGreaterThan(0, $break->warehouseCorrections()->count());
    }

    public function test_unconfirm_route_removes_the_stock(): void
    {
        $break = $this->pendingBreak();
        app(\App\Services\BrokenGlassConfirmation::class)->confirm($break, $this->admin());

        $this->actingAs($this->admin(), backpack_guard_name())
            ->postJson($this->url('broken-glass/' . $break->id . '/unconfirm'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertFalse($break->fresh()->isConfirmed());
        $this->assertSame(0, $break->warehouseCorrections()->count());
    }

    public function test_confirm_route_is_refused_without_the_permission(): void
    {
        $break = $this->pendingBreak();
        $viewer = User::query()->whereHas('roles', fn ($q) => $q->where('slug', '!=', 'admin')->where('slug', '!=', 'developer'))
            ->get()->first(fn (User $user) => $user->can('broken-glass.list') && ! $user->can('broken-glass.confirm'));

        if (! $viewer) {
            $viewer = User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'team'))->orderBy('id')->firstOrFail();
            $viewer->givePermissionTo('broken-glass.list');
        }

        $this->actingAs($viewer, backpack_guard_name())
            ->postJson($this->url('broken-glass/' . $break->id . '/confirm'))
            ->assertForbidden();

        $this->assertFalse($break->fresh()->isConfirmed());
    }

    public function test_edit_saves_corrected_details(): void
    {
        $break = $this->pendingBreak();
        $other = Machine::query()->where('requires_comment', true)->firstOrFail();

        $this->actingAs($this->admin(), backpack_guard_name())
            ->put($this->url('broken-glass/' . $break->id), [
                'id' => $break->id,
                'usable_area' => '0,2',
                'machine_id' => $other->id,
                'detected_stage_id' => BrokenGlass::detectionStages()->last()->id,
                'description' => 'შესწორდა',
                '_save_action' => 'save_and_back',
            ])
            ->assertRedirect();

        $break->refresh();
        $this->assertEquals(0.2, (float) $break->usable_area);
        $this->assertSame($other->id, $break->machine_id);
        $this->assertSame('შესწორდა', $break->description);
    }

    public function test_edit_refuses_more_usable_area_than_the_sheet(): void
    {
        $break = $this->pendingBreak();

        $this->actingAs($this->admin(), backpack_guard_name())
            ->from($this->url('broken-glass/' . $break->id . '/edit'))
            ->put($this->url('broken-glass/' . $break->id), [
                'id' => $break->id,
                'usable_area' => (string) ((float) $break->damaged_area + 1),
                'machine_id' => $break->machine_id,
                'detected_stage_id' => $break->detected_stage_id,
            ])
            ->assertSessionHasErrors('usable_area');

        $this->assertEquals(0.1, (float) $break->fresh()->usable_area);
    }

    public function test_other_lists_keep_the_stock_export(): void
    {
        $html = $this->page($this->admin(), 'payment')->assertOk()->getContent();

        $this->assertStringContainsString('let dataTablesExportAllRows = false;', $html);
        $this->assertStringContainsString('let dataTablesExportPdfGeorgian = false;', $html);
    }

    private function page(User $user, string $path)
    {
        return $this->actingAs($user, backpack_guard_name())->call('GET', $this->url($path));
    }

    private function url(string $path): string
    {
        return url(config('backpack.base.route_prefix') . '/' . $path);
    }

    private function admin(): User
    {
        return User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->orderBy('id')->firstOrFail();
    }

    private function pendingBreak(): BrokenGlass
    {
        $piece = Piece::query()->where('width', '>=', 60)->where('height', '>=', 60)
            ->whereHas('order', fn ($q) => $q->whereHas('products'))
            ->latest('id')->firstOrFail();

        return app(BrokenGlassLogger::class)->record($piece, BreakDetails::validate([
            'machine_id' => Machine::query()->where('requires_comment', false)->ordered()->value('id'),
            'detected_stage_id' => BrokenGlass::detectionStages()->first()->id,
            'usable_area' => '0.1',
        ], BreakDetails::sheetArea($piece->width, $piece->height)));
    }
}
