<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bundle_menus')) {
            Schema::create('bundle_menus', function (Blueprint $t) {
                $t->id();
                $t->string('bundle_code', 64)->unique();
                $t->string('name');
                $t->unsignedInteger('price_rupiah')->default(0);
                $t->boolean('enabled')->default(true);
                $t->integer('sort')->default(0);
                $t->string('type', 32)->default('bundle');
                $t->string('created_by', 64)->nullable();
                $t->unsignedBigInteger('created_by_id')->nullable();
                $t->timestamps();
                $t->index('created_by_id');
            });
        }

        if (! Schema::hasTable('bundle_menu_items')) {
            Schema::create('bundle_menu_items', function (Blueprint $t) {
                $t->id();
                $t->string('bundle_code', 64);
                $t->string('menu_code', 64);
                $t->string('menu_name')->nullable();
                $t->string('menu_type', 32)->nullable();
                $t->unsignedInteger('qty')->default(1);
                $t->unsignedInteger('price_rupiah')->default(0);
                $t->string('created_by', 64)->nullable();
                $t->index(['bundle_code', 'created_by']);
            });
        }

        if (! Schema::hasTable('bundle_components')) {
            Schema::create('bundle_components', function (Blueprint $t) {
                $t->id();
                $t->string('bundle_code', 64);
                $t->string('product_sku', 64);
                $t->unsignedInteger('qty')->default(1);
                $t->string('created_by', 64)->nullable();
                $t->index(['bundle_code', 'created_by']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_components');
        Schema::dropIfExists('bundle_menu_items');
        Schema::dropIfExists('bundle_menus');
    }
};
