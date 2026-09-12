<?php

namespace App\Services;

use App\Mail\SendPortalInvite;
use App\Models\Client;
use App\Models\ClientUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PortalAccountService
{
    public function invite(Client $client, array $data): ClientUser
    {
        $plainPassword = Str::password(16);

        $user = ClientUser::create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $plainPassword,
            'client_role_id' => $data['client_role_id'] ?? null,
            'status' => 'invited',
        ]);

        Mail::to($user->email)->queue(new SendPortalInvite($user, $plainPassword));

        $this->logAudit('portal_user.invited', 'client_user', $user->id, [
            'message' => '<b>'.e($user->name).'</b> invited to portal for <b>'.e($client->name).'</b>',
            'color' => 'var(--green, #16a34a)',
        ]);

        return $user;
    }

    public function deactivate(ClientUser $user): ClientUser
    {
        $user->update(['status' => 'deactivated']);
        $user->tokens()->delete();

        $this->logAudit('portal_user.deactivated', 'client_user', $user->id, [
            'message' => '<b>'.e($user->name).'</b> deactivated — portal access revoked',
            'color' => '#b7791f',
        ]);

        return $user;
    }

    public function reactivate(ClientUser $user): ClientUser
    {
        $user->update(['status' => 'active']);

        $this->logAudit('portal_user.activated', 'client_user', $user->id, [
            'message' => '<b>'.e($user->name).'</b> reactivated — portal access restored',
            'color' => 'var(--green, #16a34a)',
        ]);

        return $user;
    }

    public function resendInvite(ClientUser $user): void
    {
        $plainPassword = Str::password(16);
        $user->update(['password' => $plainPassword]);

        Mail::to($user->email)->queue(new SendPortalInvite($user, $plainPassword));

        $this->logAudit('portal_user.invite_resent', 'client_user', $user->id, [
            'message' => 'Invite resent to <b>'.e($user->name).'</b>',
            'color' => 'var(--blue, #3b6fe0)',
        ]);
    }

    public function updateRole(ClientUser $user, int $roleId): ClientUser
    {
        $user->update(['client_role_id' => $roleId]);

        $this->logAudit('portal_user.role_changed', 'client_user', $user->id, [
            'message' => '<b>'.e($user->name).'</b> role updated',
            'color' => 'var(--blue, #3b6fe0)',
        ]);

        return $user;
    }

    private function logAudit(string $action, string $entityType, int $entityId, array $diff): void
    {
        DB::table('audit_logs')->insert([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'diff' => json_encode($diff),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'correlation_id' => request()?->header('X-Correlation-Id') ?? (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
