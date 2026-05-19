<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->index(['created_by', 'created_at'], 'orders_created_by_created_at_idx');
        });

        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $t) {
                $t->index('order_id', 'refunds_order_id_idx');
            });
        }

        Schema::table('products', function (Blueprint $t) {
            $t->index(['is_deleted', 'updated_at'], 'products_deleted_updated_idx');
        });

        if (Schema::hasTable('stock_moves')) {
            Schema::table('stock_moves', function (Blueprint $t) {
                $t->index(['sku', 'created_at'], 'stock_moves_sku_created_at_idx');
            });
        }

        Schema::table('menu_items', function (Blueprint $t) {
            $t->index('menu_code', 'menu_items_menu_code_idx');
        });

        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', function (Blueprint $t) {
                $t->index('end_at', 'shifts_end_at_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropIndex('orders_created_by_created_at_idx'));
        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', fn (Blueprint $t) => $t->dropIndex('refunds_order_id_idx'));
        }
        Schema::table('products', fn (Blueprint $t) => $t->dropIndex('products_deleted_updated_idx'));
        if (Schema::hasTable('stock_moves')) {
            Schema::table('stock_moves', fn (Blueprint $t) => $t->dropIndex('stock_moves_sku_created_at_idx'));
        }
        Schema::table('menu_items', fn (Blueprint $t) => $t->dropIndex('menu_items_menu_code_idx'));
        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', fn (Blueprint $t) => $t->dropIndex('shifts_end_at_idx'));
        }
    }
};
