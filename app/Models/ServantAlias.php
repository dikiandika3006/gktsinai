<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Opsi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kamus nama pelayan di jadwal -> data jemaat. */
class ServantAlias extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_manual' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Hubungan nama -> jemaat berubah: perbarui semua tugas dengan nama itu.
        static::saved(function (ServantAlias $a) {
            if ($a->wasChanged('member_id') || $a->wasRecentlyCreated) {
                ServiceAssignment::where('kunci', $a->key)->update(['member_id' => $a->member_id]);
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ServiceAssignment::class, 'kunci', 'key');
    }

    /** Status untuk tampilan: anggota / simpatisan terdata / belum terdaftar (+kategori). */
    public static function statusLabel(?Member $m, ?string $kategori): string
    {
        if ($m) {
            return in_array($m->membership_status, Opsi::ANGGOTA_RESMI, true) ? 'Anggota' : Opsi::label(Opsi::KEANGGOTAAN, $m->membership_status);
        }

        return $kategori ? Opsi::label(Opsi::KATEGORI_PELAYAN, $kategori) : 'Belum terdaftar';
    }

    public static function statusColor(?Member $m, ?string $kategori): string
    {
        if ($m) {
            return in_array($m->membership_status, Opsi::ANGGOTA_RESMI, true) ? 'success' : 'warning';
        }

        return match ($kategori) {
            'pekerja', 'tamu' => 'gray',
            'simpatisan' => 'warning',
            default => 'danger',
        };
    }
}
