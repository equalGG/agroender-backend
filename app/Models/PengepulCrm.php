<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengepulCrm extends Model
{
    protected $fillable = ['user_id', 'nama', 'phone', 'pasar', 'komoditas_minat', 'catatan'];

    protected $casts = ['komoditas_minat' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
}