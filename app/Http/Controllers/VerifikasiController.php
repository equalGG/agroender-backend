<?php

namespace App\Http\Controllers;

use App\Models\JadwalTanam;
use App\Models\Lahan;
use App\Models\LaporanHarga;
use App\Models\SewaLahan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VerifikasiController extends Controller
{
    const TIPE = [
        'jadwal' => JadwalTanam::class,
        'harga'  => LaporanHarga::class,
        'sewa'   => SewaLahan::class,
        'lahan'  => Lahan::class,
    ];

    public function index()
    {
        return response()->json([
            'jadwal' => JadwalTanam::with('petani:id,name,phone')->where('verifikasi', 'pending')->get(),
            'harga'  => LaporanHarga::with('pelapor:id,name,phone')->where('verifikasi', 'pending')->get(),
            'sewa'   => SewaLahan::with(['petani:id,name,phone', 'lahan:id,kode'])->where('verifikasi', 'pending')->get(),
            'lahan'  => Lahan::with('pemilik:id,name,phone')->where('verifikasi', 'pending')->get(),
        ]);
    }

    public function set(Request $request, string $tipe, int $id)
    {
        abort_unless(isset(self::TIPE[$tipe]), 404, 'Tipe tidak dikenal');

        $status = $request->validate([
            'status' => ['required', Rule::in(['valid', 'ditolak'])],
        ])['status'];

        $row = self::TIPE[$tipe]::findOrFail($id);
        $row->update(['verifikasi' => $status]);

        if ($tipe === 'sewa' && $status === 'valid') {
            Lahan::whereKey($row->lahan_id)->update(['status' => 'disewa']);
        }

        return response()->json($row);
    }
}