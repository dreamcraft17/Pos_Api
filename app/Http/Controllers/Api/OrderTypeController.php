<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\OrderType;

class OrderTypeController extends BaseApiController
{
    public function index(Request $r)
    {
        $u = $this->currentUser($r);
        $q = OrderType::query();
        if ($u) $q->where('created_by',$u->id);
        return $q->orderBy('sort')->orderBy('id')->get();
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'code'=>'required|string',
            'name'=>'required|string',
            'enabled'=>'nullable|boolean',
            'sort'=>'nullable|integer',
        ]);
        $u = $this->currentUser($r);
        OrderType::updateOrCreate(
            ['code'=>$data['code']],
            [
                'name'=>$data['name'],
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
            'enabled'=>'sometimes|boolean',
            'sort'=>'sometimes|integer',
        ]);
        OrderType::where('code',$code)->update($data);
        return ['ok'=>true];
    }

    public function destroy($code)
    {
        OrderType::where('code',$code)->delete();
        return ['ok'=>true];
    }
}
