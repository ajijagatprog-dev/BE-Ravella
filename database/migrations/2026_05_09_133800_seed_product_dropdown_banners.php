<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Seed banner records for the product dropdown mega-menu.
     */
    public function up(): void
    {
        // Slot 1: Featured Card (main big banner on the right side)
        DB::table('banners')->insertOrIgnore([
            'page'       => 'product-dropdown',
            'slot'       => 1,
            'image'      => null,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Slot 2: Mini Promo Card (small banner below the featured card)
        DB::table('banners')->insertOrIgnore([
            'page'       => 'product-dropdown',
            'slot'       => 2,
            'image'      => null,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('banners')->where('page', 'product-dropdown')->delete();
    }
};
