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
        Schema::create('product_reviews', function (Blueprint $create) {
            $create->id();
            $create->foreignId('product_id')->constrained()->onDelete('cascade');
            $create->foreignId('user_id')->constrained()->onDelete('cascade');
            $create->foreignId('order_id')->constrained()->onDelete('cascade');
            $create->integer('rating')->default(5);
            $create->text('comment')->nullable();
            $create->json('images')->nullable();
            $create->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $create->text('admin_reply')->nullable();
            $create->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
