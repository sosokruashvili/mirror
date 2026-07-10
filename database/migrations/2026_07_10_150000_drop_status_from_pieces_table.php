<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The piece production workflow is now tracked via the `stage` column.
     * The legacy `status` column is no longer used and is removed here.
     */
    public function up(): void
    {
        if (Schema::hasColumn('pieces', 'status')) {
            Schema::table('pieces', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('pieces', 'status')) {
            Schema::table('pieces', function (Blueprint $table) {
                $table->string('status')->default('draft')->after('height');
            });
        }
    }
};
