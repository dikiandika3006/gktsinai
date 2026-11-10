<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Periode kepengurusan struktur organisasi. */
class OrgPeriod extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Hanya satu periode aktif.
        static::saved(function (OrgPeriod $p) {
            if ($p->is_active) {
                static::where('id', '!=', $p->id)->where('is_active', true)->update(['is_active' => false]);
            }
        });
    }

    public function units(): HasMany
    {
        return $this->hasMany(OrgUnit::class)->orderBy('sort');
    }

    public static function aktif(): ?self
    {
        return static::where('is_active', true)->first() ?? static::orderByDesc('start_date')->first();
    }
}
