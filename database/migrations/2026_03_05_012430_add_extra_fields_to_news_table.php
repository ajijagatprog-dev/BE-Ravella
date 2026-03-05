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
        Schema::table('news', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->after('content');
            $table->string('author')->default('Admin')->after('excerpt');
            $table->string('read_time')->nullable()->after('author');
            $table->integer('views')->default(0)->after('read_time');
            $table->boolean('is_featured')->default(false)->after('views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['excerpt', 'author', 'read_time', 'views', 'is_featured']);
        });
    }
};
