<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('refunds', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('order_id');
      $t->integer('total_cents')->default(0);
      $t->string('reason', 200)->nullable();
      $t->json('payload')->nullable(); // simpan payload request utk audit
      $t->timestamps();

      $t->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
      $t->index('order_id');
    });
  }

  public function down(): void {
    Schema::dropIfExists('refunds');
  }
};
