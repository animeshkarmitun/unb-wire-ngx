<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AiSettings extends Component
{
    public const DEFAULT_STYLE_PROMPT = 'You are a UNB wire copy editor. Rules: inverted pyramid; active voice; past tense for events; attribute every claim (said, according to); no adjectives of judgement; spell out numbers one to nine; dateline format "DHAKA, Aug 25 —"; end with "END/UNB/####"; never invent names, figures or quotes; if a fact is uncertain, flag it with [VERIFY].';

    public bool $preeditEn = true;

    public bool $preeditBn = true;

    public bool $preeditPhotos = false;

    public bool $autoPublish = false;

    public array $autoCats = ['Weather', 'Sports results', 'Market close', 'Currency rates'];

    public int $monthlyCap = 500000;

    public string $stylePrompt = self::DEFAULT_STYLE_PROMPT;

    public bool $killed = false;

    public string $model = 'openai:gpt-4o';

    public bool $showAutoModal = false;

    public array $allCategories = [
        'Weather',
        'Sports results',
        'Market close',
        'Currency rates',
        'Bangladesh',
        'World',
        'Business',
    ];

    public function mount(): void
    {
        $s = Setting::where('key', 'ai.desk')->first();
        if ($s) {
            $v = is_string($s->value) ? json_decode($s->value, true) : $s->value;
            if (is_array($v)) {
                $this->preeditEn = (bool) ($v['preeditEn'] ?? true);
                $this->preeditBn = (bool) ($v['preeditBn'] ?? true);
                $this->preeditPhotos = (bool) ($v['preeditPhotos'] ?? false);
                $this->autoPublish = (bool) ($v['autoPublish'] ?? false);
                $this->autoCats = is_array($v['autoCats'] ?? null) ? $v['autoCats'] : ['Weather', 'Sports results', 'Market close', 'Currency rates'];
                $this->monthlyCap = max(50000, (int) ($v['monthlyCap'] ?? 500000));
                $this->stylePrompt = (string) ($v['stylePrompt'] ?? self::DEFAULT_STYLE_PROMPT);
                $this->killed = (bool) ($v['killed'] ?? false);
                $this->model = (string) ($v['model'] ?? 'openai:gpt-4o');
            }
        }
    }

    public function toggleAutoPublish(): void
    {
        if (! $this->autoPublish) {
            $this->showAutoModal = true;
        } else {
            $this->autoPublish = false;
        }
    }

    public function confirmAutoPublish(): void
    {
        $this->autoPublish = true;
        $this->showAutoModal = false;
        $this->dispatch('toast', message: '⚠ Auto-publish ON for allowlisted categories — remember to Save');
    }

    public function cancelAutoPublish(): void
    {
        $this->showAutoModal = false;
    }

    public function toggleCategory(string $category): void
    {
        if (in_array($category, $this->autoCats, true)) {
            $this->autoCats = array_values(array_filter($this->autoCats, fn ($c) => $c !== $category));
        } else {
            $this->autoCats[] = $category;
        }
    }

    public function toggleKill(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'edit');

        $this->killed = ! $this->killed;
        if ($this->killed) {
            $this->autoPublish = false;
        }

        // Emergency kill switch acts immediately on the settings table
        Setting::updateOrCreate(
            ['key' => 'ai.desk'],
            [
                'value' => [
                    'preeditEn' => $this->preeditEn,
                    'preeditBn' => $this->preeditBn,
                    'preeditPhotos' => $this->preeditPhotos,
                    'autoPublish' => $this->autoPublish,
                    'autoCats' => $this->autoCats,
                    'monthlyCap' => $this->monthlyCap,
                    'stylePrompt' => $this->stylePrompt,
                    'killed' => $this->killed,
                    'model' => $this->model,
                ],
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]
        );

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $this->killed ? 'ai.kill_switch.on' : 'ai.kill_switch.off',
            'entity_type' => 'setting',
            'entity_id' => null,
            'diff' => ['killed' => $this->killed, 'autoPublish' => $this->autoPublish],
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: $this->killed ? '⛔ All AI features disabled newsroom-wide' : '✓ AI features re-enabled');
    }

    public function resetDefaults(): void
    {
        $this->preeditEn = true;
        $this->preeditBn = true;
        $this->preeditPhotos = false;
        $this->autoPublish = false;
        $this->autoCats = ['Weather', 'Sports results', 'Market close', 'Currency rates'];
        $this->monthlyCap = 500000;
        $this->stylePrompt = self::DEFAULT_STYLE_PROMPT;
        $this->dispatch('toast', message: 'Defaults restored — Save to persist');
    }

    public function save(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'edit');

        $this->validate([
            'monthlyCap' => 'required|integer|min:50000',
            'stylePrompt' => 'required|string',
            'autoCats' => 'array',
        ]);

        $oldSetting = Setting::where('key', 'ai.desk')->first();
        $oldValue = $oldSetting ? (is_string($oldSetting->value) ? json_decode($oldSetting->value, true) : $oldSetting->value) : [];

        $newValue = [
            'preeditEn' => $this->preeditEn,
            'preeditBn' => $this->preeditBn,
            'preeditPhotos' => $this->preeditPhotos,
            'autoPublish' => $this->autoPublish,
            'autoCats' => $this->autoCats,
            'monthlyCap' => $this->monthlyCap,
            'stylePrompt' => $this->stylePrompt,
            'killed' => $this->killed,
            'model' => $this->model,
        ];

        Setting::updateOrCreate(
            ['key' => 'ai.desk'],
            [
                'value' => $newValue,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]
        );

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'ai.settings.updated',
            'entity_type' => 'setting',
            'entity_id' => null,
            'diff' => ['old' => $oldValue, 'new' => $newValue],
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: '✓ AI settings saved — applied across the newsroom');
    }

    public function render()
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $rollups = DB::table('ai_token_usage_daily')
            ->where('date', '>=', $monthStart)
            ->get();

        $tokensEn = (int) $rollups->where('scope', 'desk:en')->sum('tokens');
        $tokensBn = (int) $rollups->where('scope', 'desk:bn')->sum('tokens');
        $tokensPhotos = (int) $rollups->where('scope', 'photos')->sum('tokens');
        $totalTokens = $tokensEn + $tokensBn + $tokensPhotos;

        // Use prototype defaults if no tokens recorded yet
        if ($totalTokens === 0) {
            $tokensEn = 188200;
            $tokensBn = 97600;
            $tokensPhotos = 26600;
            $totalTokens = 312400;
        }

        $callsEn = 418;
        $callsBn = 261;
        $callsPhotos = 89;

        $costEst = round($totalTokens / 1000 * 13.4);
        $pct = $this->monthlyCap > 0 ? min(100, (int) round(($totalTokens / $this->monthlyCap) * 100)) : 0;

        return view('livewire.admin.ai-settings', [
            'totalTokens' => $totalTokens,
            'tokensEn' => $tokensEn,
            'tokensBn' => $tokensBn,
            'tokensPhotos' => $tokensPhotos,
            'callsEn' => $callsEn,
            'callsBn' => $callsBn,
            'callsPhotos' => $callsPhotos,
            'costEst' => $costEst,
            'usagePercent' => $pct,
        ]);
    }
}
