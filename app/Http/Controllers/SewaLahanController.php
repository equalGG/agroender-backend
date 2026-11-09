<?php

namespace App\Http\Controllers;

use App\Models\SewaLahan;
use Illuminate\Http\Request;

class SewaLahanController extends Controller
{
    public function index()
    {
        return SewaLahan::with(['petani:id,name,phone', 'lahan:id,kode,luas_ha'])
            ->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'petani_id' => 'required|exists:users,id',
            'lahan_id'  => 'required|exists:lahans,id',
            'mulai'     => 'required|date',
            'selesai'   => 'required|date|after:mulai',
            'biaya'     => 'nullable|integer|min:0',
        ]);

        $data['verifikasi'] = 'pending';

        return response()->json(SewaLahan::create($data), 201);
    }
}
