<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * amount_gel used to be the full purchase price, with credit as the unpaid
 * slice (paid = amount - credit). It is now the amount paid, independent of
 * credit, so a fully-on-credit purchase is amount 0 / credit 1000.
 *
 * Existing rows are rewritten in place: paid = old amount - credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'UPDATE cashier_expenses
             SET amount_gel = GREATEST(COALESCE(amount_gel, 0) - COALESCE(credit, 0), 0)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'UPDATE cashier_expenses
             SET amount_gel = COALESCE(amount_gel, 0) + COALESCE(credit, 0)'
        );
    }
};
