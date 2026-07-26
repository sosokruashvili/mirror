<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On Postgres a foreign key constraint does not create an index (MySQL creates
 * one implicitly), so every ->constrained() column in this schema was left
 * unindexed. These are the FK columns the app actually filters and joins on,
 * plus a few columns driving the dashboard and nightly balance jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broken_glasses', function (Blueprint $table) {
            // Also backs the ON DELETE CASCADE from pieces.
            $table->index('piece_id');
        });

        Schema::table('order_service', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('piece_id');
            $table->index('service_id');
        });

        Schema::table('pieces', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('order_id');
            // Leading column also serves plain client_id lookups; the pair is
            // what ClientBalanceService sums per client every night.
            $table->index(['client_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('created_at');
        });

        Schema::table('piece_stage', function (Blueprint $table) {
            // piece_id is already covered as the leading column of
            // piece_stage_piece_id_stage_id_unique; stage_id is not.
            $table->index('stage_id');
            $table->index(['user_id', 'completed_at']);
        });

        Schema::table('client_balances', function (Blueprint $table) {
            $table->index('balance_date');
        });

        // Sorting the audit log is ORDER BY created_at DESC, id DESC, which the
        // single-column created_at index cannot satisfy on its own.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'audit_logs_created_at_id_index');
        });

        // Redundant unique indexes on id, duplicating each table's primary key.
        // Created by hand outside Laravel (note the non-standard _x suffix).
        foreach (['client_id_x', 'product_id_x', 'service_id_x'] as $index) {
            Schema::getConnection()->statement("DROP INDEX IF EXISTS {$index}");
        }
    }

    public function down(): void
    {
        Schema::table('broken_glasses', fn (Blueprint $table) => $table->dropIndex(['piece_id']));

        Schema::table('order_service', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['piece_id']);
            $table->dropIndex(['service_id']);
        });

        Schema::table('pieces', fn (Blueprint $table) => $table->dropIndex(['order_id']));

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['client_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('piece_stage', function (Blueprint $table) {
            $table->dropIndex(['stage_id']);
            $table->dropIndex(['user_id', 'completed_at']);
        });

        Schema::table('client_balances', fn (Blueprint $table) => $table->dropIndex(['balance_date']));

        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropIndex('audit_logs_created_at_id_index'));

        $connection = Schema::getConnection();
        $connection->statement('CREATE UNIQUE INDEX IF NOT EXISTS client_id_x ON clients USING btree (id)');
        $connection->statement('CREATE UNIQUE INDEX IF NOT EXISTS product_id_x ON products USING btree (id)');
        $connection->statement('CREATE UNIQUE INDEX IF NOT EXISTS service_id_x ON services USING btree (id)');
    }
};
