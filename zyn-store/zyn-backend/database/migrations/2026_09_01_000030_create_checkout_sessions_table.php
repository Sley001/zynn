<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->json('customer_snapshot');
            $table->json('cart_snapshot');
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('delivery_fee_cents');
            $table->unsignedInteger('total_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('payment_method', 30);
            $table->string('status', 30)->default('awaiting_payment');
            $table->foreignId('payment_qr_id')->nullable()->constrained('payment_qrs')->nullOnDelete();
            $table->string('qr_image_path');
            $table->timestamp('payment_claimed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('resulting_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['total_cents', 'currency']);
            $table->index(['expires_at']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
