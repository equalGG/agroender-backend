<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WaBotService;
use App\Services\WhatsApp;
use Illuminate\Http\Request;

class WaWebhookController extends Controller
{
    public function handle(Request $request, WaBotService $bot)
    {
        $kunci = (string) config('fonnte.webhook_key');

        if ($kunci === '' || !hash_equals($kunci, (string) $request->query('key'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $pesan = trim((string) $request->input('message', ''));
        $phone = $this->normal((string) $request->input('sender', ''));

        if ($phone === '' || $pesan === '') {
            return response()->json(['ok' => true]);
        }

        $user = User::where('phone', $phone)->first();

        $balasan = $user
            ? $bot->proses($user, $pesan)
            : "Nomor Anda belum terdaftar di AGROENDER. Hubungi admin desa untuk mendaftar.";

        WhatsApp::kirim($phone, $balasan);

        $out = ['ok' => true];
        if (!WhatsApp::aktif()) {
            $out['reply'] = $balasan; // hanya untuk mode tes
        }

        return response()->json($out);
    }

    private function normal(string $nomor): string
    {
        $p = preg_replace('/\D/', '', $nomor);

        if (str_starts_with($p, '0')) {
            return '62' . substr($p, 1);
        }
        if (str_starts_with($p, '8')) {
            return '62' . $p;
        }

        return $p;
    }
}
