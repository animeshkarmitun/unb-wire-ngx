<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $editorRole = Role::where('name', 'Editor')->first();
        $stratRole = Role::where('name', 'Strategist')->first();
        $reportRole = Role::where('name', 'Admin Report')->first();
        $bizRole = Role::where('name', 'Business Team')->first();
        $upBnRole = Role::where('name', 'Uploader-Bangla')->first();
        $upEnRole = Role::where('name', 'Uploader-English')->first();

        // 1. Ensure test user exists for testing
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
                'desk' => 'Management',
                'status' => 'active',
                'timezone' => 'Asia/Dhaka',
                'last_seen_at' => now(),
            ]
        );

        // 2. Prototype Team Members from app-data/roles.html
        $members = [
            [
                'name' => 'Nahar Khan',
                'email' => 'nahar@unbnews.org',
                'desk' => 'Chief Editor',
                'role_id' => $adminRole?->id,
                'status' => 'active',
                'last_seen_at' => now(),
            ],
            [
                'name' => 'Shohel Ahmed',
                'email' => 'shohel@unbnews.org',
                'desk' => 'English desk',
                'role_id' => $editorRole?->id,
                'status' => 'active',
                'last_seen_at' => now()->subMinutes(12),
            ],
            [
                'name' => 'Ruma Islam',
                'email' => 'ruma@unbnews.org',
                'desk' => 'Bangla desk',
                'role_id' => $editorRole?->id,
                'status' => 'active',
                'last_seen_at' => now()->subHour(),
            ],
            [
                'name' => 'Maria Mimi',
                'email' => 'maria@unbnews.org',
                'desk' => 'English desk',
                'role_id' => $upEnRole?->id,
                'status' => 'active',
                'last_seen_at' => now()->subMinutes(26),
            ],
            [
                'name' => 'Tanvir Hasan',
                'email' => 'tanvir@unbnews.org',
                'desk' => 'Bangla desk',
                'role_id' => $upBnRole?->id,
                'status' => 'invited',
                'last_seen_at' => null,
            ],
            [
                'name' => 'Arif Jahan',
                'email' => 'arif@unbnews.org',
                'desk' => 'Business',
                'role_id' => $bizRole?->id,
                'status' => 'active',
                'last_seen_at' => now()->subDay(),
            ],
            [
                'name' => 'Khadija Parvin',
                'email' => 'khadija@unbnews.org',
                'desk' => 'Management',
                'role_id' => $stratRole?->id,
                'status' => 'invited',
                'last_seen_at' => null,
            ],
            [
                'name' => 'Sultana Razia',
                'email' => 'sultana@unbnews.org',
                'desk' => 'Management',
                'role_id' => $reportRole?->id,
                'status' => 'deactivated',
                'last_seen_at' => now()->subDays(15),
            ],
        ];

        foreach ($members as $m) {
            User::firstOrCreate(
                ['email' => $m['email']],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => $m['name'],
                    'password' => Hash::make('password'),
                    'role_id' => $m['role_id'],
                    'desk' => $m['desk'],
                    'status' => $m['status'],
                    'timezone' => 'Asia/Dhaka',
                    'last_seen_at' => $m['last_seen_at'],
                ]
            );
        }

        // 3. Initial Audit Logs from app-data/roles.html (if table empty)
        if (DB::table('audit_logs')->count() === 0) {
            $nahar = User::where('email', 'nahar@unbnews.org')->first();
            $audits = [
                [
                    'actor_type' => 'user',
                    'actor_id' => $nahar?->id,
                    'action' => 'role.updated',
                    'entity_type' => 'role',
                    'entity_id' => $editorRole?->id,
                    'diff' => json_encode([
                        'message' => '<b>Nahar Khan</b> edited role <b>Editor</b> — added <b>Publish</b> on Bangla News',
                        'color' => 'var(--crimson, #e5484d)',
                    ]),
                    'created_at' => now()->subHours(2),
                ],
                [
                    'actor_type' => 'user',
                    'actor_id' => $nahar?->id,
                    'action' => 'user.invited',
                    'entity_type' => 'user',
                    'entity_id' => null,
                    'diff' => json_encode([
                        'message' => '<b>Khadija Parvin</b> invited as <b>Strategist</b>',
                        'color' => 'var(--blue, #3b6fe0)',
                    ]),
                    'created_at' => now()->subDay()->setTime(18, 12),
                ],
                [
                    'actor_type' => 'user',
                    'actor_id' => $nahar?->id,
                    'action' => 'role.created',
                    'entity_type' => 'role',
                    'entity_id' => $bizRole?->id,
                    'diff' => json_encode([
                        'message' => 'Role <b>Business Team</b> created — copied from <b>Strategist</b>',
                        'color' => 'var(--green, #16a34a)',
                    ]),
                    'created_at' => now()->subDays(2)->setTime(11, 40),
                ],
                [
                    'actor_type' => 'user',
                    'actor_id' => $nahar?->id,
                    'action' => 'user.deactivated',
                    'entity_type' => 'user',
                    'entity_id' => null,
                    'diff' => json_encode([
                        'message' => '<b>Sultana Razia</b> deactivated — access revoked',
                        'color' => '#b7791f',
                    ]),
                    'created_at' => now()->subDays(3)->setTime(16, 3),
                ],
                [
                    'actor_type' => 'user',
                    'actor_id' => $nahar?->id,
                    'action' => 'role.updated',
                    'entity_type' => 'role',
                    'entity_id' => $reportRole?->id,
                    'diff' => json_encode([
                        'message' => 'Role <b>Admin Report</b> granted <b>Billing</b> on Clients &amp; distribution',
                        'color' => 'var(--blue, #3b6fe0)',
                    ]),
                    'created_at' => now()->subDays(4)->setTime(10, 15),
                ],
            ];

            foreach ($audits as $audit) {
                DB::table('audit_logs')->insert($audit);
            }
        }
    }
}
