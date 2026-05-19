<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $t) {
            $t->id();
            $t->string('code',64)->unique();
            $t->string('name');
            $t->integer('price_cents');
            $t->text('image_url')->nullable();
            $t->boolean('enabled')->default(true);
            $t->integer('sort')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->string('menu_code', 64);
            $t->string('product_sku', 64);
            $t->integer('qty');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreign('menu_code')->references('code')->on('menus')->cascadeOnDelete();
        });

        Schema::create('menu_variants', function (Blueprint $t) {
            $t->id();
            $t->string('menu_code', 64);
            $t->enum('kind', ['drink','food']);
            $t->enum('category', ['hot','ice'])->nullable();
            $t->enum('size', ['S','M','L'])->nullable();
            $t->integer('price_cents');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unique(['menu_code','category','size']);
            $t->foreign('menu_code')->references('code')->on('menus')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_variants');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
