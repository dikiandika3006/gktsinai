<?php

namespace App\Policies;

use App\Models\Commission;
use App\Models\User;

class CommissionPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat() || $u->peran('koordinator_komisi');
    }

    public function view(User $u, Commission $record): bool
    {
        return $u->lihatSemuaJemaat() || $u->peran('koordinator_komisi');
    }

    public function create(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function update(User $u, Commission $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function delete(User $u, Commission $record): bool
    {
        return $u->peran('super_admin');
    }

    public function deleteAny(User $u): bool
    {
        return $u->peran('super_admin');
    }
}
