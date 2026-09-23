<?php

namespace App\Services\Ai;

class WireStyleLinter
{
    /**
     * Lint AI-generated wire copy against UNB style rules (M13-AI-003 / FR-AI-002).
     *
     * @return array<int, array{rule: string, severity: string, message: string}>
     */
    public function lint(?string $headline, ?string $bodyHtml, bool $bangla = false): array
    {
        $violations = [];
        $body = trim(html_entity_decode(strip_tags((string) $bodyHtml)));

        if ($body === '') {
            return $violations;
        }

        $verifyCount = preg_match_all('/\[VERIFY\]/i', $body);
        if ($verifyCount > 0) {
            $violations[] = [
                'rule' => 'verify_flags',
                'severity' => 'warning',
                'message' => $verifyCount.' [VERIFY] flag(s) — confirm facts before publishing',
            ];
        }

        if ($bangla) {
            return $violations;
        }

        if (! preg_match('/^[A-Z][A-Za-z.\s]{1,30},\s+[A-Z][a-z]{2}\s+\d{1,2}\s+[\x{2014}\-]/u', $body)) {
            $violations[] = [
                'rule' => 'dateline',
                'severity' => 'error',
                'message' => 'Missing wire dateline (expected "CITY, Mon DD —" opener)',
            ];
        }

        if (! preg_match('/END\/UNB/i', $body)) {
            $violations[] = [
                'rule' => 'signoff',
                'severity' => 'error',
                'message' => 'Missing END/UNB sign-off marker',
            ];
        }

        $lead = mb_substr($body, 0, 240);
        if (preg_match('/\b(announces|says|tells|declares|reveals|opens|launches|warns|confirms|states)\b/i', $lead, $m)) {
            $violations[] = [
                'rule' => 'tense',
                'severity' => 'warning',
                'message' => 'Possible present tense in lead ("'.$m[1].'") — wire copy uses past tense for events',
            ];
        }

        if (preg_match('/["\x{201C}]/u', $lead) && ! preg_match('/\b(said|told|according to|added|explained|noted)\b/i', $body)) {
            $violations[] = [
                'rule' => 'attribution',
                'severity' => 'warning',
                'message' => 'Quote without visible attribution — every claim needs "said" / "according to"',
            ];
        }

        return $violations;
    }
}
