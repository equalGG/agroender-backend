<?php

namespace App\Http\Controllers;

use App\Models\Lahan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LahanController extends Controller
{
    public function index()
    {
        return Lahan::with('pemilik:id,name,phone')->orderBy('kode')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode'       => 'required|string|unique:lahans,kode',
            'luas_ha'    => 'required|numeric|min:0.01',
            'blok'       => 'nullable|string',
            'pemilik_id' => 'nullable|exists:users,id',
        ]);

        // Lahan milik petani tidak bisa disewa orang lain
        $data['status'] = !empty($data['pemilik_id']) ? 'milik' : 'tersedia';
        $data['verifikasi'] = 'valid';

        return response()->json(Lahan::create($data)->load('pemilik:id,name,phone'), 201);
    }

    public function show(Lahan $lahan)
    {
        return $lahan->load('pemilik:id,name,phone');
    }

    public function update(Request $request, Lahan $lahan)
    {
        $data = $request->validate([
            'kode'       => ['sometimes', 'string', Rule::unique('lahans', 'kode')->ignore($lahan->id)],
            'luas_ha'    => 'sometimes|numeric|min:0.01',
            'blok'       => 'nullable|string',
            'pemilik_id' => 'nullable|exists:users,id',
            'status'     => ['sometimes', Rule::in(['tersedia', 'disewa', 'milik'])],
        ]);

        if (array_key_exists('pemilik_id', $data)) {
            $data['status'] = !empty($data['pemilik_id']) ? 'milik' : ($data['status'] ?? 'tersedia');
        }

        $lahan->update($data);

        return $lahan->load('pemilik:id,name,phone');
    }

    public function destroy(Lahan $lahan)
    {
        $lahan->delete();

        return response()->json(['message' => 'Lahan dihapus']);
    }
}