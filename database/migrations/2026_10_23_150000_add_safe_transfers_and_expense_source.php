<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safe_transfers', function (Blueprint $table) {
            $table->id();
            $table->integer('author')->nullable()->index();
            $table->string('direction');
            $table->string('status')->default('draft')->index();
            $table->decimal('amount_gel', 12, 2);
            $table->text('description')->nullable();
            $table->dateTime('transferred_at')->index();
            $table->timestamps();
        });

        Schema::table('cashier_expenses', function (Blueprint $table) {
            // Null means the historical default: a cash expense left the cashier.
            $table->string('source')->nullable()->after('type')->index();
        });

        $this->grantSafePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('safe_transfers');

        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });

        Permission::query()
            ->where('type', 'page')
            ->where('name', 'like', 'safe.%')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create the safe page permissions and give them to the roles that already
     * work with the cashier, without resetting the rest of each role's matrix.
     */
    private function grantSafePermissions(): void
    {
        $desired = [
            'safe.list' => 'Safe — View list',
            'safe.create' => 'Safe — Create',
            'safe.update' => 'Safe — Edit',
            'safe.delete' => 'Safe — Delete',
            'safe.show' => 'Safe — View details',
        ];

        foreach ($desired as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description, 'type' => 'page']
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = Permission::query()->whereIn('name', array_keys($desired))->get();
        $view = $all->whereIn('name', ['safe.list', 'safe.show']);
        $operate = $all->whereIn('name', ['safe.list', 'safe.create', 'safe.update', 'safe.show']);

        foreach (Role::all() as $role) {
            if (in_array($role->slug, ['admin', 'developer', 'manager'], true)) {
                $role->givePermissionTo($all);
                continue;
            }

            if ($role->hasPermissionTo('cashier-expense.create')) {
                $role->givePermissionTo($operate);
                continue;
            }

            if ($role->hasPermissionTo('cashier.list')) {
                $role->givePermissionTo($view);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
