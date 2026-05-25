<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('payments'))->pluck('name');

        if (! $indexes->contains('payments_order_id_idx')) {
            Schema::table('payments', function (Blueprint $t) {
                $t->index('order_id', 'payments_order_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $t) {
            $t->dropIndex('payments_order_id_idx');
        });
    }
};
