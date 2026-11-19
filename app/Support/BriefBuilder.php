<?php

namespace App\Support;

/**
 * COS-78: builds story briefs that stand on their own — whitespace normalised,
 * cut on a sentence boundary when possible, never mid-word.
 */
class BriefBuilder
{
    public static function fromPlainText(string $text, int $limit = 280): string
    {
        $text = preg_replace('/\r\n|\r|\n/u', ' ', $text);
        $text = preg_replace('/\s{2,}/u', ' ', trim((string) $text));

        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);

        // Prefer a complete sentence inside the budget (Bangla daṛi included).
        if (preg_match('/^(.*[.!?।])(?:\s|$)/us', $cut.' ', $m) && mb_strlen($m[1]) > $limit * 0.4) {
            return rtrim($m[1]);
        }

        // Otherwise fall back to a word boundary with an ellipsis (ellipsis
        // included, the result never exceeds the limit — brief is varchar(280)).
        $space = mb_strrpos($cut, ' ');
        $trim = $limit - mb_strlen('…');

        return ($space === false || $space > $trim ? mb_substr($cut, 0, $trim) : mb_substr($cut, 0, $space)).'…';
    }
}
