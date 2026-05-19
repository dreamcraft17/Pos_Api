<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'bundle_code')) {
            Schema::table('order_items', function (Blueprint $t) {
                $t->string('bundle_code', 64)->nullable()->after('menu_code');
                $t->index('bundle_code', 'order_items_bundle_code_idx');
            });
        }

        if (Schema::hasTable('bundle_menu_items')) {
            Schema::table('bundle_menu_items', function (Blueprint $t) {
                $t->index('bundle_code', 'bundle_menu_items_bundle_code_idx');
            });
        }

        if (Schema::hasTable('bundle_components')) {
            Schema::table('bundle_components', function (Blueprint $t) {
                $t->index('bundle_code', 'bundle_components_bundle_code_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'bundle_code')) {
            Schema::table('order_items', function (Blueprint $t) {
                $t->dropIndex('order_items_bundle_code_idx');
                $t->dropColumn('bundle_code');
            });
        }
    }
};
