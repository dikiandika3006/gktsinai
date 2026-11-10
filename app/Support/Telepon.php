<?php

namespace App\Support;

class Telepon
{
    /** Normalisasi nomor HP Indonesia ke format E.164 (+62...). */
    public static function e164(?string $nomor): ?string
    {
        if ($nomor === null) {
            return null;
        }
        $digit = preg_replace('/\D+/', '', $nomor);
        if ($digit === '' || strlen($digit) < 8) {
            return null;
        }
        if (str_starts_with($digit, '62')) {
            return '+'.$digit;
        }
        if (str_starts_with($digit, '0')) {
            return '+62'.substr($digit, 1);
        }
        if (str_starts_with($digit, '8')) {
            return '+62'.$digit;
        }

        return '+'.$digit;
    }

    public static function waLink(?string $nomor, string $pesan = ''): ?string
    {
        $e164 = self::e164($nomor);
        if (! $e164) {
            return null;
        }

        return 'https://wa.me/'.ltrim($e164, '+').($pesan !== '' ? '?text='.rawurlencode($pesan) : '');
    }
}
