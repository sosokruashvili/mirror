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
        if (!Schema::hasColumn('orders', 'comment')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('comment')->nullable()->after('atachment');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'comment')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('comment');
            });
        }
    }
};
