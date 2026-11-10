<?php

namespace App\Models;

use App\Support\Telepon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'consent_contact' => 'boolean',
            'consent_at' => 'datetime',
            'fu_due_at' => 'datetime',
            'first_visit_at' => 'date',
            'converted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Guest $g) {
            $g->first_visit_at ??= now()->toDateString();
            $g->fu_due_at ??= now()->addHours((int) config('sij.fu_deadline_hours', 48));
            if ($g->consent_contact) {
                $g->consent_at ??= now();
            }
        });
        static::saving(fn (Guest $g) => $g->phone = Telepon::e164($g->phone));
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function convertedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'converted_member_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(GuestFollowup::class)->latest('date');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Jumlah kunjungan = kunjungan pertama + check-in pada hari berbeda. */
    public function getVisitCountAttribute(): int
    {
        $tanggal = $this->attendances()->with('session')->get()
            ->map(fn ($a) => $a->session?->date?->toDateString())
            ->push($this->first_visit_at?->toDateString())
            ->filter()->unique();

        return $tanggal->count();
    }

    public function refreshStageFromVisits(): void
    {
        if (in_array($this->fu_stage, ['jemaat', 'tidak_lanjut'], true)) {
            return;
        }
        $n = $this->visit_count;
        $stage = $this->fu_stage;
        if ($n >= 3) {
            $stage = 'rutin';
        } elseif ($n >= 2 && in_array($stage, ['baru', 'dihubungi'], true)) {
            $stage = 'datang_lagi';
        }
        if ($stage !== $this->fu_stage) {
            $this->update(['fu_stage' => $stage]);
        }
    }

    public function isOverdue(): bool
    {
        return $this->fu_stage === 'baru' && $this->fu_due_at && $this->fu_due_at->isPast();
    }
}
