<?php

namespace App\Http\Controllers;

use App\Models\JadwalTanam;
use App\Services\JadwalService;
use App\Services\OversupplyService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class JadwalTanamController extends Controller
{
    public function index()
    {
        $rows = JadwalTanam::with(['petani:id,name,phone', 'lahan:id,kode,luas_ha'])
            ->orderBy('est_panen')->get();

        $rows->each(function ($j) {
            $j->hst = $j->tgl_panen ? null : JadwalService::hst($j->tgl_tanam);
        });

        return $rows;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'petani_id' => 'required|exists:users,id',
            'lahan_id'  => 'required|exists:lahans,id',
            'komoditas' => ['required', Rule::in(['bawang', 'jagung'])],
            'luas_ha'   => 'required|numeric|min:0.01',
            'tgl_tanam' => 'required|date',
            'paksa'     => 'nullable|boolean',
        ]);

        $tgl = Carbon::parse($data['tgl_tanam']);
        $cek = app(OversupplyService::class)->cek($data['komoditas'], (float) $data['luas_ha'], $tgl);

        if (!$cek['aman'] && !$request->boolean('paksa')) {
            return response()->json([
                'message'    => 'Minggu panen itu sudah padat (risiko oversupply)',
                'oversupply' => $cek,
            ], 422);
        }

        $jadwal = JadwalTanam::create([
            'petani_id'  => $data['petani_id'],
            'lahan_id'   => $data['lahan_id'],
            'komoditas'  => $data['komoditas'],
            'luas_ha'    => $data['luas_ha'],
            'tgl_tanam'  => $tgl->toDateString(),
            'est_panen'  => $cek['est_panen'],
            'verifikasi' => 'pending',
        ]);

        return response()->json(['jadwal' => $jadwal, 'oversupply' => $cek], 201);
    }
}
