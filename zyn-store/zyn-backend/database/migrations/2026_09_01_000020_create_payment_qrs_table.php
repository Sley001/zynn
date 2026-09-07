<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_qrs', function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD')->index();
            $table->string('image_path');
            $table->boolean('is_active')->default(true)->index();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['amount_cents', 'currency']);
            $table->index(['created_at']);
        });

        DB::statement(
            'create unique index payment_qrs_active_amount_currency_unique on payment_qrs (amount_cents, currency) where is_active'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists payment_qrs_active_amount_currency_unique');
        Schema::dropIfExists('payment_qrs');
    }
};
