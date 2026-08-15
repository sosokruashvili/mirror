<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'due_date')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->date('due_date')->nullable()->after('confirm_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'due_date')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('due_date');
            });
        }
    }
};
