<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penawaran extends Model
{
    protected $fillable = ['komoditas', 'volume_kg', 'harga_tawar', 'est_siap', 'pesan', 'terkirim_at'];

    protected $casts = [
        'est_siap'    => 'date',
        'terkirim_at' => 'datetime',
    ];
}