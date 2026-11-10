<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_confidential' => 'boolean', 'attendee_member_ids' => 'array'];
    }

    /** Nama anggota KK yang dicentang hadir + teks "yang hadir lainnya". */
    public function getDaftarHadirAttribute(): ?string
    {
        $nama = Member::whereIn('id', $this->attendee_member_ids ?? [])->orderBy('name')->pluck('name');
        $semua = $nama->when($this->attendees, fn ($c) => $c->push($this->attendees));

        return $semua->isEmpty() ? null : $semua->join(', ');
    }

    protected static function booted(): void
    {
        static::creating(fn (VisitReport $r) => $r->user_id ??= auth()->id());
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Catatan rahasia hanya terbaca oleh pendeta dan penulisnya. Majelis tidak melihat catatan pastoral. */
    public function canBeReadBy(User $user): bool
    {
        if ($this->is_confidential) {
            return $user->bacaCatatanRahasia() || $this->user_id === $user->id;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        return $user->peran('pendeta', 'super_admin', 'sekretariat', 'tim_visitasi', 'koordinator_wilayah');
    }
}
