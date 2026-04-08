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
        Schema::table('addresses', function (Blueprint $table) {
            // ID dari RajaOngkir — digunakan untuk kalkulasi ongkir real-time
            $table->unsignedInteger('province_id')->nullable()->after('province');
            $table->unsignedInteger('city_id')->nullable()->after('province_id');
            $table->unsignedInteger('subdistrict_id')->nullable()->after('city');
            $table->string('subdistrict_name')->nullable()->after('subdistrict_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['province_id', 'city_id', 'subdistrict_id', 'subdistrict_name']);
        });
    }
};
