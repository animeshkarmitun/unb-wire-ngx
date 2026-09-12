<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PortalProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('clientUser');
        $client = $request->attributes->get('client');

        $initials = collect(explode(' ', $client->name))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)
            ->implode('');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'client_role' => [
                'name' => $user->clientRole?->name,
            ],
            'client' => [
                'name' => $client->name,
                'initials' => $initials,
                'status' => $client->status,
            ],
            'last_login_at' => $user->last_login_at?->toISOString(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->attributes->get('clientUser');
        $client = $request->attributes->get('client');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', Rule::unique('client_users')->ignore($user->id)],
        ]);

        $user->update($validated);

        $initials = collect(explode(' ', $client->name))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)
            ->implode('');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'client_role' => [
                'name' => $user->clientRole?->name,
            ],
            'client' => [
                'name' => $client->name,
                'initials' => $initials,
                'status' => $client->status,
            ],
            'last_login_at' => $user->last_login_at?->toISOString(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->attributes->get('clientUser');

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Password updated']);
    }
}
