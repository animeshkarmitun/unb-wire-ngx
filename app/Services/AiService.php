<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Services\Ai\AiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\StubAiProvider;
use Illuminate\Support\Facades\DB;

class AiService
{
    private AiProvider $provider;

    public function __construct(?AiProvider $provider = null)
    {
        $this->provider = $provider ?? $this->resolveProvider();
    }

    public function call(string $kind, array $payload, int $userId, ?int $storyId = null): array
    {
        $settings = DB::table('settings')->where('key', 'ai.desk')->first();
        $cfg = $settings ? (is_string($settings->value) ? json_decode($settings->value, true) : $settings->value) : [];

        if (! empty($cfg['killed'])) {
            return ['error' => 'AI kill switch is ON'];
        }

        $deskKey = ($payload['language'] ?? 'en') === 'bn' ? 'preeditBn' : 'preeditEn';
        if (isset($cfg[$deskKey]) && ! $cfg[$deskKey]) {
            return ['error' => 'AI disabled for this desk'];
        }

        $cap = (int) ($cfg['monthlyCap'] ?? 500000);
        $monthStart = now()->startOfMonth()->toDateString();
        $used = (int) DB::table('ai_token_usage_daily')->where('date', '>=', $monthStart)->sum('tokens');
        if ($used >= $cap) {
            return ['error' => 'Monthly AI token budget exceeded'];
        }

        $payload['stylePrompt'] = $cfg['stylePrompt'] ?? null;

        $result = $this->provider->call($kind, $payload);

        if ($result->isError()) {
            return ['error' => $result->error];
        }

        AiGeneration::create([
            'story_id' => $storyId,
            'user_id' => $userId,
            'kind' => $kind,
            'prompt_version' => 'v1',
            'model' => $result->model,
            'input_hash' => hash('sha256', json_encode($payload)),
            'pack' => $result->toPack(),
            'new_facts' => null,
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'cost_micros' => $result->costMicros,
            'applied' => null,
            'created_at' => now(),
        ]);

        $totalTokens = $result->tokensIn + $result->tokensOut;
        DB::table('ai_token_usage_daily')->upsert([
            'date' => now()->toDateString(),
            'scope' => 'desk:en',
            'kind' => $kind,
            'tokens' => $totalTokens,
            'cost_micros' => $result->costMicros,
        ], ['date', 'scope', 'kind'], [
            'tokens' => DB::raw('ai_token_usage_daily.tokens + '.$totalTokens),
            'cost_micros' => DB::raw('ai_token_usage_daily.cost_micros + '.$result->costMicros),
        ]);

        return $result->toPack();
    }

    private function resolveProvider(): AiProvider
    {
        $driver = config('services.openai.driver', 'stub');

        if ($driver === 'openai' && config('services.openai.api_key')) {
            return new OpenAiProvider;
        }

        return new StubAiProvider;
    }
}
