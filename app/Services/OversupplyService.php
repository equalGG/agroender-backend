<?php

namespace App\Services;

use App\Models\JadwalTanam;
use Illuminate\Support\Carbon;

class OversupplyService
{
    // Angka contoh, sesuaikan dengan kondisi nyata
    const KAPASITAS_MINGGUAN_KG = ['bawang' => 20000, 'jagung' => 30000];
    const HASIL_KG_PER_HA       = ['bawang' => 10000, 'jagung' => 6000];

    public function cek(string $komoditas, float $luasHa, Carbon $tglTanam): array
    {
        $estPanen = JadwalService::estPanen($komoditas, $tglTanam);
        $awal  = $estPanen->copy()->startOfWeek();
        $akhir = $estPanen->copy()->endOfWeek();

        $luasSudah = JadwalTanam::where('komoditas', $komoditas)
            ->where('verifikasi', '!=', 'ditolak')
            ->whereBetween('est_panen', [$awal->toDateString(), $akhir->toDateString()])
            ->sum('luas_ha');

        $volume = ($luasSudah + $luasHa) * self::HASIL_KG_PER_HA[$komoditas];
        $aman   = $volume <= self::KAPASITAS_MINGGUAN_KG[$komoditas];

        return [
            'aman'               => $aman,
            'est_panen'          => $estPanen->toDateString(),
            'volume_minggu_kg'   => $volume,
            'kapasitas_minggu_kg' => self::KAPASITAS_MINGGUAN_KG[$komoditas],
            'saran_tgl_tanam'    => $aman ? null : $tglTanam->copy()->addWeek()->toDateString(),
        ];
    }
}
