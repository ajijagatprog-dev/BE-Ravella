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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'customer', 'b2b'])->default('customer');
            $table->enum('admin_role', ['super_admin', 'administrator', 'admin_biasa'])->nullable();
            $table->string('phone_number')->nullable();
            $table->string('company_name')->nullable();
            $table->string('npwp')->nullable();
            $table->enum('b2b_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('address')->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
