<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuVariant;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $t) {
            $t->unsignedBigInteger('menu_id')->after('id')->nullable();
            $t->index('menu_id');
        });
        Schema::table('menu_variants', function (Blueprint $t) {
            $t->unsignedBigInteger('menu_id')->after('id')->nullable();
            $t->index('menu_id');
        });

        // Backfill: hubungkan anak ke parent berdasar code lama
        foreach (Menu::cursor() as $menu) {
            MenuItem::where('menu_code', $menu->code)->update(['menu_id' => $menu->id]);
            MenuVariant::where('menu_code', $menu->code)->update(['menu_id' => $menu->id]);
        }

        // (Opsional) kamu bisa jadikan non-nullable kalau semua sudah terisi
        // Schema::table('menu_items', fn(Blueprint $t) => $t->unsignedBigInteger('menu_id')->nullable(false)->change());
        // Schema::table('menu_variants', fn(Blueprint $t) => $t->unsignedBigInteger('menu_id')->nullable(false)->change());
    }

    public function down(): void
    {
        Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn('menu_id'));
        Schema::table('menu_variants', fn (Blueprint $t) => $t->dropColumn('menu_id'));
    }
};
