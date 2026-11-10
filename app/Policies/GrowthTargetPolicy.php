<?php

namespace App\Policies;

use App\Models\GrowthTarget;
use App\Models\User;

class GrowthTargetPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat();
    }

    public function view(User $u, GrowthTarget $record): bool
    {
        return $u->lihatSemuaJemaat();
    }

    public function create(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function update(User $u, GrowthTarget $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function delete(User $u, GrowthTarget $record): bool
    {
        return $u->peran('super_admin');
    }

    public function deleteAny(User $u): bool
    {
        return $u->peran('super_admin');
    }
}
