<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->string('claimed_transaction_reference', 100)->nullable();
        });

        Schema::create('checkout_payment_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_session_id')->unique()->constrained()->restrictOnDelete();
            $table->string('transaction_reference', 100)->unique();
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_payment_verifications');
        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->dropColumn('claimed_transaction_reference');
        });
    }
};
