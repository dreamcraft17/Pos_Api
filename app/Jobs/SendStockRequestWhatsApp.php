<?php

namespace App\Jobs;

use App\Models\StockRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendStockRequestWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $stockRequestId,
        public string $message,
        public string $targetPhone,
    ) {}

    public function handle(): void
    {
        $req = StockRequest::find($this->stockRequestId);
        if (! $req) {
            return;
        }

        $token = config('pos.fonnte.token');
        if (! $token || ! $this->targetPhone) {
            $req->update(['status' => 'failed']);
            Log::error('Fonnte not configured', ['stock_request_id' => $this->stockRequestId]);

            return;
        }

        $req->update(['wa_target' => $this->targetPhone]);

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => $token,
        ])->asForm()->post(config('pos.fonnte.url'), [
            'target' => $this->targetPhone,
            'message' => $this->message,
        ]);

        $req->wa_response_code = $response->status();
        $req->wa_response_body = $response->body();

        if ($response->failed()) {
            $req->status = 'failed';
            $req->save();
            Log::error('Fonnte send failed', [
                'stock_request_id' => $req->id,
                'status' => $response->status(),
            ]);

            return;
        }

        $req->status = 'sent';
        $req->sent_at = Carbon::now('Asia/Jakarta');
        $req->save();

        Log::info('Fonnte send OK', ['stock_request_id' => $req->id]);
    }
}
