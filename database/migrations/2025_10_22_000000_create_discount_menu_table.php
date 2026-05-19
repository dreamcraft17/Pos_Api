<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('discount_menu', function (Blueprint $table) {
            $table->id();
            $table->string('discount_code'); // rel ke discounts.code
            $table->string('menu_code');     // rel ke menus.code
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['discount_code','menu_code']);

            // Index untuk query cepat per user & code
            $table->index(['created_by', 'discount_code']);
            $table->index(['created_by', 'menu_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_menu');
    }
};
