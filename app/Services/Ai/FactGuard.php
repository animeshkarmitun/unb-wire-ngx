<?php

namespace App\Services\Ai;

class FactGuard
{
    /**
     * Extract facts present in the AI output but absent from the source text
     * (numbers, quoted spans, proper-noun sequences) — FR-AI-005.
     *
     * @return array<int, string>
     */
    public function extract(?string $source, ?string $output): array
    {
        $src = mb_strtolower(strip_tags((string) $source));
        $out = strip_tags((string) $output);
        if (trim($out) === '') {
            return [];
        }

        $facts = [];

        preg_match_all('/[$£€]?\s?\b\d[\d,.]*\s?(?:%|bn|mn|thousand|million|billion)?\b/u', $out, $m);
        foreach (array_unique($m[0]) as $num) {
            $norm = mb_strtolower(trim($num));
            if ($norm !== '' && ! str_contains($src, $norm)) {
                $facts[] = trim($num);
            }
        }

        preg_match_all('/["\x{201C}]([^"\x{201C}\x{201D}]{8,})["\x{201D}]/u', $out, $q);
        foreach (array_unique($q[1]) as $quote) {
            if (! str_contains($src, mb_strtolower(trim($quote)))) {
                $facts[] = '"'.trim($quote).'"';
            }
        }

        preg_match_all('/\p{Lu}[\p{L}.]+(?:\s+\p{Lu}[\p{L}.]+)+/u', $out, $n);
        foreach (array_unique($n[0]) as $name) {
            $norm = mb_strtolower(trim($name));
            if (! str_contains($src, $norm)) {
                $facts[] = trim($name);
            }
        }

        return array_values(array_unique($facts));
    }
}
