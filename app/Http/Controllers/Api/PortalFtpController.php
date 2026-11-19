<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientChannel;
use App\Models\ClientUser;
use App\Models\User;
use App\Notifications\ClientStoryNotification;
use App\Notifications\StoryNotification;
use App\Repositories\AuditLogRepository;
use App\Services\Delivery\FtpDiskFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * COS-FTP-MGMT: the client's admin portal user manages the client's single
 * FTP channel (add/edit/pause/resume/remove + connection test). Password is
 * write-only and stored encrypted, exactly like the staff-side editor.
 */
class PortalFtpController extends Controller
{
    private const EDITABLE = ['host', 'port', 'username', 'protocol', 'password'];

    public function __construct(private AuditLogRepository $audit) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('clientUser');
        $this->authorizeManager($user);

        $channel = $this->ftpChannel($user->client_id);

        return response()->json([
            'channel' => $channel ? $this->present($channel) : null,
            'can_manage' => true,
        ]);
    }

    public function update(Request $request, FtpDiskFactory $factory): JsonResponse
    {
        $user = $request->attributes->get('clientUser');
        $this->authorizeManager($user);

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:1', 'max:190'],
            'protocol' => ['required', Rule::in(['ftp', 'ftps', 'sftp'])],
        ]);

        $channel = $this->ftpChannel($user->client_id);
        $config = $channel?->config ?? [];

        $config['host'] = $validated['host'];
        $config['port'] = $validated['port'];
        $config['username'] = $validated['username'];
        $config['auth_type'] = $validated['protocol'];
        if ($validated['protocol'] === 'ftps') {
            $config['ssl'] = true;
        } else {
            unset($config['ssl']);
        }
        if (! empty($validated['password'])) {
            $config['password'] = Crypt::encryptString($validated['password']);
        }
        $config['health'] = 'unknown';

        if ($channel) {
            $channel->update(['config' => $config, 'status' => 'active']);
            $this->auditChange($user, 'client.ftp.updated', $channel, 'updated');
        } else {
            $channel = ClientChannel::create([
                'client_id' => $user->client_id,
                'type' => 'ftp',
                'config' => $config,
                'status' => 'active',
                'failure_count' => 0,
            ]);
            $this->auditChange($user, 'client.ftp.added', $channel, 'added');
        }

        $this->notifyStaff($user, $channel->wasRecentlyCreated ? 'added' : 'updated');

        return response()->json([
            'channel' => $this->present($channel->fresh()),
            'message' => 'FTP settings saved',
        ]);
    }

    public function test(Request $request, FtpDiskFactory $factory): JsonResponse
    {
        $user = $request->attributes->get('clientUser');
        $this->authorizeManager($user);

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:190'],
            'protocol' => ['required', Rule::in(['ftp', 'ftps', 'sftp'])],
        ]);

        // Test a candidate config without saving — wrong credentials never go
        // live unnoticed. Password omitted = the stored one (masked edit).
        $config = [
            'host' => $validated['host'],
            'port' => $validated['port'],
            'username' => $validated['username'],
            'auth_type' => $validated['protocol'],
            'timeout' => 5,
        ];
        if ($validated['protocol'] === 'ftps') {
            $config['ssl'] = true;
        }
        if (! empty($validated['password'])) {
            $config['password'] = Crypt::encryptString($validated['password']);
        } else {
            $stored = $this->ftpChannel($user->client_id)?->config;
            if (is_array($stored) && ! empty($stored['password'])) {
                $config['password'] = $stored['password'];
            }
        }

        $probe = new ClientChannel;
        $probe->type = 'ftp';
        $probe->config = $config;

        try {
            $disk = $factory->make($probe);
            $name = '.unb-client-test-'.time();
            $disk->write($name, 'unb connectivity probe');
            $disk->delete($name);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Connection failed: '.substr($e->getMessage(), 0, 200),
            ], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Connection successful']);
    }

    public function pause(Request $request): JsonResponse
    {
        return $this->setStatus($request, 'paused');
    }

    public function resume(Request $request): JsonResponse
    {
        return $this->setStatus($request, 'active');
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->attributes->get('clientUser');
        $this->authorizeManager($user);

        $channel = $this->ftpChannel($user->client_id);
        if (! $channel) {
            return response()->json(['message' => 'No FTP channel configured'], 404);
        }

        // deliveries.channel_id is FK RESTRICT — a channel with history cannot
        // be hard-deleted. "Remove" = disable + wipe credentials; a fresh,
        // never-used channel is deleted outright.
        $used = DBChannelUsed($channel->id);
        if ($used) {
            $channel->update([
                'status' => 'disabled',
                'config' => ['health' => 'removed'],
                'failure_count' => 0,
            ]);
            $this->auditChange($user, 'client.ftp.removed', $channel, 'removed');
        } else {
            $this->auditChange($user, 'client.ftp.removed', null, 'removed');
            $channel->delete();
            $channel = null;
        }

        $this->notifyStaff($user, 'removed');

        return response()->json([
            'channel' => $channel ? $this->present($channel) : null,
            'message' => 'FTP channel removed',
        ]);
    }

    private function setStatus(Request $request, string $status): JsonResponse
    {
        $user = $request->attributes->get('clientUser');
        $this->authorizeManager($user);

        $channel = $this->ftpChannel($user->client_id);
        if (! $channel) {
            return response()->json(['message' => 'No FTP channel configured'], 404);
        }

        $channel->update(['status' => $status]);
        $action = $status === 'paused' ? 'paused' : 'resumed';
        $this->auditChange($user, "client.ftp.{$action}", $channel, $action);
        $this->notifyStaff($user, $action);

        // The client's portal users must know delivery is paused/resumed.
        try {
            $users = ClientUser::where('client_id', $user->client_id)->where('status', 'active')->get();
            if ($users->isNotEmpty()) {
                Notification::sendNow($users, new ClientStoryNotification("story.ftp_{$action}", [
                    'title' => 'FTP channel '.$action,
                    'message' => $status === 'paused'
                        ? 'Your FTP channel has been paused — stories will not be delivered via FTP until it is resumed. The portal always has the latest stories.'
                        : 'Your FTP channel has been resumed — FTP delivery is active again.',
                    'public_id' => null,
                    'category' => null,
                    'urgency' => 'regular',
                ]));
            }
        } catch (\Throwable $e) {
            // Notification failure must not break the state change.
        }

        return response()->json([
            'channel' => $this->present($channel),
            'message' => 'FTP channel '.$action,
        ]);
    }

    private function ftpChannel(int $clientId): ?ClientChannel
    {
        return ClientChannel::where('client_id', $clientId)->where('type', 'ftp')->first();
    }

    private function authorizeManager(?ClientUser $user): void
    {
        if (! $user || ! $user->can_manage_delivery) {
            abort(403, 'Only the client admin user can manage FTP delivery');
        }
    }

    private function present(ClientChannel $channel): array
    {
        $config = $channel->config ?? [];

        return [
            'id' => $channel->id,
            'host' => $config['host'] ?? null,
            'port' => (int) ($config['port'] ?? 21),
            'username' => $config['username'] ?? null,
            'protocol' => $config['auth_type'] ?? 'ftp',
            'has_password' => ! empty($config['password']),
            'status' => $channel->status,
            'failure_count' => (int) $channel->failure_count,
            'last_success_at' => $channel->last_success_at?->toIso8601String(),
        ];
    }

    private function auditChange(ClientUser $user, string $action, ?ClientChannel $channel, string $verb): void
    {
        $this->audit->log(
            $action,
            'client_channel',
            $channel?->id,
            ['message' => $user->name.' ('.$user->email.') '.$verb.' the FTP channel for '.$user->client->name],
            actorType: 'client_user',
            actorId: $user->id,
        );
    }

    private function notifyStaff(ClientUser $user, string $verb): void
    {
        try {
            $staff = User::whereHas('role.permissions', fn ($q) => $q
                ->where('module', 'clients')->where('can_edit', true))
                ->where('status', 'active')
                ->get();
            if ($staff->isEmpty()) {
                return;
            }

            Notification::sendNow($staff, new StoryNotification(
                'client.ftp_'.$verb,
                ['message' => $user->name.' ('.$user->client->name.') '.$verb.' the client FTP channel'],
            ));
        } catch (\Throwable $e) {
            // Staff notification failure must not break the client action.
        }
    }
}

function DBChannelUsed(int $channelId): bool
{
    return DB::table('deliveries')->where('channel_id', $channelId)->exists();
}
