<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\OrderListStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * The orders-list summary cards and the order/stats endpoint that fills them
 * are limited to the Administrator role (slug "admin").
 */
class OrderListStatsVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            $this->fail('The users, roles, and permissions tables do not exist yet.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_administrator_sees_the_order_list_totals_and_can_load_them(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $page = $this->actingAs($this->admin(), 'backpack')->get('/admin/order');

        $page->assertOk();
        $page->assertSee('id="order-stats-widgets"', false);
        $page->assertSee('order-stats-refresh.js', false);
        $page->assertSee('id="crudTable"', false);
        $page->assertSee('data-column-name="price_gel"', false);
        $page->assertSee('data-column-name="paid_amount"', false);

        $stats = Mockery::mock(OrderListStats::class);
        $stats->shouldReceive('remember')->once()->andReturn([
            'ordersCount' => 4,
            'totalPriceGel' => 12.5,
            'totalExpenses' => 1.25,
            'totalPaid' => 5,
            'totalUnpaid' => 7.5,
        ]);
        $this->app->instance(OrderListStats::class, $stats);

        $response = $this->actingAs($this->admin(), 'backpack')->getJson('/admin/order/stats');

        $response->assertOk();
        $response->assertJsonPath('ordersCount', 4);
        $response->assertJsonPath('totalPriceGel', 12.5);
        $response->assertJsonPath('totalUnpaid', 7.5);
    }

    public function test_other_roles_see_the_orders_list_without_the_totals(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $staff = User::factory()->create();
        $staff->givePermissionTo('order.list');

        $page = $this->actingAs($staff, 'backpack')->get('/admin/order');

        $page->assertOk();
        $page->assertDontSee('id="order-stats-widgets"', false);
        $page->assertDontSee('order-stats-refresh.js', false);
        $page->assertDontSee('/order/stats', false);
        $page->assertSee('id="crudTable"', false);
        $page->assertSee('data-column-name="price_gel"', false);
        $page->assertSee('data-column-name="paid_amount"', false);

        $stats = Mockery::mock(OrderListStats::class);
        $stats->shouldNotReceive('remember');
        $this->app->instance(OrderListStats::class, $stats);

        $response = $this->actingAs($staff, 'backpack')->getJson('/admin/order/stats');

        $response->assertForbidden();
    }

    public function test_developer_does_not_see_the_order_list_totals(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $developer = User::factory()->create();
        $role = Role::query()->where('slug', 'developer')->first();

        if (! $role) {
            $this->fail('The developer role is required.');
        }

        $developer->assignRole($role);

        $page = $this->actingAs($developer, 'backpack')->get('/admin/order');

        $page->assertOk();
        $page->assertDontSee('id="order-stats-widgets"', false);
        $page->assertSee('id="crudTable"', false);
        $page->assertSee('data-column-name="price_gel"', false);

        $stats = Mockery::mock(OrderListStats::class);
        $stats->shouldNotReceive('remember');
        $this->app->instance(OrderListStats::class, $stats);

        $this->actingAs($developer, 'backpack')->getJson('/admin/order/stats')->assertForbidden();
    }

    protected function admin(): User
    {
        $user = User::query()->whereHas('roles', function ($query) {
            $query->where('slug', 'admin');
        })->first();

        if (! $user) {
            $this->fail('An admin user is required.');
        }

        return $user;
    }

    /**
     * @return list<class-string>
     */
    protected function csrfMiddleware(): array
    {
        return [
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ];
    }
}
