<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('strength_mg');
            $table->unsignedInteger('price_cents');
            $table->string('color', 20)->default('#1c4a3e');
            $table->string('accent', 20)->default('#c9a227');
            $table->string('origin')->nullable();
            $table->string('release_duration')->nullable();
            $table->json('notes')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
