<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->string('photo_url')->nullable()->after('note');
            $table->enum('item_condition', ['ok', 'defect'])->nullable()->after('photo_url');
            $table->text('defect_note')->nullable()->after('item_condition');
        });
    }

    public function down(): void {
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->dropColumn(['photo_url', 'item_condition', 'defect_note']);
        });
    }
};
