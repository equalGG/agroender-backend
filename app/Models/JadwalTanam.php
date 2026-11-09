<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalTanam extends Model
{
    protected $fillable = [
        'petani_id', 'lahan_id', 'komoditas', 'luas_ha',
        'tgl_tanam', 'est_panen', 'tgl_panen', 'hasil_kg', 'verifikasi',
    ];

    protected $casts = [
        'tgl_tanam' => 'date',
        'est_panen' => 'date',
        'tgl_panen' => 'date',
    ];

    public function petani() { return $this->belongsTo(User::class, 'petani_id'); }
    public function lahan()  { return $this->belongsTo(Lahan::class); }
}