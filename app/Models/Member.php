<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\DuplicateFinder;
use App\Support\HakAkses;
use App\Support\Opsi;
use App\Support\Telepon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class Member extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'marriage_date' => 'date',
            'death_date' => 'date',
            'joined_at' => 'date',
            'baptized_at_gkt' => 'boolean',
            'confirmed_at_gkt' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Member $m) {
            $m->code ??= static::nextCode();
        });

        static::saving(function (Member $m) {
            $m->name = trim(preg_replace('/\s+/u', ' ', (string) $m->name));
            $m->phone = Telepon::e164($m->phone);
            if (in_array($m->membership_status, ['keluar', 'meninggal'], true)) {
                $m->activity_status = null;
            }
        });

        // Jumlah kemungkinan duplikat (badge menu) dihitung ulang setelah data jemaat berubah.
        static::saved(fn (Member $m) => $m->wasChanged(['name', 'birth_date']) || $m->wasRecentlyCreated ? Cache::forget(DuplicateFinder::CACHE_JUMLAH) : null);
        static::deleted(fn () => Cache::forget(DuplicateFinder::CACHE_JUMLAH));
    }

    /** Kode jemaat otomatis SIBxxxx, melanjutkan nomor terakhir. */
    public static function nextCode(): string
    {
        $last = static::query()->where('code', 'like', 'SIB%')
            ->orderByRaw('CAST(SUBSTRING(code, 4) AS UNSIGNED) DESC')->value('code');
        $n = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'SIB'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    // ---- Relasi ----

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MemberEvent::class)->orderBy('date');
    }

    public function commissions(): BelongsToMany
    {
        return $this->belongsToMany(Commission::class, 'commission_members')
            ->withPivot(['joined_at', 'is_active'])->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /** Semua kunjungan ke KK jemaat ini (termasuk kunjungan khusus ke dirinya, karena family_id ikut terisi). */
    public function kunjunganKeluarga(): HasMany
    {
        return $this->hasMany(Visit::class, 'family_id', 'family_id');
    }

    /** Tugas pelayanan di jadwal ibadah. */
    public function orgPositions(): HasMany
    {
        return $this->hasMany(OrgPosition::class);
    }

    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    // ---- Atribut turunan (tidak disimpan) ----

    public function getAgeAttribute(): ?int
    {
        if (! $this->birth_date) {
            return null;
        }
        $sampai = $this->death_date ?? now();

        return (int) $this->birth_date->diffInYears($sampai);
    }

    public function getAgeGroupAttribute(): ?string
    {
        $umur = $this->age;
        if ($umur === null) {
            return null;
        }
        foreach (Opsi::RENTANG_UMUR as $k => [$min, $max]) {
            if ($umur >= $min && $umur <= $max) {
                return $k;
            }
        }

        return null;
    }

    public function getMembershipLabelAttribute(): string
    {
        return Opsi::label(Opsi::KEANGGOTAAN, $this->membership_status) ?? '-';
    }

    public function getRegionNameAttribute(): ?string
    {
        return $this->family?->region?->name;
    }

    /** Komisi yang diusulkan dari umur & kelamin (aturan di Master Komisi). */
    public function suggestedCommissions()
    {
        return Commission::query()->where('is_active', true)->get()
            ->filter(fn (Commission $c) => $c->matches($this));
    }

    // ---- Scope ----

    public function scopeTercatat(Builder $q): Builder
    {
        return $q->whereIn('membership_status', Opsi::MASIH_TERCATAT);
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->tercatat()->where('activity_status', 'aktif');
    }

    public function scopeAgeBetween(Builder $q, ?int $min, ?int $max): Builder
    {
        $today = Carbon::today();
        if ($min !== null) {
            $q->where('birth_date', '<=', $today->copy()->subYears($min));
        }
        if ($max !== null) {
            $q->where('birth_date', '>', $today->copy()->subYears($max + 1));
        }

        return $q;
    }

    public function scopeAgeGroup(Builder $q, string $group): Builder
    {
        [$min, $max] = Opsi::RENTANG_UMUR[$group];

        return $q->whereNotNull('birth_date')->ageBetween($min, $max);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
            ->orWhere('alias', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%"));
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->lihatSemuaJemaat() || HakAkses::lihatSemua($user, 'jemaat')) {
            return $q;
        }
        if ($user->hasRole('koordinator_wilayah') && $user->region_id) {
            return $q->whereHas('family', fn ($f) => $f->where('region_id', $user->region_id));
        }
        if ($user->hasRole('koordinator_komisi')) {
            return $q->whereHas('commissions', fn ($c) => $c->whereIn('commissions.id', $user->komisiDikoordinasi()));
        }
        if ($user->hasRole('tim_visitasi')) {
            return $q->whereIn('family_id', $user->kkDitugaskan());
        }

        return $q->whereRaw('1 = 0');
    }
}
