<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->integer('opening_cash_cents')->default(0);
            $table->integer('orders_count')->default(0);
            $table->integer('sold_items')->default(0);
            $table->bigInteger('gross_cents')->default(0);
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('net_cents')->default(0);
            $table->json('by_payment')->nullable(); // {"cash": 100000, "qris": 50000}
            $table->json('items')->nullable(); // [{"name": "Espresso", "qty": 5, "totalCents": 50000}]
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('outlet_name')->nullable();
            $table->string('cashier_name')->nullable();
            $table->timestamps();

            $table->index('created_by');
            $table->index('start_at');
            $table->index('end_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};