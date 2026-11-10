<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Opsi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu orang dengan jabatannya di satu unit. */
class OrgPosition extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** Nama tampilan: nama seperti ditulis (dengan gelar), atau nama jemaat. */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->member?->name ?? '-');
    }

    public function getRoleLabelAttribute(): string
    {
        return Opsi::label(Opsi::JABATAN, $this->role);
    }
}
