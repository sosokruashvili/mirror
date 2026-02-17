<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            if (Schema::hasColumn('pieces', 'broken')) {
                $table->dropColumn('broken');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->boolean('broken')->default(false)->after('status');
        });
    }
};
