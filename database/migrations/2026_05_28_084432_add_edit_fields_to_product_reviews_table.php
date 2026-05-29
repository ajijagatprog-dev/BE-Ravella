<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->integer('edit_rating')->nullable()->after('images');
            $table->text('edit_comment')->nullable()->after('edit_rating');
            $table->json('edit_images')->nullable()->after('edit_comment');
            $table->enum('edit_status', ['none', 'pending'])->default('none')->after('edit_images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['edit_rating', 'edit_comment', 'edit_images', 'edit_status']);
        });
    }
};
