<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitasi: pendamping dari data jemaat (tanpa akun login), dan anggota KK yang hadir
 * saat kunjungan dicentang (bukan hanya teks bebas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->unique(['visit_id', 'member_id']);
        });

        Schema::table('visit_reports', function (Blueprint $table) {
            $table->json('attendee_member_ids')->nullable()->after('visit_id');
        });
    }

    public function down(): void
    {
        Schema::table('visit_reports', fn (Blueprint $table) => $table->dropColumn('attendee_member_ids'));
        Schema::dropIfExists('visit_companions');
    }
};
