<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'count_only' => 'boolean'];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'commission_members')
            ->withPivot(['joined_at', 'is_active'])->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('is_active', true);
    }

    public function activityTypes(): HasMany
    {
        return $this->hasMany(ActivityType::class);
    }

    /** Aturan usulan: rentang umur & kelamin. */
    public function matches(Member $m): bool
    {
        if (! in_array($m->membership_status, ['sidi', 'baptis', 'anggota', 'simpatisan'], true)) {
            return false;
        }
        if ($this->gender && $m->gender !== $this->gender) {
            return false;
        }
        $umur = $m->age;
        if (($this->min_age !== null || $this->max_age !== null) && $umur === null) {
            return false;
        }
        if ($this->min_age !== null && $umur < $this->min_age) {
            return false;
        }
        if ($this->max_age !== null && $umur > $this->max_age) {
            return false;
        }

        return true;
    }
}
