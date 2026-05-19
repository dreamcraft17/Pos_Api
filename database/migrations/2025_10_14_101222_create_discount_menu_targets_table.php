<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('discount_menu_targets', function (Blueprint $t) {
            $t->id();
            $t->string('discount_code');   // FK ke discounts.code
            $t->string('menu_code');       // target menu (kode unik menu)
            $t->timestamps();

            $t->unique(['discount_code', 'menu_code']);
            $t->index('discount_code');
            $t->index('menu_code');
        });
    }

    public function down(): void {
        Schema::dropIfExists('discount_menu_targets');
    }
};
