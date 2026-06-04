<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Slot 4: Home Living category card banner
        DB::table('banners')->insertOrIgnore([
            'page' => 'product-dropdown',
            'slot' => 4,
            'image' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Slot 5: Home Kitchen category card banner
        DB::table('banners')->insertOrIgnore([
            'page' => 'product-dropdown',
            'slot' => 5,
            'image' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('banners')
            ->where('page', 'product-dropdown')
            ->whereIn('slot', [4, 5])
            ->delete();
    }
};
