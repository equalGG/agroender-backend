<?php

namespace App\Http\Controllers;

use App\Models\PengepulCrm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengepulController extends Controller
{
    public function index()
    {
        return PengepulCrm::orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'              => 'required|string',
            'phone'             => 'required|string',
            'pasar'             => 'nullable|string',
            'komoditas_minat'   => 'nullable|array',
            'komoditas_minat.*' => Rule::in(['bawang', 'jagung']),
            'catatan'           => 'nullable|string',
        ]);

        return response()->json(PengepulCrm::create($data), 201);
    }

    public function show(PengepulCrm $pengepul)
    {
        return $pengepul;
    }

    public function update(Request $request, PengepulCrm $pengepul)
    {
        $data = $request->validate([
            'nama'              => 'sometimes|string',
            'phone'             => 'sometimes|string',
            'pasar'             => 'nullable|string',
            'komoditas_minat'   => 'nullable|array',
            'komoditas_minat.*' => Rule::in(['bawang', 'jagung']),
            'catatan'           => 'nullable|string',
        ]);

        $pengepul->update($data);

        return $pengepul;
    }

    public function destroy(PengepulCrm $pengepul)
    {
        $pengepul->delete();

        return response()->json(['message' => 'Pengepul dihapus']);
    }
}
