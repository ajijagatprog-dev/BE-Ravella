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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // nullable for guest checkouts if needed later, or strictly tracking
            $table->string('order_number')->unique();
            $table->decimal('total_amount', 15, 2);
            $table->string('status')->default('pending'); // pending, paid, processing, shipped, completed, cancelled
            $table->string('payment_method')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('payment_token')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
