<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Opsi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Unit di struktur organisasi: pimpinan, sekretariat, bidang, sub-bidang, komisi, tim. */
class OrgUnit extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(OrgPeriod::class, 'org_period_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'parent_id')->orderBy('sort');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(OrgPosition::class)->orderBy('sort');
    }

    public function getTypeLabelAttribute(): string
    {
        return Opsi::label(Opsi::JENIS_UNIT, $this->type);
    }
}
