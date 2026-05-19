<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Unik per user agar "code sama" di user berbeda tetap ok
        Schema::table('menus', function (Blueprint $t) {
            // Pastikan belum ada index dengan nama ini sebelumnya
            $t->unique(['code','created_by'], 'menus_code_created_by_unique');
        });

        // Index bantu untuk query cepat by parent
        Schema::table('menu_items', function (Blueprint $t) {
            $t->index(['menu_code','created_by'], 'menu_items_code_created_by_idx');
        });
        Schema::table('menu_variants', function (Blueprint $t) {
            $t->index(['menu_code','created_by'], 'menu_variants_code_created_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $t) {
            $t->dropUnique('menus_code_created_by_unique');
        });
        Schema::table('menu_items', function (Blueprint $t) {
            $t->dropIndex('menu_items_code_created_by_idx');
        });
        Schema::table('menu_variants', function (Blueprint $t) {
            $t->dropIndex('menu_variants_code_created_by_idx');
        });
    }
};
