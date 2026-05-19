<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends BaseApiController
{
    /**
     * POST /api/upload-menu-image
     * Multipart: image (required)
     * Response: { ok: true, url: "/storage/menu-images/xxx.jpg" }
     */
    public function menuImage(Request $r)
    {
        $u = $this->currentUser($r);
        if (!$u) return response()->json(['ok'=>false, 'message'=>'unauthenticated'], 401);

        $data = $r->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120', // 5 MB
        ]);

        $file     = $data['image'];
        $ext      = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString() . '.' . $ext;

        // Simpan ke storage public/menu-images
        $path = $file->storeAs('public/menu-images', $filename);

        // Pastikan sudah jalankan: php artisan storage:link
        $publicUrl = Storage::url('menu-images/' . $filename);

        return ['ok' => true, 'url' => $publicUrl];
    }
}
