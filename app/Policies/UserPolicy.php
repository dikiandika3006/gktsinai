<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->isSuperAdmin();
    }

    public function view(User $u, User $record): bool
    {
        return $u->isSuperAdmin();
    }

    public function create(User $u): bool
    {
        return $u->isSuperAdmin();
    }

    public function update(User $u, User $record): bool
    {
        return $u->isSuperAdmin();
    }

    public function delete(User $u, User $record): bool
    {
        return $u->isSuperAdmin();
    }

    public function deleteAny(User $u): bool
    {
        return $u->isSuperAdmin();
    }
}
