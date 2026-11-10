<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ActivitySession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (ActivitySession $s) => $s->qr_token ??= Str::random(40));
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function getTitleAttribute(): string
    {
        return ($this->activityType?->name ?? 'Kegiatan').($this->kebaktian ? ' '.$this->kebaktian : '').' - '.$this->date?->format('d/m/Y')
            .($this->start_time ? ' '.substr($this->start_time, 0, 5) : '');
    }

    public function isCountOnly(): bool
    {
        return (bool) ($this->activityType?->count_only);
    }

    /** Total hadir: jumlah manual (bila ada) atau jumlah check-in. */
    public function getTotalHadirAttribute(): int
    {
        return $this->manual_count ?? $this->attendances()->count();
    }

    /** Waktu mulai/selesai jendela check-in QR (30 menit sebelum mulai s.d. selesai, default 3 jam). */
    public function checkinWindow(): array
    {
        $tgl = $this->date->format('Y-m-d');
        $mulai = Carbon::parse($tgl.' '.($this->start_time ?? '00:00:00'))->subMinutes(30);
        $selesai = $this->end_time
            ? Carbon::parse($tgl.' '.$this->end_time)->addMinutes(30)
            : ($this->start_time ? Carbon::parse($tgl.' '.$this->start_time)->addHours(3) : Carbon::parse($tgl.' 23:59:59'));

        return [$mulai, $selesai];
    }

    public function isCheckinOpen(): bool
    {
        [$a, $b] = $this->checkinWindow();

        return now()->between($a, $b);
    }

    /** Daftar jemaat yang diharapkan hadir (anggota komisi, atau semua jemaat tercatat untuk Ibadah Umum). */
    public function expectedMembersQuery(): Builder
    {
        $type = $this->activityType;
        $q = Member::query()->tercatat();
        if ($type?->commission_id) {
            $q->whereHas('commissions', fn ($c) => $c->where('commissions.id', $type->commission_id)
                ->where('commission_members.is_active', true));
        }

        return $q;
    }
}
