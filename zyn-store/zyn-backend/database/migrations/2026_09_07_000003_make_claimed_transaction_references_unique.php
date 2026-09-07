<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('checkout_sessions')
            ->selectRaw('UPPER(TRIM(claimed_transaction_reference)) as reference, COUNT(*) as aggregate')
            ->whereNotNull('claimed_transaction_reference')
            ->groupByRaw('UPPER(TRIM(claimed_transaction_reference))')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('reference');

        foreach ($duplicates as $reference) {
            $ids = DB::table('checkout_sessions')
                ->whereRaw('UPPER(TRIM(claimed_transaction_reference)) = ?', [$reference])
                ->orderBy('payment_claimed_at')
                ->orderBy('id')
                ->pluck('id');

            DB::table('checkout_sessions')
                ->whereIn('id', $ids->skip(1))
                ->update(['claimed_transaction_reference' => null]);
        }

        DB::table('checkout_sessions')
            ->whereNotNull('claimed_transaction_reference')
            ->orderBy('id')
            ->eachById(fn ($session) => DB::table('checkout_sessions')
                ->where('id', $session->id)
                ->update(['claimed_transaction_reference' => strtoupper(trim($session->claimed_transaction_reference))]));

        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->unique('claimed_transaction_reference', 'checkout_sessions_claimed_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->dropUnique('checkout_sessions_claimed_reference_unique');
        });
    }
};
