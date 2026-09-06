<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RbacService
{
    public function can(User $user, string $module, string $action): bool
    {
        $perm = $user->role?->permissions()->where('module', $module)->first();
        if (! $perm) {
            return false;
        }
        $col = 'can_'.$action;

        return (bool) ($perm->$col ?? false);
    }

    public function assertCan(User $user, string $module, string $action): void
    {
        if (! $this->can($user, $module, $action)) {
            throw new AuthorizationException("Forbidden: need {$action} on {$module}");
        }
    }
}
