<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('refund_items', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('refund_id');
      $t->unsignedBigInteger('order_id');
      $t->unsignedBigInteger('order_item_id')->nullable();
      $t->string('sku')->nullable();
      $t->string('menu_code')->nullable();
      $t->integer('qty')->default(0);
      $t->integer('unit_price_rupiah')->default(0);
      $t->timestamps();

      $t->foreign('refund_id')->references('id')->on('refunds')->onDelete('cascade');
      $t->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
      $t->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
      $t->index(['order_id', 'order_item_id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('refund_items');
  }
};
