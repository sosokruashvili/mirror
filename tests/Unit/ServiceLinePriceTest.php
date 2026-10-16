<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\OrderCrudController;
use App\Models\Service;
use ReflectionMethod;
use Tests\TestCase;

class ServiceLinePriceTest extends TestCase
{
    private function price(Service $service, array $data, int $pieceQty, int $productCount = 1): float
    {
        $controller = new OrderCrudController();
        $method = new ReflectionMethod($controller, 'calculatedServiceLinePrice');
        $method->setAccessible(true);

        return $method->invoke($controller, $service, $data, $pieceQty, $productCount);
    }

    private function service(string $slug, string $unit, float $priceGel): Service
    {
        $service = new Service();
        $service->slug = $slug;
        $service->unit = $unit;
        $service->price_gel = $priceGel;

        return $service;
    }

    public function test_piece_unit_quantity_is_the_total_and_ignores_piece_count(): void
    {
        $template = $this->service('silicone', 'ცალი', 40);

        $this->assertEquals(40.0, $this->price($template, ['quantity' => 1], 2));
        $this->assertEquals(80.0, $this->price($template, ['quantity' => 2], 5));
    }

    public function test_hole_count_is_not_multiplied_by_piece_count(): void
    {
        $hole = $this->service('hole', 'ცალი', 5);

        $this->assertEquals(20.0, $this->price($hole, ['quantity' => 4], 2));
    }

    public function test_flat_piece_unit_service_uses_the_typed_quantity(): void
    {
        $antifog = $this->service('antifog', 'ცალი', 30);

        $this->assertEquals(30.0, $this->price($antifog, [], 2));
        $this->assertEquals(90.0, $this->price($antifog, ['quantity' => 3], 2));
    }

    public function test_piece_unit_perimeter_uses_the_total_already_on_the_line(): void
    {
        $edge = $this->service('ek', 'ცალი', 1.5);

        $this->assertEquals(5.19, $this->price($edge, ['perimeter' => 3.46], 2));
    }

    public function test_metre_and_square_metre_services_still_scale_with_piece_count(): void
    {
        $bevel = $this->service('fk', 'მ', 1.5);
        $matt = $this->service('matt', 'კვ.მ', 10);

        // Per-piece price is rounded before the piece count is applied, so
        // 3.46m × 1.50 becomes 5.20 rather than 5.19.
        $this->assertEquals(5.2, $this->price($bevel, ['perimeter' => 3.46], 2));
        $this->assertEquals(20.0, $this->price($matt, ['area' => 2], 2));
    }

    public function test_glass_product_count_still_multiplies_a_piece_unit_service(): void
    {
        $template = $this->service('silicone', 'ცალი', 40);

        $this->assertEquals(80.0, $this->price($template, ['quantity' => 1], 2, 2));
    }
}
