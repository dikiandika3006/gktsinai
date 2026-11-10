<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestFollowup extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (GuestFollowup $f) => $f->user_id ??= auth()->id());
        static::created(function (GuestFollowup $f) {
            if ($f->guest && $f->guest->fu_stage === 'baru') {
                $f->guest->update(['fu_stage' => 'dihubungi']);
            }
        });
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
