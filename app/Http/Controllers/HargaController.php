<?php

namespace App\Http\Controllers;

use App\Models\LaporanHarga;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HargaController extends Controller
{
    // Publik: hanya data yang sudah diverifikasi, 7 hari terakhir
    public function index()
    {
        $rows = LaporanHarga::where('verifikasi', 'valid')
            ->where('tanggal', '>=', now()->subDays(7)->toDateString())
            ->selectRaw('komoditas, sumber, ROUND(AVG(harga_per_kg)) as rata2, MIN(harga_per_kg) as terendah, MAX(harga_per_kg) as tertinggi, COUNT(*) as jumlah_laporan')
            ->groupBy('komoditas', 'sumber')
            ->orderBy('komoditas')
            ->get();

        return response()->json($rows);
    }

    // Admin: input harga (masuk sebagai pending, menunggu verifikasi)
    public function store(Request $request)
    {
        $data = $request->validate([
            'komoditas'    => ['required', Rule::in(['bawang', 'jagung'])],
            'sumber'       => ['required', Rule::in(['pasar_lokal', 'pengepul'])],
            'harga_per_kg' => 'required|integer|min:1',
            'lokasi'       => 'nullable|string',
            'tanggal'      => 'required|date',
        ]);

        $data['pelapor_id'] = $request->user()->id;
        $data['verifikasi'] = 'pending';

        return response()->json(LaporanHarga::create($data), 201);
    }
}
