<?php

namespace App\Policies;

use App\Models\User;
use App\Models\OrgUnit;

class OrgUnitPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat();
    }

    public function view(User $u, OrgUnit $record): bool
    {
        return $u->lihatSemuaJemaat();
    }

    public function create(User $u): bool
    {
        return $u->kelolaJadwal();
    }

    public function update(User $u, OrgUnit $record): bool
    {
        return $u->kelolaJadwal();
    }

    public function delete(User $u, OrgUnit $record): bool
    {
        return $u->kelolaJadwal();
    }

    public function deleteAny(User $u): bool
    {
        return $u->kelolaJadwal();
    }
}
