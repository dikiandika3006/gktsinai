<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup & Restore Database (form rahasia di halaman Tentang, Alt+F7)
    |--------------------------------------------------------------------------
    |
    | PIN wajib dimasukkan sebelum form terbuka (khusus super admin).
    | Bila BACKUP_PIN kosong, fitur backup & restore nonaktif.
    |
    */

    'pin' => env('BACKUP_PIN'),

    // Folder file backup, relatif ke storage/app/private (tidak bisa diakses publik).
    'path' => 'backups',

    // Lama form tetap terbuka setelah PIN benar (menit).
    'unlock_minutes' => 15,

];
