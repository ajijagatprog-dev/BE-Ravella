<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('variant_type')->default('Warna'); // e.g. "Warna", "Ukuran"
            $table->string('variant_value'); // e.g. "Hitam", "Pink", "XL"
            $table->integer('price')->nullable(); // null = use product price
            $table->integer('stock')->default(0);
            $table->string('sku_suffix')->nullable(); // e.g. "-BLK", "-PNK"
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
