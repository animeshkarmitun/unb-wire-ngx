<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'type' => 'system', 'description' => 'Full control over every module, users and settings', 'is_locked' => true],
            ['name' => 'Editor', 'type' => 'custom', 'description' => 'Runs the desk — create, edit and publish in both languages', 'is_locked' => false],
            ['name' => 'Strategist', 'type' => 'custom', 'description' => 'Plans coverage and reviews performance — no publishing', 'is_locked' => false],
            ['name' => 'Admin Report', 'type' => 'custom', 'description' => 'Read-only access across modules for management reports', 'is_locked' => false],
            ['name' => 'Business Team', 'type' => 'custom', 'description' => 'Manages clients, packages and billing — no newsroom access', 'is_locked' => false],
            ['name' => 'Client Bangla (Without AP)', 'type' => 'client', 'description' => 'Client portal login — Bangla service and photos, no AP access', 'is_locked' => false],
            ['name' => 'Uploader-Bangla', 'type' => 'custom', 'description' => 'Bangla desk uploader — drafts only, cannot publish', 'is_locked' => false],
            ['name' => 'Uploader-English', 'type' => 'custom', 'description' => 'English desk uploader — drafts only, cannot publish', 'is_locked' => false],
        ];

        $modules = ['stories', 'stories_bn', 'media', 'clients', 'packages', 'distribution', 'settings', 'ai', 'history', 'audit'];

        $perms = [
            'Admin' => [
                'stories' => [1, 1, 1, 1, 1], 'stories_bn' => [1, 1, 1, 1, 1], 'media' => [1, 1, 1, 1, 1], 'clients' => [1, 1, 1, 1, 1], 'packages' => [1, 1, 1, 1, 1], 'distribution' => [1, 1, 1, 1, 1], 'settings' => [1, 1, 1, 1, 1], 'ai' => [1, 1, 1, 1, 1], 'history' => [1, 0, 0, 0, 0], 'audit' => [1, 0, 0, 0, 0],
            ],
            'Editor' => [
                'stories' => [1, 1, 1, 1, 1], 'stories_bn' => [1, 1, 1, 1, 1], 'media' => [1, 1, 1, 0, 1], 'clients' => [1, 0, 0, 0, 0], 'packages' => [0, 0, 0, 0, 0], 'distribution' => [1, 0, 0, 0, 0], 'settings' => [0, 0, 0, 0, 0], 'ai' => [1, 1, 0, 0, 0], 'history' => [1, 0, 0, 0, 0], 'audit' => [0, 0, 0, 0, 0],
            ],
            'Strategist' => [
                'stories' => [1, 0, 1, 0, 0], 'stories_bn' => [1, 0, 1, 0, 0], 'media' => [1, 0, 0, 0, 0], 'clients' => [1, 0, 0, 0, 0], 'packages' => [0, 0, 0, 0, 0], 'distribution' => [0, 0, 0, 0, 0], 'settings' => [0, 0, 0, 0, 0], 'ai' => [0, 0, 0, 0, 0], 'history' => [1, 0, 0, 0, 0], 'audit' => [0, 0, 0, 0, 0],
            ],
            'Admin Report' => [
                'stories' => [1, 0, 0, 0, 0], 'stories_bn' => [1, 0, 0, 0, 0], 'media' => [1, 0, 0, 0, 0], 'clients' => [1, 0, 0, 0, 0], 'packages' => [0, 0, 0, 0, 0], 'distribution' => [1, 0, 0, 0, 0], 'settings' => [0, 0, 0, 0, 0], 'ai' => [0, 0, 0, 0, 0], 'history' => [1, 0, 0, 0, 0], 'audit' => [1, 0, 0, 0, 0],
            ],
            'Business Team' => [
                'stories' => [0, 0, 0, 0, 0], 'stories_bn' => [0, 0, 0, 0, 0], 'media' => [0, 0, 0, 0, 0], 'clients' => [1, 1, 1, 0, 1], 'packages' => [1, 1, 1, 0, 1], 'distribution' => [1, 1, 0, 0, 0], 'settings' => [0, 0, 0, 0, 0], 'ai' => [0, 0, 0, 0, 0], 'history' => [0, 0, 0, 0, 0], 'audit' => [0, 0, 0, 0, 0],
            ],
            'Client Bangla (Without AP)' => [
                'stories' => [0, 0, 0, 0, 0], 'stories_bn' => [1, 0, 0, 0, 0], 'media' => [1, 0, 0, 0, 0], 'clients' => [0, 0, 0, 0, 0], 'packages' => [0, 0, 0, 0, 0], 'distribution' => [0, 0, 0, 0, 0], 'settings' => [0, 0, 0, 0, 0], 'ai' => [0, 0, 0, 0, 0], 'history' => [0, 0, 0, 0, 0], 'audit' => [0, 0, 0, 0, 0],
            ],
            'Uploader-Bangla' => [
                'stories' => [0, 0, 0, 0, 0], 'stories_bn' => [1, 1, 1, 0, 0], 'media' => [1, 1, 0, 0, 0], 'clients' => [0, 0, 0, 0, 0], 'packages' => [0, 0, 0, 0, 0], 'distribution' => [0, 0, 0, 0, 0], 'settings' => [0, 0, 0, 0, 0], 'ai' => [1, 0, 0, 0, 0], 'history' => [1, 0, 0, 0, 0], 'audit' => [0, 0, 0, 0, 0],
            ],
            'Uploader-English' => [
                'stories' => [1, 1, 1, 0, 0], 'stories_bn' => [0, 0, 0, 0, 0], 'media' => [1, 1, 0, 0, 0], 'clients' => [0, 0, 0, 0, 0], 'packages' => [0, 0, 0, 0, 0], 'distribution' => [0, 0, 0, 0, 0], 'settings' => [0, 0, 0, 0, 0], 'ai' => [1, 0, 0, 0, 0], 'history' => [1, 0, 0, 0, 0], 'audit' => [0, 0, 0, 0, 0],
            ],
        ];

        $canonicalNames = array_column($roles, 'name');
        $extraRoles = DB::table('roles')->whereNotIn('name', $canonicalNames)->get();
        foreach ($extraRoles as $extra) {
            if (DB::table('users')->where('role_id', $extra->id)->count() === 0) {
                DB::table('role_permissions')->where('role_id', $extra->id)->delete();
                DB::table('roles')->where('id', $extra->id)->delete();
            }
        }

        foreach ($roles as $roleData) {
            $existing = DB::table('roles')->where('name', $roleData['name'])->first();
            if ($existing) {
                $roleId = $existing->id;
                DB::table('roles')->where('id', $roleId)->update([
                    'type' => $roleData['type'],
                    'description' => $roleData['description'],
                    'is_locked' => $roleData['is_locked'],
                    'updated_at' => now(),
                ]);
            } else {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $roleData['name'],
                    'type' => $roleData['type'],
                    'description' => $roleData['description'],
                    'is_locked' => $roleData['is_locked'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($modules as $module) {
                $p = $perms[$roleData['name']][$module] ?? [0, 0, 0, 0, 0];
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'module' => $module],
                    [
                        'can_view' => (bool) $p[0],
                        'can_create' => (bool) $p[1],
                        'can_edit' => (bool) $p[2],
                        'can_publish' => (bool) $p[3],
                        'can_delete' => (bool) $p[4],
                    ]
                );
            }
        }
    }
}
