<?php

namespace App\Policies;

use App\Models\ActivityType;
use App\Models\User;

class ActivityTypePolicy
{
    public function viewAny(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function view(User $u, ActivityType $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function create(User $u): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function update(User $u, ActivityType $record): bool
    {
        return $u->peran('super_admin', 'sekretariat');
    }

    public function delete(User $u, ActivityType $record): bool
    {
        return $u->peran('super_admin');
    }

    public function deleteAny(User $u): bool
    {
        return $u->peran('super_admin');
    }
}
