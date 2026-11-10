<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jadwal ibadah Minggu (satu baris per tanggal).
        Schema::create('worship_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('tema')->nullable();
            $table->string('ayat_referensi')->nullable();
            $table->string('introitus')->nullable();
            $table->string('phb')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        // Kamus nama pelayan: satu nama tertulis di jadwal -> jemaat (atau kategori bila belum terdaftar).
        Schema::create('servant_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('key', 150)->unique();          // nama tanpa gelar, huruf kecil
            $table->string('display_name');                // contoh penulisan di jadwal
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->boolean('is_manual')->default(false);  // dihubungkan manual (tidak ditimpa pencocokan otomatis)
            $table->string('category', 20)->nullable();    // simpatisan, pekerja, tamu (untuk yang belum terdaftar)
            $table->timestamps();
        });

        // Petugas per tanggal / kebaktian / peran.
        Schema::create('service_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worship_schedule_id')->constrained('worship_schedules')->cascadeOnDelete();
            $table->string('kebaktian', 30)->nullable();   // KU I, KU II; null = semua kebaktian
            $table->string('peran', 30);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('nama');                        // persis seperti tertulis
            $table->string('kunci', 150);                  // -> servant_aliases.key
            $table->boolean('is_keluarga')->default(false); // "Kel. Bp. ..."
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();

            $table->index('kunci');
            $table->index(['member_id', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_assignments');
        Schema::dropIfExists('servant_aliases');
        Schema::dropIfExists('worship_schedules');
    }
};
