<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaSession extends Model
{
    protected $fillable = ['phone', 'state', 'data'];

    protected $casts = ['data' => 'array'];
}