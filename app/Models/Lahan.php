<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lahan extends Model
{
    protected $fillable = ['kode', 'luas_ha', 'blok', 'status', 'pemilik_id', 'verifikasi'];

    public function sewa()    { return $this->hasMany(SewaLahan::class); }
    public function jadwal()  { return $this->hasMany(JadwalTanam::class); }
    public function pemilik() { return $this->belongsTo(User::class, 'pemilik_id'); }
}