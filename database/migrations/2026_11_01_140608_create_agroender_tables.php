<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone')->nullable()->unique();
            });
        }

        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('petani');
            });
        }

        foreach (['email', 'password'] as $kolom) {
            if (Schema::hasColumn('users', $kolom)) {
                Schema::table('users', function (Blueprint $table) use ($kolom) {
                    $table->string($kolom)->nullable()->change();
                });
            }
        }

        Schema::create('lahans', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->decimal('luas_ha', 8, 2);
            $table->string('blok')->nullable();
            $table->string('status')->default('tersedia');
            $table->timestamps();
        });

        Schema::create('sewa_lahans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petani_id')->constrained('users');
            $table->foreignId('lahan_id')->constrained('lahans');
            $table->date('mulai');
            $table->date('selesai');
            $table->bigInteger('biaya')->nullable();
            $table->string('verifikasi')->default('pending');
            $table->timestamps();
        });

        Schema::create('jadwal_tanams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petani_id')->constrained('users');
            $table->foreignId('lahan_id')->constrained('lahans');
            $table->string('komoditas');
            $table->decimal('luas_ha', 8, 2);
            $table->date('tgl_tanam');
            $table->date('est_panen');
            $table->date('tgl_panen')->nullable();
            $table->decimal('hasil_kg', 12, 2)->nullable();
            $table->string('verifikasi')->default('pending');
            $table->timestamps();
        });

        Schema::create('laporan_hargas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelapor_id')->constrained('users');
            $table->string('komoditas');
            $table->string('sumber');
            $table->bigInteger('harga_per_kg');
            $table->string('lokasi')->nullable();
            $table->date('tanggal');
            $table->string('verifikasi')->default('pending');
            $table->timestamps();
        });

        Schema::create('pengepul_crms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('nama');
            $table->string('phone');
            $table->string('pasar')->nullable();
            $table->json('komoditas_minat')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('penawarans', function (Blueprint $table) {
            $table->id();
            $table->string('komoditas');
            $table->decimal('volume_kg', 12, 2);
            $table->bigInteger('harga_tawar')->nullable();
            $table->date('est_siap');
            $table->text('pesan');
            $table->timestamp('terkirim_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique();
            $table->string('state')->default('MENU');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_sessions');
        Schema::dropIfExists('penawarans');
        Schema::dropIfExists('pengepul_crms');
        Schema::dropIfExists('laporan_hargas');
        Schema::dropIfExists('jadwal_tanams');
        Schema::dropIfExists('sewa_lahans');
        Schema::dropIfExists('lahans');
    }
};
