<?php

namespace App\Services;

use App\Models\Story;

class DuplicateDetectionService
{
    public const LEVEL_WARN = 'warn';

    public const LEVEL_HIGH = 'high';

    private const TITLE_THRESHOLD = 0.85;

    private const BODY_THRESHOLD = 0.75;

    private const HIGH_COMBINED = 0.72;

    private const WARN_COMBINED = 0.45;

    private const WINDOW_DAYS = 30;

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(strip_tags($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    public static function fingerprint(string $text): string
    {
        return sha1(self::normalize($text));
    }

    /**
     * Detect duplicates against published stories from the last 30 days.
     *
     * @return array<int, array{public_id: string, headline: string, title_score: float, body_score: float, combined: float, level: string}>
     */
    public function matches(string $title, string $bodyText, ?string $language = null, ?int $excludeId = null): array
    {
        $normTitle = self::normalize($title);
        $normBody = self::normalize($bodyText);
        if ($normTitle === '' && $normBody === '') {
            return [];
        }

        $candidates = Story::query()
            ->where('status', 'published')
            ->when($language, fn ($q) => $q->where('language', $language))
            ->where('published_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get(['id', 'public_id', 'headline', 'body_text', 'body_fingerprint']);

        $titleNorms = [];
        $bodyGrams = self::trigrams($normBody);
        $finger = $normBody !== '' ? sha1($normBody) : null;

        $out = [];
        foreach ($candidates as $c) {
            $candTitle = self::normalize((string) $c->headline);
            $titleScore = self::titleScore($normTitle, $candTitle);
            $bodyScore = self::bodyScore($normBody, self::normalize((string) $c->body_text), $bodyGrams, $finger, $c->body_fingerprint);

            $exactTitle = $normTitle !== '' && $normTitle === $candTitle;
            $exactBody = $finger !== null && $c->body_fingerprint === $finger;
            $combined = 0.4 * $titleScore + 0.6 * $bodyScore;

            if ($exactTitle || $exactBody || $titleScore >= self::TITLE_THRESHOLD || $bodyScore >= self::BODY_THRESHOLD) {
                $level = self::LEVEL_HIGH;
            } elseif ($combined >= self::WARN_COMBINED) {
                $level = self::LEVEL_WARN;
            } else {
                continue;
            }

            $titleNorms[$c->id] = true;
            $out[] = [
                'public_id' => (string) $c->public_id,
                'headline' => (string) $c->headline,
                'title_score' => round($titleScore, 3),
                'body_score' => round($bodyScore, 3),
                'combined' => round($combined, 3),
                'level' => $level,
            ];
        }

        usort($out, fn ($a, $b) => $b['combined'] <=> $a['combined']);

        return $out;
    }

    private static function titleScore(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $max = max(mb_strlen($a), mb_strlen($b));
        $dist = levenshtein($a, $b);

        return max(0.0, 1 - $dist / $max);
    }

    private static function bodyScore(string $a, string $b, array $aGrams, ?string $aFp, ?string $bFp): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($aFp !== null && $bFp !== null && $aFp === $bFp) {
            return 1.0;
        }

        $bGrams = self::trigrams($b);
        if ($aGrams === [] || $bGrams === []) {
            return 0.0;
        }
        $inter = count(array_intersect_key($aGrams, $bGrams));
        $union = count($aGrams) + count($bGrams) - $inter;

        return $union > 0 ? $inter / $union : 0.0;
    }

    private static function trigrams(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($chars);
        if ($n === 0) {
            return [];
        }
        if ($n < 3) {
            return [implode('', $chars) => true];
        }
        $out = [];
        for ($i = 0; $i <= $n - 3; $i++) {
            $out[$chars[$i].$chars[$i + 1].$chars[$i + 2]] = true;
        }

        return $out;
    }
}
