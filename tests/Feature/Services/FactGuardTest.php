<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\Ai\FactGuard;
use App\Services\AiService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FactGuardTest extends TestCase
{
    use RefreshDatabase;

    private function guard(): FactGuard
    {
        return app(FactGuard::class);
    }

    public function test_numbers_in_output_but_not_in_source_are_flagged(): void
    {
        $facts = $this->guard()->extract(
            'The minister spoke about the budget on Tuesday.',
            'The minister said the deficit was 500 million taka and growth hit 7.2 percent.'
        );

        $this->assertNotEmpty(array_filter($facts, fn ($f) => str_contains($f, '500')));
        $this->assertNotEmpty(array_filter($facts, fn ($f) => str_contains($f, '7.2')));
    }

    public function test_numbers_present_in_source_are_not_flagged(): void
    {
        $facts = $this->guard()->extract(
            'The deficit was 500 million taka and growth hit 7.2 percent.',
            'The deficit was 500 million taka. Growth was recorded at 7.2 percent.'
        );

        $this->assertSame([], $facts);
    }

    public function test_invented_quote_is_flagged(): void
    {
        $facts = $this->guard()->extract(
            'The minister spoke about the budget on Tuesday.',
            'The minister said: "We will rebuild the entire economy from scratch."'
        );

        $this->assertNotEmpty(array_filter($facts, fn ($f) => str_contains($f, 'rebuild the entire economy')));
    }

    public function test_quote_present_in_source_is_not_flagged(): void
    {
        $src = 'The minister said: "We will rebuild the entire economy from scratch." on Tuesday.';
        $facts = $this->guard()->extract($src, 'Repeating, the minister said: "We will rebuild the entire economy from scratch."');

        $this->assertSame([], $facts);
    }

    public function test_invented_proper_name_is_flagged(): void
    {
        $facts = $this->guard()->extract(
            'A government spokesperson addressed reporters in Dhaka.',
            'Finance Minister Ashraf Mahmud unveiled the new package in parliament.'
        );

        $this->assertNotEmpty(array_filter($facts, fn ($f) => str_contains($f, 'Ashraf Mahmud')));
    }

    public function test_source_names_are_not_flagged(): void
    {
        $facts = $this->guard()->extract(
            'Finance Minister Ashraf Mahmud unveiled the package.',
            'Finance Minister Ashraf Mahmud unveiled the package in parliament.'
        );

        $this->assertSame([], $facts);
    }

    public function test_empty_output_returns_no_facts(): void
    {
        $this->assertSame([], $this->guard()->extract('source', ''));
    }

    public function test_ai_service_attaches_new_facts_to_pack(): void
    {
        $this->seed(SettingSeeder::class);
        DB::table('ai_token_usage_daily')->delete();
        $user = User::factory()->create();

        $pack = app(AiService::class)->call('preedit', ['text' => 'The minister spoke.', 'headline' => 'Minister speaks'], $user->id);

        $this->assertIsArray($pack['new_facts']);
    }
}
