<?php

namespace App\Services;

use App\Models\JadwalTanam;
use App\Models\Lahan;
use App\Models\LaporanHarga;
use App\Models\SewaLahan;
use App\Models\User;
use App\Models\WaSession;
use Illuminate\Support\Carbon;

class WaBotService
{
    private const BATAL = "\n\n(Ketik *menu* untuk batal)";

    public function proses(User $user, string $pesan): string
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/', ' ', $pesan)));

        $s = WaSession::firstOrCreate(['phone' => $user->phone], ['state' => 'MENU']);

        // Sesi yang menggantung lebih dari 30 menit dikembalikan ke menu
        if ($s->state !== 'MENU' && $s->updated_at->lt(now()->subMinutes(30))) {
            $this->reset($s);
        }

        if (in_array($t, ['menu', '0', 'batal', 'halo', 'hai', 'hi', 'p', 'mulai', 'assalamualaikum'], true)) {
            $this->reset($s);
            return $this->menu($user);
        }

        $d = $s->data ?? [];

        return match ($s->state) {
            'TANAM_KOM'         => $this->tanamKom($user, $s, $t),
            'TANAM_BLOK'        => $this->tanamBlok($s, $d, $t),
            'TANAM_LUAS_PILIH'  => $this->tanamLuasPilih($s, $d, $t),
            'TANAM_LUAS'        => $this->tanamLuas($s, $d, $t),
            'TANAM_TGL_PILIH'   => $this->tanamTglPilih($user, $s, $d, $t),
            'TANAM_TGL'         => $this->tanamTgl($user, $s, $d, $t),
            'TANAM_KONFIRM'     => $this->tanamKonfirm($user, $s, $d, $t),
            'PANEN_PILIH'       => $this->panenPilih($s, $d, $t),
            'PANEN_HASIL'       => $this->panenHasil($s, $d, $t),
            'HARGA_KOM'         => $this->hargaKom($s, $d, $t),
            'HARGA_NILAI'       => $this->hargaNilai($s, $d, $t),
            'HARGA_LOKASI'      => $this->hargaLokasi($user, $s, $d, $t),
            'SEWA_BLOK'         => $this->sewaBlok($s, $d, $t),
            'SEWA_MULAI_PILIH'  => $this->sewaMulaiPilih($s, $d, $t),
            'SEWA_MULAI'        => $this->sewaMulai($s, $d, $t),
            'SEWA_BULAN_PILIH'  => $this->sewaBulanPilih($user, $s, $d, $t),
            'SEWA_BULAN'        => $this->sewaBulan($user, $s, $d, $t),
            default             => $this->pilihMenu($user, $s, $t),
        };
    }

    // ---------- Menu ----------

    private function menu(User $user): string
    {
        $nama = $user->name;
        $akhir = "\n9. Keluar\n\nBalas dengan angka pilihan.\n(Ketik *menu* kapan saja untuk kembali ke sini)";

        if ($user->role === 'petani') {
            return "🌱 *AGROENDER*\nHalo {$nama}! Silakan pilih:\n"
                . "1. Lapor Tanam\n2. Lapor Panen\n3. Lapor Harga Pasar\n"
                . "4. Cek Harga\n5. Jadwal & HST Saya\n6. Lapor Sewa Blok Lahan\n"
                . "7. Profil Saya\n8. Info Lahan & Komoditas Desa" . $akhir;
        }

        if ($user->role === 'pengepul') {
            return "🌱 *AGROENDER*\nHalo {$nama}! Silakan pilih:\n"
                . "1. Lapor Harga Transaksi\n2. Cek Harga\n3. Profil Saya\n"
                . "4. Info Lahan & Komoditas Desa" . $akhir;
        }

        return "🌱 *AGROENDER* (Admin)\nHalo {$nama}! Silakan pilih:\n"
            . "1. Cek Harga\n2. Jumlah Laporan Menunggu Verifikasi\n3. Profil Saya\n"
            . "4. Info Lahan & Komoditas Desa" . $akhir
            . "\n\nPenginputan dan verifikasi lengkap lewat aplikasi AGROENDER.";
    }

    private function pilihMenu(User $user, WaSession $s, string $t): string
    {
        if ($t === '9') {
            return $this->keluar($s);
        }

        $pilihKom = "\nKomoditas apa?\n1. Bawang\n2. Jagung\n\nBalas angka pilihan.";

        if ($user->role === 'petani') {
            return match ($t) {
                '1' => $this->ke($s, 'TANAM_KOM', [], "🌱 *Lapor Tanam*" . $pilihKom),
                '2' => $this->mulaiPanen($user, $s),
                '3' => $this->ke($s, 'HARGA_KOM', ['sumber' => 'pasar_lokal'], "💰 *Lapor Harga Pasar*" . $pilihKom),
                '4' => $this->cekHarga(),
                '5' => $this->jadwalSaya($user),
                '6' => $this->mulaiSewa($s),
                '7' => $this->profil($user),
                '8' => $this->infoDesa(),
                default => $this->menu($user),
            };
        }

        if ($user->role === 'pengepul') {
            return match ($t) {
                '1' => $this->ke($s, 'HARGA_KOM', ['sumber' => 'pengepul'], "💰 *Lapor Harga Transaksi*" . $pilihKom),
                '2' => $this->cekHarga(),
                '3' => $this->profil($user),
                '4' => $this->infoDesa(),
                default => $this->menu($user),
            };
        }

        return match ($t) {
            '1' => $this->cekHarga(),
            '2' => $this->jumlahPending(),
            '3' => $this->profil($user),
            '4' => $this->infoDesa(),
            default => $this->menu($user),
        };
    }

    private function profil(User $user): string
    {
        $peran = ['admin' => 'Admin', 'petani' => 'Petani', 'pengepul' => 'Pengepul'][$user->role] ?? ucfirst((string) $user->role);
        $nomor = str_starts_with((string) $user->phone, '62') ? '0' . substr($user->phone, 2) : $user->phone;

        return "👤 *Profil Saya*\n"
            . "Nama: {$user->name}\n"
            . "Nomor: {$nomor}\n"
            . "Peran: {$peran}\n\n"
            . "Untuk mengubah data, hubungi admin desa.\nKetik *menu* untuk kembali.";
    }

    private function keluar(WaSession $s): string
    {
        $this->reset($s);

        return "👋 Sesi ditutup. Terima kasih!\nKetik *halo* kapan saja untuk memulai lagi.";
    }

    // ---------- Info lahan & komoditas Desa Ender ----------

    private function infoDesa(): string
    {
        $lahans = Lahan::with([
            'sewa' => fn ($q) => $q->where('verifikasi', 'valid')
                ->whereDate('selesai', '>=', now()->toDateString())
                ->with('petani:id,name'),
            'jadwal' => fn ($q) => $q->whereNull('tgl_panen')
                ->where('verifikasi', '!=', 'ditolak')
                ->with('petani:id,name'),
        ])->orderBy('kode')->get();

        if ($lahans->isEmpty()) {
            return "Belum ada data blok lahan.\nKetik *menu* untuk kembali.";
        }

        $totalLuas   = $lahans->sum('luas_ha');
        $disewa      = $lahans->filter(fn ($l) => $l->sewa->isNotEmpty() || $l->status === 'disewa');
        $luasDisewa  = $disewa->sum('luas_ha');
        $jmlTersedia = $lahans->count() - $disewa->count();

        $out = "🌾 *Info Lahan & Komoditas Desa Ender*\n\n"
            . "*Ringkasan Lahan*\n"
            . "Jumlah blok: {$lahans->count()}\n"
            . "Total luas: " . $this->ha($totalLuas) . " ha\n"
            . "Disewa: {$disewa->count()} blok (" . $this->ha($luasDisewa) . " ha)\n"
            . "Tersedia: {$jmlTersedia} blok (" . $this->ha($totalLuas - $luasDisewa) . " ha)";

        $tanam = $lahans->flatMap(fn ($l) => $l->jadwal);

        $out .= "\n\n*Komoditas yang Sedang Ditanam*";
        if ($tanam->isEmpty()) {
            $out .= "\nBelum ada tanaman aktif.";
        } else {
            foreach ($tanam->groupBy('komoditas') as $kom => $items) {
                $petani = $items->pluck('petani_id')->unique()->count();
                $out .= "\n• " . ucfirst($kom) . ": " . $this->ha($items->sum('luas_ha')) . " ha"
                    . " ({$items->count()} penanaman, {$petani} petani)";
            }
            $out .= "\n(Termasuk laporan yang belum diverifikasi admin)";
        }

        $out .= "\n\n*Daftar Blok*";
        foreach ($lahans->take(20) as $l) {
            $sewa = $l->sewa->first();
            $status = ($sewa || $l->status === 'disewa') ? 'Disewa' : 'Tersedia';

            $out .= "\n\n*Blok {$l->kode}* - " . $this->ha($l->luas_ha) . " ha"
                . "\nStatus: {$status}";

            if ($sewa && $sewa->petani) {
                $out .= "\nPenyewa: {$sewa->petani->name}";
            }

            if ($l->jadwal->isEmpty()) {
                $out .= "\nTanaman: -";
            } else {
                foreach ($l->jadwal as $j) {
                    $out .= "\nTanaman: " . ucfirst($j->komoditas) . " " . $this->ha($j->luas_ha)
                        . " ha (HST " . JadwalService::hst($j->tgl_tanam) . ")";
                }
            }
        }

        if ($lahans->count() > 20) {
            $out .= "\n\n...dan " . ($lahans->count() - 20) . " blok lainnya (lihat lengkap di aplikasi AGROENDER).";
        }

        return $out . "\n\nKetik *menu* untuk kembali.";
    }

    // ---------- Lapor tanam ----------

    private function tanamKom(User $user, WaSession $s, string $t): string
    {
        $k = $this->komoditas($t);
        if (!$k) {
            return "Pilihan tidak dikenal. Balas *1* untuk Bawang atau *2* untuk Jagung.";
        }

        // Blok yang disewa petani (terverifikasi); kalau belum ada, tampilkan semua blok
        $idSewa = SewaLahan::where('petani_id', $user->id)->where('verifikasi', 'valid')->pluck('lahan_id');
        $q = Lahan::query()->orderBy('kode');
        if ($idSewa->isNotEmpty()) {
            $q->whereIn('id', $idSewa);
        }
        $rows = $q->take(9)->get();

        if ($rows->isEmpty()) {
            $this->reset($s);
            return "Belum ada blok lahan yang terdaftar. Hubungi admin desa.\nKetik *menu* untuk kembali.";
        }

        return $this->ke($s, 'TANAM_BLOK',
            ['komoditas' => $k, 'ids' => $rows->pluck('id')->all()],
            "Pilih blok lahan:" . $this->daftarBlok($rows) . "\n\nBalas angka pilihan.");
    }

    private function tanamBlok(WaSession $s, array $d, string $t): string
    {
        $ids = $d['ids'] ?? [];
        $i = $this->indeks($t, count($ids));
        if ($i === null) {
            return "Balas dengan angka blok yang ada di daftar (1 sampai " . count($ids) . ").";
        }

        $l = Lahan::find($ids[$i]);
        if (!$l) {
            $this->reset($s);
            return "Blok tidak ditemukan. Ketik *menu* untuk mulai lagi.";
        }

        unset($d['ids']);
        $d['lahan_id'] = $l->id;
        $d['kode'] = $l->kode;
        $d['luas_max'] = (float) $l->luas_ha;

        $max = $this->ha($d['luas_max']);
        $half = $this->ha(max(0.01, round($d['luas_max'] / 2, 2)));

        return $this->ke($s, 'TANAM_LUAS_PILIH', $d,
            "Luas tanam di Blok {$l->kode} (luas blok {$max} ha)?\n"
            . "1. Seluruh blok ({$max} ha)\n2. Setengah blok ({$half} ha)\n3. Tulis luas sendiri\n\nBalas angka pilihan.");
    }

    private function tanamLuasPilih(WaSession $s, array $d, string $t): string
    {
        if ($t === '1') {
            $d['luas'] = $d['luas_max'];
            return $this->tanyaTanggalTanam($s, $d);
        }
        if ($t === '2') {
            $d['luas'] = max(0.01, round($d['luas_max'] / 2, 2));
            return $this->tanyaTanggalTanam($s, $d);
        }
        if ($t === '3') {
            return $this->ke($s, 'TANAM_LUAS', $d,
                "Tulis luas dalam hektar, contoh: 0.5\n(Maksimal " . $this->ha($d['luas_max']) . " ha)");
        }

        return "Balas *1*, *2*, atau *3*.";
    }

    private function tanamLuas(WaSession $s, array $d, string $t): string
    {
        $l = $this->desimal($t);
        if ($l === null || $l <= 0) {
            return "Tulis angka luas dalam hektar, contoh: 0.5";
        }
        if ($l > $d['luas_max'] + 0.0001) {
            return "Luas melebihi luas blok (" . $this->ha($d['luas_max']) . " ha). Tulis luas yang lebih kecil.";
        }

        $d['luas'] = $l;

        return $this->tanyaTanggalTanam($s, $d);
    }

    private function tanyaTanggalTanam(WaSession $s, array $d): string
    {
        $h = now()->format('d-m-Y');
        $k = now()->subDay()->format('d-m-Y');

        return $this->ke($s, 'TANAM_TGL_PILIH', $d,
            "Kapan tanamnya?\n1. Hari ini ({$h})\n2. Kemarin ({$k})\n3. Tulis tanggal sendiri\n\nBalas angka pilihan.");
    }

    private function tanamTglPilih(User $user, WaSession $s, array $d, string $t): string
    {
        if ($t === '1') {
            return $this->prosesTanggalTanam($user, $s, $d, now()->startOfDay());
        }
        if ($t === '2') {
            return $this->prosesTanggalTanam($user, $s, $d, now()->subDay()->startOfDay());
        }
        if ($t === '3') {
            return $this->ke($s, 'TANAM_TGL', $d, "Tulis tanggal tanam, contoh: 25-10-2026");
        }

        return "Balas *1*, *2*, atau *3*.";
    }

    private function tanamTgl(User $user, WaSession $s, array $d, string $t): string
    {
        $tgl = $this->tanggal($t);
        if (!$tgl) {
            return "Format tanggal belum benar. Tulis seperti: 25-10-2026";
        }

        return $this->prosesTanggalTanam($user, $s, $d, $tgl);
    }

    private function prosesTanggalTanam(User $user, WaSession $s, array $d, Carbon $tgl): string
    {
        if ($tgl->gt(now()->addDays(30))) {
            return "Tanggal terlalu jauh ke depan (maksimal 30 hari dari sekarang). Tulis tanggal lain.";
        }

        $cek = app(OversupplyService::class)->cek($d['komoditas'], (float) $d['luas'], $tgl);

        if ($cek['aman']) {
            return $this->simpanTanam($user, $s, $d, $tgl);
        }

        $d['tgl'] = $tgl->toDateString();
        $d['saran'] = $cek['saran_tgl_tanam'];
        $saran = Carbon::parse($d['saran'])->format('d-m-Y');
        $panen = Carbon::parse($cek['est_panen'])->format('d-m-Y');

        return $this->ke($s, 'TANAM_KONFIRM', $d,
            "⚠️ *Minggu panen {$panen} sudah padat* (risiko kelebihan pasokan {$d['komoditas']}).\n\n"
            . "Pilih:\n1. Tetap lapor tanggal {$tgl->format('d-m-Y')}\n2. Pakai tanggal saran: {$saran}\n\nBalas angka pilihan.");
    }

    private function tanamKonfirm(User $user, WaSession $s, array $d, string $t): string
    {
        if ($t === '1') {
            return $this->simpanTanam($user, $s, $d, Carbon::parse($d['tgl']));
        }
        if ($t === '2') {
            return $this->simpanTanam($user, $s, $d, Carbon::parse($d['saran']));
        }

        return "Balas *1* untuk tetap pakai tanggal Anda, atau *2* untuk tanggal saran.";
    }

    private function simpanTanam(User $user, WaSession $s, array $d, Carbon $tgl): string
    {
        $est = JadwalService::estPanen($d['komoditas'], $tgl);

        JadwalTanam::create([
            'petani_id'  => $user->id,
            'lahan_id'   => $d['lahan_id'],
            'komoditas'  => $d['komoditas'],
            'luas_ha'    => $d['luas'],
            'tgl_tanam'  => $tgl->toDateString(),
            'est_panen'  => $est->toDateString(),
            'verifikasi' => 'pending',
        ]);

        $this->reset($s);

        return "✅ *Laporan tanam diterima*\n"
            . "Komoditas: " . ucfirst($d['komoditas']) . "\n"
            . "Blok: {$d['kode']} (" . $this->ha($d['luas']) . " ha)\n"
            . "Tanggal tanam: " . $tgl->format('d-m-Y') . "\n"
            . "Estimasi panen: " . $est->format('d-m-Y') . "\n\n"
            . "Status: menunggu verifikasi admin.\nKetik *menu* untuk kembali.";
    }

    // ---------- Lapor panen ----------

    private function mulaiPanen(User $user, WaSession $s): string
    {
        $rows = $this->jadwalAktif($user)->take(9)->values();

        if ($rows->isEmpty()) {
            return "Belum ada tanaman aktif yang bisa dilaporkan panennya.\nKetik *menu* untuk kembali.";
        }

        $teks = "🌾 *Lapor Panen*\nPilih tanaman yang dipanen:";
        foreach ($rows as $i => $j) {
            $teks .= "\n" . ($i + 1) . ". " . ucfirst($j->komoditas) . " - Blok " . ($j->lahan->kode ?? '-')
                . " (" . $this->ha($j->luas_ha) . " ha), HST " . JadwalService::hst($j->tgl_tanam);
        }

        return $this->ke($s, 'PANEN_PILIH', ['ids' => $rows->pluck('id')->all()],
            $teks . "\n\nBalas angka pilihan.");
    }

    private function panenPilih(WaSession $s, array $d, string $t): string
    {
        $ids = $d['ids'] ?? [];
        $i = $this->indeks($t, count($ids));
        if ($i === null) {
            return "Balas dengan nomor tanaman yang ada di daftar (1 sampai " . count($ids) . ").";
        }

        return $this->ke($s, 'PANEN_HASIL', ['jadwal_id' => $ids[$i]],
            "Berapa hasil panen dalam kg?\nTulis angkanya saja, contoh: 1500");
    }

    private function panenHasil(WaSession $s, array $d, string $t): string
    {
        $kg = $this->desimal($t);
        if ($kg === null || $kg <= 0) {
            return "Tulis angka hasil panen dalam kg, contoh: 1500";
        }

        $j = JadwalTanam::find($d['jadwal_id'] ?? 0);
        if (!$j) {
            $this->reset($s);
            return "Data tanaman tidak ditemukan. Ketik *menu* untuk mulai lagi.";
        }

        $j->update([
            'tgl_panen'  => now()->toDateString(),
            'hasil_kg'   => $kg,
            'verifikasi' => 'pending',
        ]);

        $this->reset($s);

        return "✅ *Laporan panen diterima*\n" . ucfirst($j->komoditas) . ": " . $this->ha($kg) . " kg\n"
            . "Status: menunggu verifikasi admin.\nKetik *menu* untuk kembali.";
    }

    // ---------- Lapor harga ----------

    private function hargaKom(WaSession $s, array $d, string $t): string
    {
        $k = $this->komoditas($t);
        if (!$k) {
            return "Pilihan tidak dikenal. Balas *1* untuk Bawang atau *2* untuk Jagung.";
        }

        $d['komoditas'] = $k;

        return $this->ke($s, 'HARGA_NILAI', $d, "Harga per kg berapa?\nTulis angkanya saja, contoh: 32000");
    }

    private function hargaNilai(WaSession $s, array $d, string $t): string
    {
        $h = $this->rupiah($t);
        if ($h === null || $h < 500 || $h > 1000000) {
            return "Tulis angka harga per kg, contoh: 32000";
        }

        $d['harga'] = $h;

        return $this->ke($s, 'HARGA_LOKASI', $d,
            "Di mana lokasinya?\nTulis nama pasar/tempatnya, contoh: Pasar Ender\nAtau balas *1* untuk lewati.");
    }

    private function hargaLokasi(User $user, WaSession $s, array $d, string $t): string
    {
        $lokasi = in_array($t, ['1', '-'], true) ? null : ucwords($t);

        LaporanHarga::create([
            'pelapor_id'   => $user->id,
            'komoditas'    => $d['komoditas'],
            'sumber'       => $d['sumber'],
            'harga_per_kg' => $d['harga'],
            'lokasi'       => $lokasi,
            'tanggal'      => now()->toDateString(),
            'verifikasi'   => 'pending',
        ]);

        $this->reset($s);

        return "✅ *Laporan harga diterima*\n" . ucfirst($d['komoditas']) . ": " . $this->rp($d['harga']) . "/kg"
            . ($lokasi ? "\nLokasi: {$lokasi}" : '')
            . "\n\nStatus: menunggu verifikasi admin.\nKetik *menu* untuk kembali.";
    }

    // ---------- Lapor sewa blok lahan ----------

    private function mulaiSewa(WaSession $s): string
    {
        $rows = Lahan::where('status', 'tersedia')->orderBy('kode')->take(9)->get();

        if ($rows->isEmpty()) {
            return "Belum ada blok yang tersedia untuk disewa.\nKetik *menu* untuk kembali.";
        }

        return $this->ke($s, 'SEWA_BLOK', ['ids' => $rows->pluck('id')->all()],
            "🏞️ *Lapor Sewa Blok Lahan*\nPilih blok yang disewa:" . $this->daftarBlok($rows) . "\n\nBalas angka pilihan.");
    }

    private function sewaBlok(WaSession $s, array $d, string $t): string
    {
        $ids = $d['ids'] ?? [];
        $i = $this->indeks($t, count($ids));
        if ($i === null) {
            return "Balas dengan angka blok yang ada di daftar (1 sampai " . count($ids) . ").";
        }

        $l = Lahan::find($ids[$i]);
        if (!$l || $l->status !== 'tersedia') {
            $this->reset($s);
            return "Blok itu sudah tidak tersedia. Ketik *menu* untuk mulai lagi.";
        }

        $h = now()->format('d-m-Y');

        return $this->ke($s, 'SEWA_MULAI_PILIH', ['lahan_id' => $l->id, 'kode' => $l->kode],
            "Blok {$l->kode}. Mulai sewa kapan?\n1. Hari ini ({$h})\n2. Tulis tanggal sendiri\n\nBalas angka pilihan.");
    }

    private function sewaMulaiPilih(WaSession $s, array $d, string $t): string
    {
        if ($t === '1') {
            $d['mulai'] = now()->toDateString();
            return $this->tanyaBulan($s, $d);
        }
        if ($t === '2') {
            return $this->ke($s, 'SEWA_MULAI', $d, "Tulis tanggal mulai sewa, contoh: 01-11-2026");
        }

        return "Balas *1* atau *2*.";
    }

    private function sewaMulai(WaSession $s, array $d, string $t): string
    {
        $tgl = $this->tanggal($t);
        if (!$tgl) {
            return "Format tanggal belum benar. Tulis seperti: 01-11-2026";
        }

        $d['mulai'] = $tgl->toDateString();

        return $this->tanyaBulan($s, $d);
    }

    private function tanyaBulan(WaSession $s, array $d): string
    {
        return $this->ke($s, 'SEWA_BULAN_PILIH', $d,
            "Berapa lama masa sewa?\n1. 3 bulan\n2. 6 bulan\n3. 12 bulan\n4. Tulis jumlah bulan sendiri\n\nBalas angka pilihan.");
    }

    private function sewaBulanPilih(User $user, WaSession $s, array $d, string $t): string
    {
        $peta = ['1' => 3, '2' => 6, '3' => 12];

        if (isset($peta[$t])) {
            return $this->simpanSewa($user, $s, $d, $peta[$t]);
        }
        if ($t === '4') {
            return $this->ke($s, 'SEWA_BULAN', $d, "Tulis jumlah bulan (1 sampai 24), contoh: 8");
        }

        return "Balas *1*, *2*, *3*, atau *4*.";
    }

    private function sewaBulan(User $user, WaSession $s, array $d, string $t): string
    {
        if (!ctype_digit($t) || (int) $t < 1 || (int) $t > 24) {
            return "Tulis angka bulan antara 1 sampai 24, contoh: 8";
        }

        return $this->simpanSewa($user, $s, $d, (int) $t);
    }

    private function simpanSewa(User $user, WaSession $s, array $d, int $bulan): string
    {
        $mulai = Carbon::parse($d['mulai']);
        $selesai = $mulai->copy()->addMonths($bulan);

        SewaLahan::create([
            'petani_id'  => $user->id,
            'lahan_id'   => $d['lahan_id'],
            'mulai'      => $mulai->toDateString(),
            'selesai'    => $selesai->toDateString(),
            'verifikasi' => 'pending',
        ]);

        $this->reset($s);

        return "✅ *Laporan sewa blok diterima*\nBlok: {$d['kode']}\n"
            . "Masa sewa: " . $mulai->format('d-m-Y') . " sampai " . $selesai->format('d-m-Y') . " ({$bulan} bulan)"
            . "\n\nStatus: menunggu verifikasi admin.\nKetik *menu* untuk kembali.";
    }

    // ---------- Informasi ----------

    private function cekHarga(): string
    {
        $rows = LaporanHarga::where('verifikasi', 'valid')
            ->where('tanggal', '>=', now()->subDays(7)->toDateString())
            ->selectRaw('komoditas, sumber, ROUND(AVG(harga_per_kg)) as rata2, MIN(harga_per_kg) as terendah, MAX(harga_per_kg) as tertinggi')
            ->groupBy('komoditas', 'sumber')
            ->orderBy('komoditas')
            ->get();

        if ($rows->isEmpty()) {
            return "Belum ada data harga terverifikasi dalam 7 hari terakhir.\nKetik *menu* untuk kembali.";
        }

        $out = "💰 *Harga 7 hari terakhir* (terverifikasi)";
        foreach ($rows->groupBy('komoditas') as $kom => $items) {
            $out .= "\n\n*" . ucfirst($kom) . "*";
            foreach ($items as $r) {
                $label = $r->sumber === 'pengepul' ? 'Pengepul' : 'Pasar lokal';
                $out .= "\n• {$label}: rata-rata " . $this->rp($r->rata2) . "/kg ("
                    . $this->rp($r->terendah) . " - " . $this->rp($r->tertinggi) . ")";
            }
        }

        return $out . "\n\nKetik *menu* untuk kembali.";
    }

    private function jadwalSaya(User $user): string
    {
        $rows = $this->jadwalAktif($user);

        if ($rows->isEmpty()) {
            return "Belum ada tanaman aktif.\nKetik *menu* untuk kembali.";
        }

        $status = ['pending' => 'menunggu verifikasi', 'valid' => 'terverifikasi'];
        $out = "📅 *Jadwal & HST Anda*";

        foreach ($rows as $j) {
            $out .= "\n\n🌾 *" . ucfirst($j->komoditas) . "* - Blok " . ($j->lahan->kode ?? '-') . " (" . $this->ha($j->luas_ha) . " ha)"
                . "\nHST: " . JadwalService::hst($j->tgl_tanam) . " hari"
                . "\nEst. panen: " . $j->est_panen->format('d-m-Y')
                . "\nStatus: " . ($status[$j->verifikasi] ?? $j->verifikasi);
        }

        return $out . "\n\nKetik *menu* untuk kembali.";
    }

    private function jumlahPending(): string
    {
        $jadwal = JadwalTanam::where('verifikasi', 'pending')->count();
        $harga  = LaporanHarga::where('verifikasi', 'pending')->count();
        $sewa   = SewaLahan::where('verifikasi', 'pending')->count();

        return "🗂️ *Menunggu verifikasi*\nJadwal tanam/panen: {$jadwal}\nHarga: {$harga}\nSewa blok: {$sewa}"
            . "\n\nVerifikasi lewat aplikasi AGROENDER.\nKetik *menu* untuk kembali.";
    }

    // ---------- Alat bantu ----------

    private function jadwalAktif(User $user)
    {
        return JadwalTanam::with('lahan:id,kode')
            ->where('petani_id', $user->id)
            ->whereNull('tgl_panen')
            ->where('verifikasi', '!=', 'ditolak')
            ->orderBy('tgl_tanam')
            ->get();
    }

    private function daftarBlok($rows): string
    {
        $out = '';
        foreach ($rows->values() as $i => $l) {
            $out .= "\n" . ($i + 1) . ". Blok {$l->kode} (" . $this->ha($l->luas_ha) . " ha)";
        }

        return $out;
    }

    private function indeks(string $t, int $jumlah): ?int
    {
        if (!ctype_digit($t) || (int) $t < 1 || (int) $t > $jumlah) {
            return null;
        }

        return (int) $t - 1;
    }

    private function ke(WaSession $s, string $state, array $data, string $balasan): string
    {
        $s->update(['state' => $state, 'data' => $data]);

        return $balasan . self::BATAL;
    }

    private function reset(WaSession $s): void
    {
        $s->update(['state' => 'MENU', 'data' => null]);
    }

    private function komoditas(string $t): ?string
    {
        return match ($t) {
            '1', 'bawang' => 'bawang',
            '2', 'jagung' => 'jagung',
            default => null,
        };
    }

    private function tanggal(string $t): ?Carbon
    {
        if (in_array($t, ['hari ini', 'hariini', 'sekarang'], true)) {
            return now()->startOfDay();
        }
        if ($t === 'kemarin') {
            return now()->subDay()->startOfDay();
        }

        $t = str_replace(['/', '.'], '-', $t);

        if (!preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $t, $m) || !checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        return Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
    }

    private function desimal(string $t): ?float
    {
        $t = str_replace(',', '.', $t);

        return is_numeric($t) ? (float) $t : null;
    }

    private function rupiah(string $t): ?int
    {
        $n = (int) preg_replace('/\D/', '', $t);

        return $n > 0 ? $n : null;
    }

    private function ha($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }

    private function rp($n): string
    {
        return 'Rp' . number_format((float) $n, 0, ',', '.');
    }
}