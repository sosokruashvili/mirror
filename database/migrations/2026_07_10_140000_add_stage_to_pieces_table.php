<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production stage of a piece (მოჭრა → დასრულება). This is separate from
     * `status`, which tracks the piece's workflow state (new/cut/ready/…).
     */
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->string('stage')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
