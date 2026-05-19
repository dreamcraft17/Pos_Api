<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $t) {
            $t->id();
            $t->string('code',64)->unique();
            $t->string('name');
            $t->enum('kind',['percent','amount']);
            $t->decimal('value',10,2);
            $t->boolean('enabled')->default(true);
            $t->integer('sort')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
