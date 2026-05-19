<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private array $renames = [
        'products' => ['price_rupiah' => 'price_rupiah'],
        'menus' => ['price_rupiah' => 'price_rupiah'],
        'menu_variants' => ['price_rupiah' => 'price_rupiah'],
        'orders' => [
            'subtotal_rupiah' => 'subtotal_rupiah',
            'discount_rupiah' => 'discount_rupiah',
            'service_rupiah' => 'service_rupiah',
            'tax_rupiah' => 'tax_rupiah',
            'total_rupiah' => 'total_rupiah',
            'refund_total_rupiah' => 'refund_total_rupiah',
        ],
        'order_items' => ['price_rupiah' => 'price_rupiah'],
        'payments' => ['amount_rupiah' => 'amount_rupiah'],
        'refunds' => ['total_rupiah' => 'total_rupiah'],
        'refund_items' => ['unit_price_rupiah' => 'unit_price_rupiah'],
        'open_bills' => [
            'subtotal_rupiah' => 'subtotal_rupiah',
            'discount_rupiah' => 'discount_rupiah',
            'tax_rupiah' => 'tax_rupiah',
            'total_rupiah' => 'total_rupiah',
        ],
        'shifts' => [
            'opening_cash_rupiah' => 'opening_cash_rupiah',
            'gross_rupiah' => 'gross_rupiah',
            'discount_rupiah' => 'discount_rupiah',
            'tax_rupiah' => 'tax_rupiah',
            'net_rupiah' => 'net_rupiah',
        ],
        'bundle_menus' => ['price_rupiah' => 'price_rupiah'],
        'bundle_menu_items' => ['price_rupiah' => 'price_rupiah'],
    ];

    public function up(): void
    {
        foreach ($this->renames as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $from => $to) {
                if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
                    Schema::table($table, fn (Blueprint $t) => $t->renameColumn($from, $to));
                }
            }
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $t) {
                if (! Schema::hasColumn('orders', 'service_rupiah')) {
                    $t->integer('service_rupiah')->default(0);
                }
                if (! Schema::hasColumn('orders', 'refund_total_rupiah')) {
                    $t->integer('refund_total_rupiah')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->renames as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $from => $to) {
                if (Schema::hasColumn($table, $to) && ! Schema::hasColumn($table, $from)) {
                    Schema::table($table, fn (Blueprint $t) => $t->renameColumn($to, $from));
                }
            }
        }
    }
};
