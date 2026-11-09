<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaporanHarga extends Model
{
    protected $fillable = [
        'pelapor_id', 'komoditas', 'sumber', 'harga_per_kg',
        'lokasi', 'tanggal', 'verifikasi',
    ];

    protected $casts = ['tanggal' => 'date'];

    public function pelapor() { return $this->belongsTo(User::class, 'pelapor_id'); }
}