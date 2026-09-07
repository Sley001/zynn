<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->string('customer_name', 120);
            $table->string('phone', 30)->index();
            $table->string('province', 100);
            $table->string('district', 100);
            $table->text('address_note')->nullable();
            $table->string('payment_method', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('total_cents');
            $table->timestamp('age_confirmed_at');
            $table->string('source', 30)->default('website');
            $table->string('telegram_username')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
