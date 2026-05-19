<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // kalau belum ada kolom category, bisa tambahin di sini juga
            // $table->string('category', 100)->nullable();

            $table->string('unit', 50)->nullable()->after('category');
            $table->integer('min_qty')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unit', 'min_qty']);
        });
    }
};
