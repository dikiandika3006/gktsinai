<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kolom tambahan agar semua kolom Excel jemaat lama tersimpan apa adanya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('baptized_at_gkt')->default(false)->after('activity_status');
            $table->boolean('confirmed_at_gkt')->default(false)->after('baptized_at_gkt');
            $table->string('legacy_status', 50)->nullable()->after('confirmed_at_gkt');
            $table->string('legacy_region', 100)->nullable()->after('legacy_status');
            $table->string('legacy_family_code', 50)->nullable()->after('legacy_region');
            $table->text('legacy_address')->nullable()->after('legacy_family_code');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['baptized_at_gkt', 'confirmed_at_gkt', 'legacy_status', 'legacy_region', 'legacy_family_code', 'legacy_address']);
        });
    }
};
