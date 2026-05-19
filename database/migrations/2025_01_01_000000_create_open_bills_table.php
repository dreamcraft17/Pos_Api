<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('client_id', 100)->nullable()->index(); // id lokal dari app
            $table->string('status', 20)->default('open');         // open/cancelled/done (optional)
            $table->integer('subtotal_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->json('payload'); // full JSON open bill dari app
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_bills');
    }
};
