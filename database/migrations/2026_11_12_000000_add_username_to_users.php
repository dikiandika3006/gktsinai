<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
        });

        // Isi username dari bagian depan email (admin@... -> admin), dijamin unik.
        $dipakai = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email', 'name']) as $u) {
            $dasar = Str::of($u->email ? Str::before($u->email, '@') : $u->name)->lower()->slug('')->value() ?: 'user';
            $nama = $dasar;
            $i = 2;
            while (in_array($nama, $dipakai, true)) {
                $nama = $dasar.$i++;
            }
            $dipakai[] = $nama;
            DB::table('users')->where('id', $u->id)->update(['username' => $nama]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
