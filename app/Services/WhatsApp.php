<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsApp
{
    public static function aktif(): bool
    {
        return !empty(config('fonnte.token'));
    }

    public static function kirim(string $to, string $pesan): bool
    {
        if (!self::aktif()) {
            Log::info("WA (mode tes, tidak dikirim) ke {$to}: {$pesan}");
            return false;
        }

        try {
            $r = Http::withHeaders(['Authorization' => config('fonnte.token')])
                ->asForm()
                ->timeout(10)
                ->post('https://api.fonnte.com/send', [
                    'target'      => $to,
                    'message'     => $pesan,
                    'countryCode' => '62',
                ]);

            return $r->successful();
        } catch (\Throwable $e) {
            Log::error('Gagal kirim WA: ' . $e->getMessage());
            return false;
        }
    }
}
