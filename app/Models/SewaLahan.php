<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SewaLahan extends Model
{
    protected $fillable = ['petani_id', 'lahan_id', 'mulai', 'selesai', 'biaya', 'verifikasi'];

    protected $casts = [
        'mulai'   => 'date',
        'selesai' => 'date',
    ];

    public function petani() { return $this->belongsTo(User::class, 'petani_id'); }
    public function lahan()  { return $this->belongsTo(Lahan::class); }
}