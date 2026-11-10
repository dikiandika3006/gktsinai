<?php

namespace App\Models;

use App\Support\Opsi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu petugas pada satu tanggal / kebaktian / peran. */
class ServiceAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_keluarga' => 'boolean'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorshipSchedule::class, 'worship_schedule_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function alias(): BelongsTo
    {
        return $this->belongsTo(ServantAlias::class, 'kunci', 'key');
    }

    public function getPeranLabelAttribute(): string
    {
        return Opsi::label(Opsi::PERAN_PELAYANAN, $this->peran);
    }
}
