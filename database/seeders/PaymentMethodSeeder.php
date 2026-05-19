<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['code'=>'cash','name'=>'Cash','enabled'=>true,'sort'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'card','name'=>'Card','enabled'=>true,'sort'=>2,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'qris','name'=>'QRIS','enabled'=>true,'sort'=>3,'created_at'=>$now,'updated_at'=>$now],
        ];
        foreach ($rows as $r){
            DB::table('payment_methods')->updateOrInsert(['code'=>$r['code']], $r);
        }
    }
}
