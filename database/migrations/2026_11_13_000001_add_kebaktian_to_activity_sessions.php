<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->string('kebaktian', 30)->nullable()->after('date'); // KU I, KU II, Gabungan
        });
    }

    public function down(): void
    {
        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->dropColumn('kebaktian');
        });
    }
};
