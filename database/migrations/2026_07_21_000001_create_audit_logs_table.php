<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central audit trail for every model change (create / update / delete).
 *
 * Rows are written automatically by App\Support\Auditing\AuditLogger, which is
 * wired to Eloquent's global model events in App\Providers\AuditServiceProvider.
 * The table is append-only: nothing in the app updates or deletes these rows,
 * so it keeps a permanent record even after the subject or causer is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // What happened: created | updated | deleted | restored.
            $table->string('event', 20)->index();

            // The affected record (polymorphic). Kept as raw class + id rather
            // than a real FK so the log survives the subject being deleted.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Who did it. causer_id points at users.id (no FK, so the log
            // outlives the user); causer_name is a snapshot for display.
            $table->unsignedBigInteger('causer_id')->nullable()->index();
            $table->string('causer_name')->nullable();

            // Field-level diff. For "created" only new_values is set; for
            // "deleted" only old_values; for "updated" both, limited to the
            // columns that actually changed. Sensitive fields are redacted.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request context (null for console / queued work).
            $table->string('ip_address', 45)->nullable();
            $table->text('url')->nullable();

            // Only created_at — rows are immutable (see AuditLog::UPDATED_AT).
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
