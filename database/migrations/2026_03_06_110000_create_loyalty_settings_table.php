<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default values
        DB::table('loyalty_settings')->insert([
            [
                'key' => 'earning_multiplier',
                'value' => '10',
                'description' => 'Points earned per Rp 10.000 spent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'redemption_value',
                'value' => '5',
                'description' => 'Rupiah discount per 1 point redeemed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'point_expiration',
                'value' => '12',
                'description' => 'Months before points expire',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
