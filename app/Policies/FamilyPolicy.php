<?php

namespace App\Policies;

use App\Models\Family;
use App\Models\User;

class FamilyPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat() || $u->peran('koordinator_wilayah', 'koordinator_komisi', 'tim_visitasi');
    }

    public function view(User $u, Family $record): bool
    {
        return ($u->lihatSemuaJemaat() || $u->peran('koordinator_wilayah', 'koordinator_komisi', 'tim_visitasi')) && Family::query()->visibleTo($u)->whereKey($record->getKey())->exists();
    }

    public function create(User $u): bool
    {
        return $u->kelolaJemaat();
    }

    public function update(User $u, Family $record): bool
    {
        return $u->kelolaJemaat() && Family::query()->visibleTo($u)->whereKey($record->getKey())->exists();
    }

    public function delete(User $u, Family $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function deleteAny(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }
}
