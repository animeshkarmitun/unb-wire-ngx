<?php

namespace Tests\Feature\Services;

use App\Services\Ai\WireStyleLinter;
use Tests\TestCase;

class WireStyleLinterTest extends TestCase
{
    private function linter(): WireStyleLinter
    {
        return app(WireStyleLinter::class);
    }

    public function test_good_wire_copy_passes(): void
    {
        $body = 'DHAKA, Aug 25 — The cabinet approved the annual budget Tuesday. The finance minister said the deficit would narrow. END/UNB/1234//DHAKA';

        $this->assertSame([], $this->linter()->lint('Cabinet approves budget', '<p>'.$body.'</p>'));
    }

    public function test_missing_dateline_and_signoff_flagged_as_errors(): void
    {
        $violations = $this->linter()->lint('Headline', '<p>The cabinet approved the budget Tuesday afternoon.</p>');

        $rules = array_column($violations, 'rule');
        $this->assertContains('dateline', $rules);
        $this->assertContains('signoff', $rules);
        foreach ($violations as $v) {
            if (in_array($v['rule'], ['dateline', 'signoff'], true)) {
                $this->assertSame('error', $v['severity']);
            }
        }
    }

    public function test_verify_flags_counted_as_warning(): void
    {
        $body = 'DHAKA, Aug 25 — The minister said [VERIFY] the figure was 500. [VERIFY] END/UNB';

        $violations = $this->linter()->lint('Headline', '<p>'.$body.'</p>');

        $verify = array_values(array_filter($violations, fn ($v) => $v['rule'] === 'verify_flags'));
        $this->assertCount(1, $verify);
        $this->assertSame('warning', $verify[0]['severity']);
        $this->assertStringContainsString('2 [VERIFY]', $verify[0]['message']);
    }

    public function test_present_tense_lead_flagged(): void
    {
        $body = 'DHAKA, Aug 25 — The minister announces the budget today. Officials said spending would rise. END/UNB';

        $violations = $this->linter()->lint('Headline', '<p>'.$body.'</p>');

        $rules = array_column($violations, 'rule');
        $this->assertContains('tense', $rules);
    }

    public function test_unattributed_quote_flagged(): void
    {
        $body = 'DHAKA, Aug 25 — "This is a disaster," the protest continued into the night. END/UNB';

        $violations = $this->linter()->lint('Headline', '<p>'.$body.'</p>');

        $rules = array_column($violations, 'rule');
        $this->assertContains('attribution', $rules);
    }

    public function test_attributed_quote_not_flagged(): void
    {
        $body = 'DHAKA, Aug 25 — "This is a disaster," said the protest leader as the rally continued. END/UNB';

        $this->assertNotContains('attribution', array_column($this->linter()->lint('Headline', '<p>'.$body.'</p>'), 'rule'));
    }

    public function test_bangla_copy_only_checks_verify_flags(): void
    {
        $violations = $this->linter()->lint('শিরোনাম', '<p>ঢাকা থেকে একটি সংবাদ। [VERIFY]</p>', true);

        $this->assertSame(['verify_flags'], array_column($violations, 'rule'));
    }

    public function test_empty_body_returns_no_violations(): void
    {
        $this->assertSame([], $this->linter()->lint('Headline', ''));
    }
}
