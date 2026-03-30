<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('voucher_code')->nullable()->after('payment_token');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('voucher_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['voucher_code', 'discount_amount']);
        });
    }
};
