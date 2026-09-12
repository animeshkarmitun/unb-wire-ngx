<?php

namespace App\Http\Middleware;

use App\Models\ClientUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = $accessToken->tokenable;

        if (! $user || ! $user instanceof ClientUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user->withAccessToken($accessToken);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('clientUser', $user);
        $request->attributes->set('client', $user->client);

        return $next($request);
    }
}
