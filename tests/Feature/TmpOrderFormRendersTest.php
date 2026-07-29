<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

/**
 * Temporary smoke test: renders the order create/edit forms against the dev database
 * to prove the new order_payments field view compiles and renders. GET only.
 */
class TmpOrderFormRendersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml forces sqlite/:memory:; talk to the real dev database instead.
        config(['database.default' => 'pgsql']);
    }

    public function test_create_and_edit_forms_render(): void
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->first() ?? User::first();

        $this->assertNotNull($admin, 'no user to act as');

        $create = $this->actingAs($admin, 'backpack')->get('/admin/order/create');
        $create->assertStatus(200);
        $create->assertSee('orderPaymentsField', false);
        $create->assertSee('addPaymentBtn', false);

        $order = Order::has('payments')->latest('id')->first();
        $this->assertNotNull($order, 'no order with payments to render');

        $edit = $this->actingAs($admin, 'backpack')->get('/admin/order/' . $order->getKey() . '/edit');
        $edit->assertStatus(200);
        $edit->assertSee('orderPaymentsField', false);

        // The field must hand the JS the payments already attached to this order.
        preg_match('/data-payments="([^"]*)"/', $edit->getContent(), $m);
        $this->assertNotEmpty($m, 'data-payments attribute missing');
        $payload = json_decode(html_entity_decode($m[1]), true);
        $this->assertCount($order->payments()->count(), $payload);

        fwrite(STDERR, PHP_EOL . 'order #' . $order->getKey() . ' payments payload: ' . json_encode($payload) . PHP_EOL);
    }
}
