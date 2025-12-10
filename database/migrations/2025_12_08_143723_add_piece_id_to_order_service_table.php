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
        Schema::table('order_service', function (Blueprint $table) {
            $table->unsignedBigInteger('piece_id')->nullable()->after('service_id');
            $table->foreign('piece_id')->references('id')->on('pieces')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_service', function (Blueprint $table) {
            $table->dropForeign(['piece_id']);
            $table->dropColumn('piece_id');
        });
    }
};
