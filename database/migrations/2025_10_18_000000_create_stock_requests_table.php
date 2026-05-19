<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requested_by');
            $table->text('notes')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('channel', 32)->default('whatsapp');
            $table->enum('status', ['pending','sent','failed'])->default('pending');

            $table->string('wa_target')->nullable();
            $table->integer('wa_response_code')->nullable();
            $table->longText('wa_response_body')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_requests');
    }
};
