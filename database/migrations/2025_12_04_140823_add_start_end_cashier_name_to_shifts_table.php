<?php

// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     /**
//      * Run the migrations.
//      */
//     public function up(): void
//     {
//         Schema::table('shifts', function (Blueprint $table) {
//             //
//         });
//     }

//     /**
//      * Reverse the migrations.
//      */
//     public function down(): void
//     {
//         Schema::table('shifts', function (Blueprint $table) {
//             //
//         });
//     }
// };


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->string('start_cashier_name')->nullable()->after('outlet_name');
            $table->string('end_cashier_name')->nullable()->after('start_cashier_name');

            // optional: kalau mau hapus yg lama
            // $table->dropColumn('cashier_name');
        });
    }

    public function down()
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['start_cashier_name', 'end_cashier_name']);

            // optional: kalau di up tadi di-drop
            // $table->string('cashier_name')->nullable();
        });
    }
};
