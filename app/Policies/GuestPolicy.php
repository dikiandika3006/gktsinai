<?php

namespace App\Policies;

use App\Models\Guest;
use App\Models\User;

class GuestPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat() || $u->peran('koordinator_wilayah', 'koordinator_komisi', 'penyambut');
    }

    public function view(User $u, Guest $record): bool
    {
        return $u->lihatSemuaJemaat() || $u->peran('koordinator_wilayah', 'koordinator_komisi', 'penyambut');
    }

    public function create(User $u): bool
    {
        return $u->kelolaTamu() || $u->peran('penyambut');
    }

    public function update(User $u, Guest $record): bool
    {
        return $u->kelolaTamu() || $u->peran('pendeta', 'koordinator_wilayah', 'koordinator_komisi');
    }

    public function delete(User $u, Guest $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function deleteAny(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }
}
