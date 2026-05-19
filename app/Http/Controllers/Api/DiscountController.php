<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Discount;

class DiscountController extends BaseApiController
{
    public function index(Request $r)
    {
        $u = $this->currentUser($r);
        $q = Discount::query();
        if ($u) $q->where('created_by',$u->id);
        return $q->orderBy('sort')->orderBy('id')->get();
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'code'=>'required|string',
            'name'=>'required|string',
            'kind'=>'required|in:percent,amount',
            'value'=>'required|numeric|min:0',
            'enabled'=>'nullable|boolean',
            'sort'=>'nullable|integer',
        ]);
        $u = $this->currentUser($r);
        Discount::updateOrCreate(
            ['code'=>$data['code']],
            [
                'name'=>$data['name'],
                'kind'=>$data['kind'],
                'value'=>$data['value'],
                'enabled'=>$data['enabled'] ?? true,
                'sort'=>$data['sort'] ?? 0,
                'created_by'=>$u?->id,
            ]
        );
        return ['ok'=>true];
    }

    public function update(Request $r, $code)
    {
        $data = $r->validate([
            'name'=>'sometimes|string',
            'kind'=>'sometimes|in:percent,amount',
            'value'=>'sometimes|numeric|min:0',
            'enabled'=>'sometimes|boolean',
            'sort'=>'sometimes|integer',
        ]);
        Discount::where('code',$code)->update($data);
        return ['ok'=>true];
    }

    public function destroy($code)
    {
        Discount::where('code',$code)->delete();
        return ['ok'=>true];
    }
}
