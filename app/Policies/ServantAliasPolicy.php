<?php

namespace App\Policies;

use App\Models\ServantAlias;
use App\Models\User;

class ServantAliasPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat();
    }

    public function view(User $u, ServantAlias $record): bool
    {
        return $u->lihatSemuaJemaat();
    }

    public function create(User $u): bool
    {
        return $u->kelolaJadwal();
    }

    public function update(User $u, ServantAlias $record): bool
    {
        return $u->kelolaJadwal();
    }

    public function delete(User $u, ServantAlias $record): bool
    {
        return $u->kelolaJadwal();
    }

    public function deleteAny(User $u): bool
    {
        return $u->kelolaJadwal();
    }
}
