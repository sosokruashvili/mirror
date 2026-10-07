<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A per-form-render token that makes order creation idempotent, the same
     * mechanism payments use. When the create form is submitted twice (a second
     * click on a Save dropdown action, Ctrl+S, a browser/proxy retry) both
     * requests carry the same token, so the unique index rejects the second
     * insert at the database level — atomically, even when the two requests
     * overlap. Nullable so historical rows and forms rendered before this
     * column existed remain valid (Postgres treats NULLs as distinct).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
