<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\OrderCrudController;
use App\Models\Service;
use ReflectionMethod;
use Tests\TestCase;

/**
 * განათება brings ქაფი, ასაწყობი მასალა and საკიდი onto the order form as separate
 * lines (public/assets/js/order-pieces-services.js). These cover the stored list
 * and that the server prices those lines the way the form shows them.
 */
class ServiceCompanionsTest extends TestCase
{
    public function test_companion_ids_are_unique_integers_without_the_service_itself(): void
    {
        $service = new Service();
        $service->id = 60;
        $service->companion_service_ids = ['53', 56, '60', 53, '', 'x', 14];

        $this->assertSame([53, 56, 14], $service->companionServiceIds());
        $this->assertSame('[53,56,14]', $service->getAttributes()['companion_service_ids']);
    }

    public function test_an_empty_companion_list_is_stored_as_null(): void
    {
        $service = new Service();
        $service->companion_service_ids = [];
        $this->assertNull($service->getAttributes()['companion_service_ids']);

        $service->companion_service_ids = null;
        $this->assertNull($service->getAttributes()['companion_service_ids']);
        $this->assertSame([], $service->companionServiceIds());
    }

    public function test_a_service_without_the_column_loaded_has_no_companions(): void
    {
        $this->assertSame([], (new Service())->companionServiceIds());
    }

    public function test_light_set_on_three_mirrors_of_one_size_is_priced_like_the_form(): void
    {
        // 60×70 ×3: perimeter on every line is the total, 7.80 m.
        $this->assertEquals(70.2, $this->price($this->service('ek', 'მ', 9), ['perimeter' => 7.80], 3));    // განათება
        $this->assertEquals(39.0, $this->price($this->service('ek', 'ცალი', 5), ['perimeter' => 7.80], 3)); // ქაფი
        $this->assertEquals(60.0, $this->price($this->service('hole', 'ცალი', 20), ['quantity' => 3], 3));  // ასაწყობი მასალა
        $this->assertEquals(45.0, $this->price($this->service('hanger', 'ცალი', 15), ['quantity' => 3], 3)); // საკიდი
    }

    private function price(Service $service, array $data, int $pieceQty): float
    {
        $controller = new OrderCrudController();
        $method = new ReflectionMethod($controller, 'calculatedServiceLinePrice');
        $method->setAccessible(true);

        return $method->invoke($controller, $service, $data, $pieceQty, 1);
    }

    private function service(string $slug, string $unit, float $priceGel): Service
    {
        $service = new Service();
        $service->slug = $slug;
        $service->unit = $unit;
        $service->price_gel = $priceGel;

        return $service;
    }
}
