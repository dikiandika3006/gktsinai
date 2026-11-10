<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat() || $u->peran('koordinator_wilayah', 'koordinator_komisi', 'tim_visitasi');
    }

    public function view(User $u, Member $record): bool
    {
        return ($u->lihatSemuaJemaat() || $u->peran('koordinator_wilayah', 'koordinator_komisi', 'tim_visitasi')) && Member::query()->visibleTo($u)->whereKey($record->getKey())->exists();
    }

    public function create(User $u): bool
    {
        return $u->kelolaJemaat();
    }

    public function update(User $u, Member $record): bool
    {
        return $u->kelolaJemaat() && Member::query()->visibleTo($u)->whereKey($record->getKey())->exists();
    }

    public function delete(User $u, Member $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function deleteAny(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }
}
