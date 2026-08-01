<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_snapshots', function (Blueprint $table) {
            // Sum of manual corrections in effect on the snapshot date (signed).
            // Shown as its own column so the summary reconciles on screen:
            // remaining = warehouse_area - expenses + corrections.
            $table->decimal('corrections', 13, 3)->default(0)->after('expenses');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_snapshots', function (Blueprint $table) {
            $table->dropColumn('corrections');
        });
    }
};
