<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_payment_alerts', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('telegram_update_id')->nullable()->index();
            $table->bigInteger('telegram_chat_id')->index();
            $table->bigInteger('telegram_message_id');
            $table->bigInteger('telegram_from_id')->nullable()->index();
            $table->bigInteger('telegram_sender_chat_id')->nullable()->index();
            $table->timestamp('telegram_message_sent_at')->nullable();
            $table->timestamp('telegram_update_received_at')->nullable();
            $table->text('raw_message')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('currency', 3)->nullable()->index();
            $table->string('payer_name')->nullable();
            $table->string('masked_account_digits', 20)->nullable();
            $table->timestamp('displayed_paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('merchant_name')->nullable();
            $table->string('transaction_id')->nullable()->unique();
            $table->string('apv')->nullable();
            $table->string('status', 30)->default('received')->index();
            $table->foreignId('matched_checkout_session_id')->nullable()->constrained('checkout_sessions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
            $table->foreignId('duplicate_of_alert_id')->nullable()->constrained('telegram_payment_alerts')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['telegram_chat_id', 'telegram_message_id']);
            $table->index(['amount_cents', 'currency', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_payment_alerts');
    }
};
