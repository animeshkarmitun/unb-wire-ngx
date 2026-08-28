<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    private \App\Models\User $user;
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = \App\Models\User::factory()->create();
    }

    private function aiCall(string $kind = 'preedit', array $payload = []): array
    {
        return app(AiService::class)->call($kind, array_merge(['text'=>'hello world','headline'=>'Test','language'=>'en'], $payload), $this->user->id, null);
    }

    public function test_kill_switch_blocks(): void
    {
        DB::table('settings')->insert(['key'=>'ai.desk','value'=>json_encode(['killed'=>true]),'updated_at'=>now()]);
        $res = $this->aiCall();
        $this->assertEquals('AI kill switch is ON', $res['error']);
    }

    public function test_desk_disabled_blocks(): void
    {
        DB::table('settings')->insert(['key'=>'ai.desk','value'=>json_encode(['preeditEn'=>false]),'updated_at'=>now()]);
        $res = $this->aiCall();
        $this->assertEquals('AI disabled for this desk', $res['error']);
    }

    public function test_monthly_cap_blocks(): void
    {
        DB::table('settings')->insert(['key'=>'ai.desk','value'=>json_encode(['monthlyCap'=>10]),'updated_at'=>now()]);
        DB::table('ai_token_usage_daily')->insert(['date'=>now()->toDateString(),'scope'=>'desk:en','kind'=>'preedit','tokens'=>20,'cost_micros'=>100]);
        $res = $this->aiCall();
        $this->assertEquals('Monthly AI token budget exceeded', $res['error']);
    }

    public function test_preedit_returns_pack_and_logs(): void
    {
        $res = $this->aiCall('preedit', ['text'=>'breaking news']);
        $this->assertArrayHasKey('headline', $res);
        $this->assertArrayHasKey('brief', $res);
        $this->assertDatabaseHas('ai_generations', ['kind'=>'preedit']);
        $this->assertDatabaseHas('ai_token_usage_daily', ['kind'=>'preedit','tokens'=>300]);
    }

    public function test_tags_kind(): void
    {
        $res = $this->aiCall('tags');
        $this->assertEquals('Sports', $res['category']['name']);
    }

    public function test_generate_kind_escapes_input(): void
    {
        $res = $this->aiCall('generate', ['text'=>'<script>x</script>']);
        $this->assertStringNotContainsString('<script>', $res['body']);
    }

    public function test_bangla_desk_uses_bn_toggle(): void
    {
        DB::table('settings')->insert(['key'=>'ai.desk','value'=>json_encode(['preeditBn'=>false]),'updated_at'=>now()]);
        $res = $this->aiCall('preedit', ['language'=>'bn']);
        $this->assertEquals('AI disabled for this desk', $res['error']);
    }
}
