<?php

namespace App\Policies;

use App\Models\Region;
use App\Models\User;

class RegionPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat();
    }

    public function view(User $u, Region $record): bool
    {
        return $u->lihatSemuaJemaat();
    }

    public function create(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function update(User $u, Region $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function delete(User $u, Region $record): bool
    {
        return $u->peran('super_admin');
    }

    public function deleteAny(User $u): bool
    {
        return $u->peran('super_admin');
    }
}
