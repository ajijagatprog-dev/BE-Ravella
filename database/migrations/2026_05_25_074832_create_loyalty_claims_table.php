<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loyalty_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // ID unik dari claimable_reward di JSON tier config
            // Contoh: "gold_welcome_voucher", "platinum_birthday_points"
            $table->string('reward_id');

            $table->string('tier_name');   // "Gold", "Platinum", dll
            $table->enum('reward_type', ['voucher_code', 'bonus_points']);
            $table->string('reward_value')->nullable(); // kode voucher ATAU jumlah poin
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamps();

            // Satu customer hanya bisa claim satu reward_id sekali
            $table->unique(['user_id', 'reward_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_claims');
    }
};
