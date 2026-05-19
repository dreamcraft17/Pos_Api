<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $rows = PaymentMethod::query()
            ->where('enabled', 1)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id','code','name','enabled','sort']);

        return response()->json($rows);
    }

    // Tambah method baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:payment_methods,code',
            'name' => 'required|string|max:100',
            'sort' => 'nullable|integer',
        ]);

        $method = PaymentMethod::create([
            'code'       => $validated['code'],
            'name'       => $validated['name'],
            'enabled'    => true,
            'sort'       => $validated['sort'] ?? 0,
            'created_by' => optional($request->user())->id, 
        ]);

        return response()->json($method, 201);
    }

   
    // public function toggle(Request $request, $id)
    // {
    //     $validated = $request->validate([
    //         'enabled' => 'required|boolean',
    //     ]);

    //     $method = PaymentMethod::findOrFail($id);
    //     $method->enabled = $validated['enabled'];
    //     $method->save();

    //     return response()->json([
    //         'message' => 'Status updated',
    //         'data'    => $method,
    //     ]);
    // }

      public function update(Request $request, string $code)
    {
        $method = PaymentMethod::where('code', $code)->firstOrFail();

        $validated = $request->validate([
            'code'    => [
                'sometimes','string','max:50','alpha_dash',
                Rule::unique('payment_methods','code')->ignore($method->id),
            ],
            'name'    => 'sometimes|string|max:100',
            'sort'    => 'sometimes|integer',
            'enabled' => 'sometimes|boolean',
        ]);

        // patch partial fields
        $method->fill($validated);
        $method->save();

        return response()->json([
            'message' => 'Payment method updated',
            'data'    => $method,
        ]);
    }


   public function destroy($code)
{
    $method = PaymentMethod::where('code', $code)->firstOrFail();
    $method->delete();

    return response()->json([
        'message' => 'Payment method deleted successfully'
    ]);
}

}
