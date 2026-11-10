<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberEvent extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        // Sinkronkan status/tanggal di tabel members dari riwayat mutasi.
        static::saved(function (MemberEvent $e) {
            $m = $e->member;
            if (! $m) {
                return;
            }
            match ($e->type) {
                'wafat' => $m->fill(['membership_status' => 'meninggal', 'death_date' => $e->date ?? $m->death_date]),
                'atestasi_keluar' => $m->fill(['membership_status' => 'keluar']),
                'sidi' => $m->membership_status !== 'meninggal' && $m->membership_status !== 'keluar'
                    ? $m->fill(['membership_status' => 'sidi']) : null,
                'baptis' => in_array($m->membership_status, ['simpatisan', 'anggota'], true)
                    ? $m->fill(['membership_status' => 'baptis']) : null,
                'nikah' => $e->date ? $m->fill(['marriage_date' => $e->date]) : null,
                default => null,
            };
            if ($m->isDirty()) {
                $m->save();
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
