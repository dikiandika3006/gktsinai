<?php

return [
    // Nama gereja untuk kop laporan.
    'church_name' => env('SIJ_CHURCH_NAME', 'GKT Jemaat Sinai Batu'),
    'church_city' => env('SIJ_CHURCH_CITY', 'Batu, Jawa Timur'),

    // Geocoding (DJ-14). driver: nominatim | google
    'geocoder' => env('SIJ_GEOCODER', 'nominatim'),
    'google_geocoding_key' => env('GOOGLE_GEOCODING_KEY'),
    'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
    'nominatim_email' => env('NOMINATIM_EMAIL'),
    'geocode_suffix' => env('SIJ_GEOCODE_SUFFIX', 'Kota Batu, Jawa Timur, Indonesia'),
    'geocode_on_save' => env('SIJ_GEOCODE_ON_SAVE', true),

    // Titik tengah peta (Kota Batu).
    'map_center' => [-7.8710, 112.5260],

    // TF-03: batas waktu follow-up tamu (jam).
    'fu_deadline_hours' => 48,

    // KH-07: jumlah ketidakhadiran berturut-turut sebelum masuk daftar perhatian.
    'absence_threshold' => 3,

    // KH-09: usulan status Pasif bila tidak hadir selama N bulan.
    'passive_after_months' => 3,

    // VS-03
    'elderly_age' => 70,
    'visit_overdue_months' => 12,

    // Template pesan WhatsApp (TF-08). {nama} diganti nama tamu.
    'wa_templates' => [
        'sambutan' => "Shalom {nama}, terima kasih sudah beribadah bersama kami di GKT Jemaat Sinai Batu. Kiranya Tuhan memberkati. Kami senang bila bisa bertemu lagi minggu depan.",
        'undangan' => "Shalom {nama}, kami mengundang Bapak/Ibu/Saudara untuk beribadah bersama di GKT Jemaat Sinai Batu setiap hari Minggu. Tuhan memberkati.",
    ],
];
