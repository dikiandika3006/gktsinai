<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->isSuperAdmin();
    }

    public function view(User $u, AuditLog $record): bool
    {
        return $u->isSuperAdmin();
    }

    public function create(User $u): bool
    {
        return false;
    }

    public function update(User $u, AuditLog $record): bool
    {
        return false;
    }

    public function delete(User $u, AuditLog $record): bool
    {
        return false;
    }

    public function deleteAny(User $u): bool
    {
        return false;
    }
}
