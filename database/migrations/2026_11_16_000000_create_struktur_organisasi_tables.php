<?php

use Database\Seeders\StrukturOrganisasiSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periode kepengurusan (mis. 2025–2027).
        Schema::create('org_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Unit: pimpinan, sekretariat, bidang, sub-bidang, komisi, tim.
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_period_id')->constrained('org_periods')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['org_period_id', 'type']);
        });

        // Orang dan jabatannya di unit. Nama tetap disimpan bila bukan / belum terdata sebagai jemaat.
        Schema::create('org_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->string('role', 30);
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('note')->nullable(); // mis. "Teologi & pengajaran", "Musik"
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index('member_id');
        });

        // Isi awal: struktur GKT Sinai Batu periode 2025–2027.
        (new StrukturOrganisasiSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('org_positions');
        Schema::dropIfExists('org_units');
        Schema::dropIfExists('org_periods');
    }
};
