<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class JadwalService
{
    // Umur tanaman dalam hari (angka contoh, sesuaikan dengan kondisi Desa Ender)
    const UMUR = ['bawang' => 65, 'jagung' => 100];

    public static function hst($tglTanam): int
    {
        $tgl = Carbon::parse($tglTanam)->startOfDay();

        return max(0, (int) $tgl->diffInDays(now()->startOfDay()));
    }

    public static function estPanen(string $komoditas, $tglTanam): Carbon
    {
        return Carbon::parse($tglTanam)->addDays(self::UMUR[$komoditas]);
    }
}
