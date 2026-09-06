<?php

namespace App\Http\Middleware;

use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;

class EnsureRbac
{
    public function __construct(private RbacService $rbac) {}

    public function handle(Request $request, Closure $next, string $module, string $action = 'view')
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $this->rbac->assertCan($user, $module, $action);

        return $next($request);
    }
}
