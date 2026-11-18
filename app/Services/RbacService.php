<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RbacService
{
    public function can(User $user, string $module, string $action): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

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

    /**
     * Stories are split into two RBAC modules by language ('stories' / 'stories_bn').
     * Every language-aware permission check must resolve the module through this.
     */
    public function storiesModuleFor(string $language): string
    {
        return $language === 'bn' ? 'stories_bn' : 'stories';
    }
}
