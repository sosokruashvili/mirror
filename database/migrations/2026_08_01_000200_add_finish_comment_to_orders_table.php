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
        if (!Schema::hasColumn('orders', 'finish_comment')) {
            Schema::table('orders', function (Blueprint $table) {
                // The team's note left when the order is handed out (გატანილია).
                // Kept apart from `comment`, which is the manager's note FOR the team.
                $table->text('finish_comment')->nullable()->after('comment');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'finish_comment')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('finish_comment');
            });
        }
    }
};
