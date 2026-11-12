<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * services.product_types limits which services the order form offers per order
 * product type; an empty list means every type. Administrators set it on the
 * Services → product types matrix (ServiceProductTypesController).
 */
class ServiceProductTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('services', 'product_types')) {
            $this->fail('Run the 2026_11_11_120000_add_product_types_to_services_table migration first.');
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

    public function test_types_are_stored_known_unique_and_in_matrix_order(): void
    {
        $service = new Service();
        $service->product_types = ['mirror', 'x', 'glass', 'mirror', ['nested']];

        $this->assertSame(['glass', 'mirror'], $service->productTypes());
        $this->assertSame('["glass","mirror"]', $service->getAttributes()['product_types']);

        $service->product_types = [];
        $this->assertNull($service->getAttributes()['product_types']);

        $service->product_types = '';
        $this->assertNull($service->getAttributes()['product_types']);
        $this->assertSame([], $service->productTypes());
    }

    public function test_an_unlimited_service_is_offered_everywhere_and_a_limited_one_only_on_its_types(): void
    {
        $open = new Service();
        $this->assertTrue($open->isOfferedFor('lamix'));

        $limited = new Service();
        $limited->product_types = ['mirror'];
        $this->assertTrue($limited->isOfferedFor('mirror'));
        $this->assertFalse($limited->isOfferedFor('glass'));
        // No type chosen yet on a new order: nothing is hidden.
        $this->assertTrue($limited->isOfferedFor(null));
        $this->assertTrue($limited->isOfferedFor(''));
    }

    public function test_admin_saves_the_matrix_and_an_unticked_row_is_cleared(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        [$a, $b, $c] = Service::query()->orderBy('id')->take(3)->get()->all();
        $c->forceFill(['product_types' => ['lamix']])->save();

        $response = $this->actingAs($this->admin(), 'backpack')->post('/admin/service-product-types', [
            'types' => [
                $a->id => ['mirror', 'glass', 'bogus'],
                $b->id => ['service'],
                // $c is not posted: every box in its row was unticked.
            ],
        ]);

        $response->assertRedirect(route('service-product-types.index'));
        $this->assertSame(['glass', 'mirror'], $a->fresh()->productTypes());
        $this->assertSame(['service'], $b->fresh()->productTypes());
        $this->assertSame([], $c->fresh()->productTypes());
        $this->assertNull(DB::table('services')->where('id', $c->id)->value('product_types'));
    }

    public function test_admin_sees_the_matrix_with_current_ticks(): void
    {
        $service = Service::query()->orderBy('id')->first();
        $service->forceFill(['product_types' => ['glass_pkg']])->save();

        $page = $this->actingAs($this->admin(), 'backpack')->get('/admin/service-product-types');

        $page->assertOk();
        $page->assertSee('id="spt-table"', false);
        $page->assertSee(e($service->title), false);
        $html = $page->getContent();
        $box = fn (string $type) => '/name="types\[' . $service->id . '\]\[\]" value="' . $type . '"\s+data-type="' . $type . '"\s+checked/';
        $this->assertMatchesRegularExpression($box('glass_pkg'), $html);
        $this->assertDoesNotMatchRegularExpression($box('mirror'), $html);
    }

    public function test_non_admin_cannot_open_or_save_the_matrix(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $staff = User::factory()->create();
        $staff->givePermissionTo(['service.list', 'service.update']);
        $service = Service::query()->orderBy('id')->first();
        $before = $service->productTypes();

        $this->actingAs($staff, 'backpack')->get('/admin/service-product-types')->assertForbidden();

        $this->app->forgetScopedInstances();
        Facade::clearResolvedInstances();

        $this->actingAs($staff, 'backpack')
            ->post('/admin/service-product-types', ['types' => [$service->id => ['mirror']]])
            ->assertForbidden();
        $this->assertSame($before, $service->fresh()->productTypes());
    }

    protected function admin(): User
    {
        $user = User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->first();

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
