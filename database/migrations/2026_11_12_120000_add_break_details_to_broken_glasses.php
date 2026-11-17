<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Detailed broken-glass records (client request, 2026-11).
 *
 * The team's "გატყდა" dialog now also asks how many m² of the broken sheet are
 * still usable, which machine broke it, and at which stage the damage was found.
 * A production lead then confirms each record on the Broken Glasses page; only a
 * confirmed record puts its usable m² back into stock, as a warehouse correction
 * linked back to the break (warehouse_corrections.broken_glass_id).
 *
 * Every column is nullable so the existing rows stay valid. Those rows predate the
 * dialog fields: they have no machine (which is how the app tells them apart) and
 * are never confirmed. damaged_area IS backfilled for them, because it is only the
 * piece's sheet area and the list totals should include old breaks too.
 */
return new class extends Migration
{
    private const MACHINES = [
        'კრომკა',
        'ფასკა',
        'საჭრელი მაგიდა',
        'ოვალი',
        'სარეცხი',
        'მატოვი',
        'ხვრეტა',
        'წრთობა',
        'სხვა',
    ];

    private const PERMISSIONS = [
        'broken-glass.confirm' => 'Broken Glasses — Confirm',
        'broken-glass.unconfirm' => 'Broken Glasses — Unconfirm',
        'machine.list' => 'Machines — View list',
        'machine.create' => 'Machines — Create',
        'machine.update' => 'Machines — Edit',
    ];

    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            // "სხვა" (other) says nothing by itself, so it makes the comment mandatory.
            $table->boolean('requires_comment')->default(false);
            // Machines are switched off rather than deleted: old breaks still point at them.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('machines')->insert(array_map(fn ($name, $index) => [
            'name' => $name,
            'position' => $index + 1,
            'requires_comment' => $name === 'სხვა',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::MACHINES, array_keys(self::MACHINES)));

        Schema::table('broken_glasses', function (Blueprint $table) {
            $table->foreignId('machine_id')->nullable()->constrained('machines')->restrictOnDelete();
            $table->foreignId('detected_stage_id')->nullable()->constrained('stages')->nullOnDelete();

            // Area of the one broken sheet (m²), copied from the piece at break time
            // so later edits to the piece's size do not rewrite history.
            $table->decimal('damaged_area', 10, 4)->nullable();
            // Part of that sheet still fit for use (m²); returned to stock on confirmation.
            $table->decimal('usable_area', 10, 4)->nullable();

            $table->timestamp('confirmed_at')->nullable()->index();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->string('confirmed_by_name')->nullable();
        });

        DB::update('
            UPDATE broken_glasses bg
            SET damaged_area = ROUND((p.width / 100.0) * (p.height / 100.0), 4)
            FROM pieces p
            WHERE p.id = bg.piece_id
              AND p.width IS NOT NULL
              AND p.height IS NOT NULL
        ');

        Schema::table('warehouse_corrections', function (Blueprint $table) {
            // Set when the correction is a confirmed break's usable m² going back to
            // stock. Unconfirming the break deletes these rows.
            $table->foreignId('broken_glass_id')->nullable()->constrained('broken_glasses')->nullOnDelete();
        });

        foreach (self::PERMISSIONS as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description, 'type' => 'page']
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Admins and developers pass every check anyway; this only shows the boxes
        // ticked in their role checklist.
        foreach (Role::query()->whereIn('slug', ['admin', 'developer'])->get() as $role) {
            $role->givePermissionTo(array_keys(self::PERMISSIONS));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('type', 'page')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('warehouse_corrections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broken_glass_id');
        });

        Schema::table('broken_glasses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_id');
            $table->dropConstrainedForeignId('detected_stage_id');
            $table->dropIndex(['confirmed_at']);
            $table->dropColumn([
                'damaged_area',
                'usable_area',
                'confirmed_at',
                'confirmed_by',
                'confirmed_by_name',
            ]);
        });

        Schema::dropIfExists('machines');
    }
};
