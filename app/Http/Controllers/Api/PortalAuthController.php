<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendPortalResetPassword;
use App\Models\ClientUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PortalAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        $user = ClientUser::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active'], 403);
        }

        $token = $user->createToken('portal')->plainTextToken;
        $user->update(['last_login_at' => now()]);

        $client = $user->client;
        $initials = collect(explode(' ', $client->name))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)
            ->implode('');

        return response()->json([
            'token' => $token,
            'client_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'client_role_id' => $user->client_role_id,
                'client' => [
                    'name' => $client->name,
                    'initials' => $initials,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = ClientUser::where('email', $validated['email'])->first();

        if ($user) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $token = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            Mail::to($user->email)->queue(new SendPortalResetPassword($user->email, $token));
        }

        return response()->json(['message' => 'If the email exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (! $record || ! Hash::check($validated['token'], $record->token)) {
            return response()->json(['message' => 'Invalid or expired reset token'], 422);
        }

        $createdAt = Carbon::parse($record->created_at);

        if ($createdAt->diffInMinutes(now()) > 60) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            return response()->json(['message' => 'Reset token has expired'], 422);
        }

        ClientUser::where('email', $validated['email'])->update([
            'password' => Hash::make($validated['password']),
        ]);

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json(['message' => 'Password reset successful']);
    }
}
