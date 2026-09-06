<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $adminId = $admin?->id;

        $defaultAiDesk = [
            'preeditEn' => true,
            'preeditBn' => true,
            'preeditPhotos' => false,
            'autoPublish' => false,
            'autoCats' => ['Weather', 'Sports results', 'Market close', 'Currency rates'],
            'monthlyCap' => 500000,
            'stylePrompt' => 'You are a UNB wire copy editor. Rules: inverted pyramid; active voice; past tense for events; attribute every claim (said, according to); no adjectives of judgement; spell out numbers one to nine; dateline format "DHAKA, Aug 25 —"; end with "END/UNB/####"; never invent names, figures or quotes; if a fact is uncertain, flag it with [VERIFY].',
            'killed' => false,
            'model' => 'openai:gpt-4o',
        ];

        DB::table('settings')->updateOrInsert(
            ['key' => 'ai.desk'],
            [
                'value' => json_encode($defaultAiDesk),
                'updated_by' => $adminId,
                'updated_at' => now(),
            ]
        );

        // Seed initial monthly token usage rollup matching prototype figures
        DB::table('ai_token_usage_daily')->delete();
        $today = now()->toDateString();
        $rollups = [
            ['scope' => 'desk:en', 'kind' => 'preedit', 'tokens' => 188200, 'cost_micros' => 2521880],
            ['scope' => 'desk:bn', 'kind' => 'preedit', 'tokens' => 97600, 'cost_micros' => 1307840],
            ['scope' => 'photos', 'kind' => 'caption', 'tokens' => 26600, 'cost_micros' => 356440],
        ];

        foreach ($rollups as $r) {
            DB::table('ai_token_usage_daily')->updateOrInsert(
                ['date' => $today, 'scope' => $r['scope'], 'kind' => $r['kind']],
                [
                    'tokens' => $r['tokens'],
                    'cost_micros' => $r['cost_micros'],
                ]
            );
        }
    }
}
