<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('petugas_absensi', 'web');
    }

    public function down(): void
    {
        Role::where('name', 'petugas_absensi')->where('guard_name', 'web')->delete();
    }
};
