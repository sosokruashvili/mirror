<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguish page/action access permissions ('page') from the existing
     * production-stage capabilities ('stage'). Existing rows are stage perms.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('type')->default('page')->after('description');
        });

        // Every permission that existed before this feature is a production stage.
        DB::table('permissions')->update(['type' => 'stage']);
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
