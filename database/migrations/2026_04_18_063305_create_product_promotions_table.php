<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_promotions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('sku')->index(); // Link to Product or ProductVariant SKU
            $blueprint->string('name')->nullable(); // Promo Name (e.g. "Ramadan Sale")
            $blueprint->enum('type', ['discount', 'flash_sale'])->default('discount');
            $blueprint->enum('discount_type', ['percent', 'fixed'])->default('fixed');
            $blueprint->decimal('discount_value', 15, 2);
            $blueprint->timestamp('starts_at')->nullable(); // For Flash Sale
            $blueprint->timestamp('ends_at')->nullable();   // For Flash Sale
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
    }
};
