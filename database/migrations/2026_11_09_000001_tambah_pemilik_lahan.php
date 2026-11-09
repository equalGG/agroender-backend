<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lahans', function (Blueprint $table) {
            // Petani pemilik lahan (kosong = lahan desa yang bisa disewa)
            $table->foreignId('pemilik_id')->nullable()->constrained('users')->nullOnDelete();
            // Lahan buatan admin langsung valid, laporan petani via WhatsApp berstatus pending
            $table->string('verifikasi')->default('valid');
        });
    }

    public function down(): void
    {
        Schema::table('lahans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pemilik_id');
            $table->dropColumn('verifikasi');
        });
    }
};