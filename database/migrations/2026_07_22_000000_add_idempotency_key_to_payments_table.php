<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A per-form-render token that makes payment creation idempotent. When a
     * create form is submitted twice (double-click, browser/proxy retry, or a
     * re-POST after a session/CSRF timeout) both requests carry the same token,
     * so the unique index rejects the second insert at the database level —
     * atomically, without a read-then-write race. Nullable so historical rows
     * and any non-tokenised insert path remain valid (Postgres treats NULLs as
     * distinct, allowing many of them).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
