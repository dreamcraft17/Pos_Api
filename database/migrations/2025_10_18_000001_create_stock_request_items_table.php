<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_request_id');
            $table->string('name');
            $table->integer('request_qty')->default(0);
            $table->string('unit', 32)->default('pcs');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('stock_request_id')
                  ->references('id')->on('stock_requests')
                  ->onDelete('cascade');

            $table->index('stock_request_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_request_items');
    }
};
