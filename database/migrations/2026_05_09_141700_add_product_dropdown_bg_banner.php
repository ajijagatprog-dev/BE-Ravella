<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add background banner slot for the product dropdown mega-menu.
     */
    public function up(): void
    {
        DB::table('banners')->insertOrIgnore([
            'page'       => 'product-dropdown',
            'slot'       => 3,
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
        DB::table('banners')
            ->where('page', 'product-dropdown')
            ->where('slot', 3)
            ->delete();
    }
};
