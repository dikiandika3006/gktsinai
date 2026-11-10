<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat setiap perubahan data (siapa, kapan, nilai lama/baru) ke audit_logs.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => static::writeAudit($m, 'dibuat', [], $m->getAttributes()));

        static::updated(function (Model $m) {
            $baru = collect($m->getChanges())->except(['updated_at'])->all();
            if ($baru === []) {
                return;
            }
            $lama = collect($m->getOriginal())->only(array_keys($baru))->all();
            static::writeAudit($m, 'diubah', $lama, $baru);
        });

        static::deleted(fn (Model $m) => static::writeAudit($m, 'dihapus', $m->getOriginal(), []));
    }

    protected static function writeAudit(Model $m, string $event, array $lama, array $baru): void
    {
        $bersih = fn (array $a) => collect($a)->except(['created_at', 'updated_at'])
            ->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v)->all();

        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => $m->getMorphClass(),
            'auditable_id' => $m->getKey(),
            'event' => $event,
            'old_values' => $bersih($lama) ?: null,
            'new_values' => $bersih($baru) ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
