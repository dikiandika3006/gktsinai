<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['planned_date' => 'date', 'realized_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Visit $v) => $v->created_by ??= auth()->id());
        static::saving(function (Visit $v) {
            if ($v->member_id && ! $v->family_id) {
                $v->family_id = Member::find($v->member_id)?->family_id;
            }
        });
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'visit_user');
    }

    public function report(): HasOne
    {
        return $this->hasOne(VisitReport::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTargetNameAttribute(): string
    {
        if ($this->member) {
            return $this->member->name;
        }

        return $this->family?->display_name ?? '-';
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->peran('super_admin', 'sekretariat', 'pendeta', 'majelis')) {
            return $q;
        }
        if ($user->hasRole('koordinator_wilayah') && $user->region_id) {
            return $q->whereHas('family', fn ($f) => $f->where('region_id', $user->region_id));
        }
        if ($user->hasRole('tim_visitasi')) {
            return $q->whereHas('team', fn ($t) => $t->whereKey($user->id));
        }

        return $q->whereRaw('1 = 0');
    }
}
