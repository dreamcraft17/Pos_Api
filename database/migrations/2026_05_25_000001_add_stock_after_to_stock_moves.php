<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_moves')) {
            return;
        }

        Schema::table('stock_moves', function (Blueprint $t) {
            if (! Schema::hasColumn('stock_moves', 'stock_after')) {
                $t->integer('stock_after')->nullable()->after('delta');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_moves')) {
            return;
        }

        Schema::table('stock_moves', function (Blueprint $t) {
            if (Schema::hasColumn('stock_moves', 'stock_after')) {
                $t->dropColumn('stock_after');
            }
        });
    }
};
