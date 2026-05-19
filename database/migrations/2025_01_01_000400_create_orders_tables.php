<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->timestamp('created_at')->useCurrent();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->integer('subtotal_cents');
            $t->integer('discount_cents')->default(0);
            $t->integer('tax_cents');
            $t->integer('total_cents');
            $t->json('payload');
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->string('sku',64)->nullable();
            $t->string('menu_code',64)->nullable();
            $t->integer('qty');
            $t->integer('price_cents');
            $t->string('name')->nullable();
            $t->enum('temp',['ice','hot'])->nullable();
            $t->enum('size',['S','M','L'])->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->string('method',64);
            $t->integer('amount_cents');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
