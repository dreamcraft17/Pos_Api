<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;



class StockRequestController extends Controller
{

    


    public function send(Request $request)
    {
        $branch     = $request->input('branch');
        $requestedBy = $request->input('requested_by', 'Unknown User');
        $items       = $request->input('items', []);
        $notes       = trim((string) $request->input('notes', ''));

        if (empty($items) || !is_array($items)) {
            return response()->json(['message' => 'No items provided'], 400);
        }

        // Normalisasi items
        $normalized = [];
        foreach ($items as $it) {
            $normalized[] = [
                'name'        => (string)($it['name'] ?? '-'),
                'request_qty' => (int)($it['request_qty'] ?? 0),
                'unit'        => isset($it['unit']) && $it['unit'] !== '' ? (string)$it['unit'] : 'pcs',
                'note'        => isset($it['note']) ? trim((string)$it['note']) : null,
            ];
        }

        // ============ 1) Simpan ke DB ============
        $req = StockRequest::create([
            'requested_by'   => $requestedBy,
            'notes'          => $notes !== '' ? $notes : null,
            'channel'        => 'whatsapp',
            'status'         => 'pending',
            'request_status' => 'Requested',
            'wa_target'      => null,
            'branch'         => $branch,
            'approval_state' => 'pending',
        ]);

        foreach ($normalized as $row) {
            StockRequestItem::create([
                'stock_request_id' => $req->id,
                'name'             => $row['name'],
                'request_qty'      => $row['request_qty'],
                'unit'             => $row['unit'],
                'note'             => $row['note'],
            ]);
        }

        // ============ 2) Rakit Pesan WhatsApp ============
        $nowJakarta = Carbon::now('Asia/Jakarta');
        $sentAtText = $nowJakarta->format('d M Y, H:i') . ' WIB';

        $message  = "*📦 RESTOCK REQUEST - e+e Coffee n Kitchen*\n\n";
        $message .= "FROM: *{$requestedBy}*\n";
        $message .= "🕒 Sent on: *{$sentAtText}*\n\n";
        $message .= "Requested ITEM / GOODS:\n";

        foreach ($normalized as $row) {
            $line = "- {$row['name']} - {$row['request_qty']} {$row['unit']}";
            if (!empty($row['note'])) {
                $line .= " (note: " . mb_substr($row['note'], 0, 180) . ")";
            }
            $message .= $line . "\n";
        }

        if (!empty($notes)) {
            $message .= "\n*Notes:*\n" . mb_substr($notes, 0, 800) . "\n";
        }

        $message .= "\nPlease Proceed / buy as soon as possible 🙏";

        // ============ 3) Kirim ke Fonnte ============
        $token       = env('FONNTE_TOKEN');
        $targetPhone = env('SUPPLIER_WHATSAPP', env('ADMIN_WHATSAPP'));
        $waApiUrl    = 'https://api.fonnte.com/send';
        $targetPhone = $this->normalizePhone((string)$targetPhone);

        if (!$token || !$targetPhone) {
            $req->update(['status' => 'failed']);
            return response()->json(['message' => 'Fonnte token or target phone not configured'], 500);
        }

        $req->update(['wa_target' => $targetPhone]);

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->asForm()->post($waApiUrl, [
            'target'  => $targetPhone,
            'message' => $message,
        ]);

        // ============ 4) Update Status ============
        $req->wa_response_code = $response->status();
        $req->wa_response_body = $response->body();

        if ($response->failed()) {
            $req->status = 'failed';
            $req->save();

            Log::error('Fonnte send failed', [
                'stock_request_id' => $req->id,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return response()->json([
                'message' => 'Failed to send WhatsApp message',
                'error'   => $response->body(),
                'request_id' => $req->id,
            ], 500);
        }

        $req->status  = 'sent';
        $req->sent_at = $nowJakarta;
        $req->save();

        Log::info('Fonnte send OK', [
            'stock_request_id' => $req->id,
            'target'   => $targetPhone,
            'response' => $response->body(),
        ]);

        return response()->json([
            'message'        => 'Stock request sent successfully',
            'request_id'     => $req->id,
            'requested_by'   => $requestedBy,
            'items_count'    => count($normalized),
            'has_notes'      => !empty($notes),
            'sent_at'        => $sentAtText,
            'status'         => 'sent',
            'request_status' => $req->request_status,
        ]);
    }

    public function index(Request $request)
    {
        $status = $request->query('status');
        $reqStatus = $request->query('request_status');

        $q = StockRequest::with('items')
            ->when($status, fn($qq) => $qq->where('status', $status))
            ->when($reqStatus, fn($qq) => $qq->where('request_status', $reqStatus))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($q);
    }

    public function show($id)
    {
        $req = StockRequest::with('items')->findOrFail($id);
        return response()->json($req);
    }

    // ============ 5) Update Request Lifecycle Status ============
    // public function updateStatus(Request $request, $id)
    // {
    //     $validStatuses = ['Requested', 'Order by Purchasing', 'Delivery', 'Arrive', 'Refund'];

    //     $data = $request->validate([
    //         'request_status' => ['required', 'string', \Illuminate\Validation\Rule::in($validStatuses)],
    //     ]);

    //     $sr = StockRequest::findOrFail($id);
    //     $sr->update(['request_status' => $data['request_status']]);

    //     return response()->json([
    //         'success'        => true,
    //         'message'        => 'Request status updated successfully',
    //         'id'             => $sr->id,
    //         'request_status' => $sr->request_status,
    //     ]);
    // }

    // App/Http/Controllers/Api/StockRequestController.php

public function setItemTracking(Request $request, $id, $itemId)
{
    $data = $request->validate([
        'tracking_number' => 'required|string|max:100',
    ]);

    $item = \App\Models\StockRequestItem::where('stock_request_id', $id)
        ->where('id', $itemId)
        ->firstOrFail();

    $item->tracking_number = $data['tracking_number'];
    $item->save();

    return response()->json([
        'success' => true,
        'stock_request_id' => (int) $id,
        'item_id' => (int) $itemId,
        'tracking_number' => $item->tracking_number,
    ]);
}

public function updateStatus(Request $request, $id)
{
    $validStatuses = ['Requested', 'Order by Purchasing', 'Delivery', 'Arrive', 'Refund'];
    $data = $request->validate([
        'request_status' => ['required','string', Rule::in($validStatuses)],
    ]);

    $sr = StockRequest::with('items')->findOrFail($id);

    // ✅ Jika mau pindah ke Delivery → semua item wajib ada tracking_number
    if ($data['request_status'] === 'Delivery') {
        $missing = $sr->items()->whereNull('tracking_number')->orWhere('tracking_number','')->count();
        if ($missing > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Nomor resi wajib diisi untuk semua item sebelum pindah ke Delivery',
                'missing_items' => $missing,
            ], 422);
        }
    }

    $sr->update(['request_status' => $data['request_status']]);

    return response()->json([
        'success'        => true,
        'message'        => 'Request status updated successfully',
        'id'             => $sr->id,
        'request_status' => $sr->request_status,
    ]);
}



    // App/Http/Controllers/Api/StockRequestController.php
public function updateApproval(Request $request, $id)
{
    $data = $request->validate([
        'action'      => 'required|string|in:approve,reject',
        'approved_by' => 'required|string',
    ]);

    $sr = StockRequest::findOrFail($id);

    // set state
    $state = $data['action'] === 'approve' ? 'approved' : 'rejected';
    $sr->approval_state = $state;
    $sr->approved_by    = $data['approved_by'];
    $sr->approved_at    = now('Asia/Jakarta');

    // opsional: jika approve, boleh auto-ubah lifecycle awal (tetap 'Requested')
    // $sr->request_status = $sr->request_status ?: 'Requested';

    $sr->save();

    return response()->json([
        'success' => true,
        'id'      => $sr->id,
        'approval_state' => $sr->approval_state,
        'approved_by'    => $sr->approved_by,
        'approved_at'    => optional($sr->approved_at)->toDateTimeString(),
    ]);
}

// public function uploadProof(Request $request)
// {
//     $request->validate([
//         'photo'   => 'required|file|image|max:4096',
//         'request_id' => 'required|integer',
//         'item_id'    => 'required|integer',
//         'status'     => 'required|in:ok,defect',
//         'note'       => 'nullable|string|max:255',
//     ]);

//     $item = \App\Models\StockRequestItem::where('id', $request->item_id)
//         ->where('stock_request_id', $request->request_id)
//         ->first();

//     if (!$item) {
//         return response()->json(['success' => false, 'error' => 'Item not found'], 404);
//     }

//     // simpan file ke storage/app/public/stock_proofs
//     // $path = $request->file('photo')->store('public/stock_proofs');
//     // $url  = \Illuminate\Support\Facades\Storage::url($path); // /storage/stock_proofs/xxx.jpg

//     // Buat folder "upload_proof" di root project (bukan di public/)
// $uploadDir = base_path('upload_proof');
// if (!file_exists($uploadDir)) {
//     mkdir($uploadDir, 0775, true);
// }

// // Ambil file & buat nama unik
// $file = $request->file('photo');
// $filename = 'proof_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

// // Pindahkan file ke folder upload_proof/
// $file->move($uploadDir, $filename);

// // Simpan path relatif (bukan URL public)
// $relativePath = 'upload_proof/' . $filename;

// // Kalau mau generate URL manual (misal lewat route download)
// $url = url('/api/stock-requests/proof-file/' . $filename);


//     $item->update([
//         'photo_url'        => ltrim($url, '/'),
//         'item_condition'   => $request->status,
//         'defect_note'      => $request->note,
//         'proof_uploaded_at'=> now(),
//     ]);

//     // (opsional) ubah status request
//     if ($request->status === 'defect') {
//         $item->request()->update(['request_status' => 'Refund']);
//     } else {
//         $item->request()->update(['request_status' => 'Arrive']);
//     }

//     return response()->json([
//         'success' => true,
//         'message' => 'Proof uploaded',
//         'photo_url' => $url,
//         'status' => $request->status,
//     ]);
// }




public function uploadProof(Request $request)
{
    $request->validate([
        'photo'      => 'required|file|image|max:12288',
        'request_id' => 'required|integer',
        'item_id'    => 'required|integer',
        'status'     => 'required|in:ok,defect',
        'note'       => 'nullable|string|max:255',
    ]);

    $item = \App\Models\StockRequestItem::where('id', $request->item_id)
        ->where('stock_request_id', $request->request_id)
        ->first();

    if (!$item) {
        return response()->json(['success' => false, 'error' => 'Item not found'], 404);
    }

    // === Simpan ke folder di luar public ===
    $uploadDir = base_path('upload_proof');               // /.../pos_api/upload_proof
    if (!File::exists($uploadDir)) {
        File::makeDirectory($uploadDir, 0775, true);      // auto-buat folder
    }

    $file     = $request->file('photo');
    $ext      = strtolower($file->getClientOriginalExtension() ?: 'jpg');
    $filename = 'proof_' . time() . '_' . uniqid() . '.' . $ext;

    // Pindahkan file
    $file->move($uploadDir, $filename);

    // Bangun URL absolut TANPA mengandalkan APP_URL (biar gak jadi localhost)
    $origin = $request->getSchemeAndHttpHost();           // contoh: https://pos.domainmu.com
    $url    = $origin . '/pos-api/public/api/stock-requests/proof-file/' . rawurlencode($filename);

    // Update DB POS
    $item->update([
        'photo_url'         => ltrim(parse_url($url, PHP_URL_PATH), '/'), // simpan path relatif '/api/...'
        'item_condition'    => $request->status,
        'defect_note'       => $request->note,
        'proof_uploaded_at' => now('Asia/Jakarta'),
    ]);

    // Ubah status parent
    $item->request()->update([
        'request_status' => $request->status === 'defect' ? 'Refund' : 'Arrive'
    ]);

    return response()->json([
        'success'     => true,
        'message'     => 'Proof uploaded',
        'photo_url'   => $url,              // absolut → langsung bisa dipakai Flutter
        'status'      => $request->status,
        'request_id'  => (int)$request->request_id,
        'item_id'     => (int)$request->item_id,
    ]);
}




    private function normalizePhone(?string $raw): string
    {
        $p = preg_replace('/[^0-9]/', '', $raw ?? '');
        if (!$p) return '';
        if (str_starts_with($p, '0')) $p = '62' . substr($p, 1);
        return $p;
    }
}
