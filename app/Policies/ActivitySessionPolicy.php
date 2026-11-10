<?php

namespace App\Policies;

use App\Models\ActivitySession;
use App\Models\User;

class ActivitySessionPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->lihatSemuaJemaat() || $u->kelolaKehadiran();
    }

    public function view(User $u, ActivitySession $record): bool
    {
        return $u->lihatSemuaJemaat() || $u->kelolaKehadiran();
    }

    public function create(User $u): bool
    {
        return $u->kelolaKehadiran() && ! $u->peran('penyambut') || $u->peran('super_admin', 'sekretariat');
    }

    public function update(User $u, ActivitySession $record): bool
    {
        return $u->kelolaKehadiran() && ! $u->peran('penyambut') || $u->peran('super_admin', 'sekretariat');
    }

    public function delete(User $u, ActivitySession $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function deleteAny(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }
}
