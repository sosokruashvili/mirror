<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserColumnVisibilityTest extends TestCase
{
    public function test_column_visibility_is_stored_per_table(): void
    {
        $user = new User;
        $user->setColumnVisibilityFor('admin/order', [
            'client_id' => false,
            'status' => true,
        ]);

        $this->assertSame(
            ['client_id' => false, 'status' => true],
            $user->columnVisibilityFor('admin/order')
        );
        $this->assertSame([], $user->columnVisibilityFor('admin/product'));
    }

    public function test_column_visibility_values_are_booleans(): void
    {
        $user = new User;
        $user->crud_column_visibility = [
            'admin/order' => ['paid' => 1, 'price_gel' => 0],
        ];

        $this->assertSame(
            ['paid' => true, 'price_gel' => false],
            $user->columnVisibilityFor('admin/order')
        );
    }
}
