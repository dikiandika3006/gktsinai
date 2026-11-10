<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Matriks Hak Akses: hanya sel yang diubah dari aturan bawaan yang disimpan.
        Schema::create('role_menu_access', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50);
            $table->string('menu', 50);
            $table->unsignedTinyInteger('level'); // 0 tidak, 1 lihat, 2 kelola
            $table->timestamps();
            $table->unique(['role', 'menu']);
        });

        Role::findOrCreate('tim_ibadah', 'web');
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_access');
        Role::where('name', 'tim_ibadah')->where('guard_name', 'web')->delete();
    }
};
