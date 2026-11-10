<?php

namespace App\Models;

use App\Jobs\GeocodeFamily;
use App\Models\Concerns\Auditable;
use App\Support\Telepon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Family extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geocoded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Family $f) {
            $f->code ??= static::nextCode();
        });

        static::saving(function (Family $f) {
            if ($f->address !== null) {
                $f->address = trim(preg_replace('/\s+/u', ' ', $f->address));
            }
            $f->phone = Telepon::e164($f->phone);

            // Pin digeser manual: tandai "manual" dan hitung ulang usulan wilayah (DJ-15).
            if ($f->isDirty(['latitude', 'longitude'])) {
                if ($f->latitude !== null && ! $f->isDirty('geocode_status')) {
                    $f->geocode_status = 'manual';
                    $f->geocoded_at = now();
                }
                $f->suggested_region_id = Region::suggestFor($f->latitude, $f->longitude)?->id;
            }
        });

        // Geocoding otomatis saat alamat berubah (lewat antrean).
        static::saved(function (Family $f) {
            if ($f->wasChanged('address') || ($f->wasRecentlyCreated && $f->address)) {
                if ($f->geocode_status !== 'manual' && config('sij.geocode_on_save', true)) {
                    GeocodeFamily::dispatch($f->id);
                }
            }
        });
    }

    public static function nextCode(): string
    {
        $last = static::query()->where('code', 'like', 'KK-%')
            ->orderByRaw('CAST(SUBSTRING(code, 4) AS UNSIGNED) DESC')->value('code');
        $n = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'KK-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function suggestedRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'suggested_region_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'head_member_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function lastVisit(): HasOne
    {
        return $this->hasOne(Visit::class)->where('status', 'selesai')->latestOfMany('realized_date');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->code.' - '.($this->head?->name ?? $this->members()->orderBy('id')->value('name') ?? 'Tanpa kepala keluarga');
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->lihatSemuaJemaat()) {
            return $q;
        }
        if ($user->hasRole('koordinator_wilayah') && $user->region_id) {
            return $q->where('region_id', $user->region_id);
        }
        if ($user->hasRole('tim_visitasi')) {
            return $q->whereIn('id', $user->kkDitugaskan());
        }
        if ($user->hasRole('koordinator_komisi')) {
            return $q->whereHas('members.commissions', fn ($c) => $c->whereIn('commissions.id', $user->komisiDikoordinasi()));
        }

        return $q->whereRaw('1 = 0');
    }
}
