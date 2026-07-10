<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy existing user-role assignments from the legacy `role_user` pivot
     * into Spatie's `model_has_roles` table. The legacy table is kept as-is
     * so nothing is destroyed and a rollback is trivial.
     */
    public function up(): void
    {
        if (! Schema::hasTable('role_user') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $assignments = DB::table('role_user')->get(['role_id', 'user_id']);

        foreach ($assignments as $assignment) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $assignment->role_id,
                'model_type' => \App\Models\User::class,
                'model_id' => $assignment->user_id,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')->where('model_type', \App\Models\User::class)->delete();
        }
    }
};
