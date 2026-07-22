<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'confirm_date')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('confirm_date')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'confirm_date')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('confirm_date');
            });
        }
    }
};
