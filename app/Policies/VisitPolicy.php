<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat', 'pendeta', 'majelis', 'koordinator_wilayah', 'tim_visitasi');
    }

    public function view(User $u, Visit $record): bool
    {
        return $this->viewAny($u) && Visit::query()->visibleTo($u)->whereKey($record->getKey())->exists();
    }

    public function create(User $u): bool
    {
        return $u->kelolaVisitasi() || $u->peran('koordinator_wilayah');
    }

    public function update(User $u, Visit $record): bool
    {
        return ($u->kelolaVisitasi() || $u->peran('koordinator_wilayah')) && $this->view($u, $record);
    }

    /** Isi laporan kunjungan: tim yang ditugaskan, pendeta, super admin. */
    public function report(User $u, Visit $record): bool
    {
        return $u->kelolaVisitasi() || $record->team()->whereKey($u->id)->exists();
    }

    public function delete(User $u, Visit $record): bool
    {
        return $u->kelolaVisitasi();
    }

    public function deleteAny(User $u): bool
    {
        return $u->kelolaVisitasi();
    }
}
